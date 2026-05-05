<?php
namespace iProtek\Device\Helpers\Console;

use DB; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use iProtek\Device\Helpers\MClientHelper as MikroTikClient;
use iProtek\Device\Helpers\MQueryHelper as MikroTikQuery;

class MikrotikScriptHelper
{  
    public static $is_print_only = true;
    public static function executeScript($script, $is_print_only = true, $added_ids=[], $ini_context="[]")
    {
        $client = null;


        $lines = [];
        static::$is_print_only = $is_print_only;
        $result = static::validateScript($script, $lines);
        if($result["status"] != 1)
            return $result;

        $context = json_decode( $ini_context , true);

        $context["_added_ids"] =  $added_ids;
        $i = 0;
        $tables = [];


        $result = static::runBlock($lines, $i, $context, $tables, $client);
        if($result["status"] != 1){
            $result["tables"] = $tables;
            $result["context"] = $context;
            //Log::error($result);
            return $result;
        }

        return [
            "status" => 1,
            "message"=> "Completed",
            "context" => $context,
            "tables" => $tables
        ];
    }

    public static function runBlock(&$lines, &$i, &$context, &$tables, $client)
    {
        while ($i < count($lines)) {
            $line = $lines[$i];

            // IF BLOCK
            if (preg_match('/^IF\[(.+)\]$/', $line, $match)) {
                $condition = $match[1];
                $i++;

                $trueBlock = [];
                $falseBlock = [];
                $current = &$trueBlock;

                // COLLECT BLOCK
                while ($i < count($lines) && $lines[$i] !== 'END') {
                    if ($lines[$i] === 'ELSE') {
                        $current = &$falseBlock;
                        $i++;
                        continue;
                    }
                    $current[] = $lines[$i];
                    $i++;
                }

                // EVALUATE CONDITION
                $evaluate = static::evaluateCondition($condition, $context);
                if($evaluate["status"] != 1){
                    $evaluate["line"] = $i;
                    return $evaluate;
                }

                if ( $evaluate["result"] ) {
                    $subIndex = 0;
                    $result = static::runBlock($trueBlock, $subIndex, $context, $tables, $client);
                    if($result["status"] != 1){
                        $result["line"] = $i;
                        return $result;
                    }
                } else { 
                    $subIndex = 0;
                    $result = static::runBlock($falseBlock, $subIndex, $context, $tables, $client);
                    if($result["status"] != 1){
                        $result["line"] = $i;
                        return $result;
                    }
                }

            }
            // COMMAND
            elseif (str_starts_with($line, '/')) {
                $result = static::executeCommand($line, $context, $tables, $client);
                if($result["status"] != 1){
                    $result["line"] = $i;
                    return $result;
                }

            }
            // END
            elseif ($line === 'END') {
                return ["status"=>1, "message"=>"Completed"];
            }

            $i++;
        }
        return ["status"=>1, "message"=>"Completed"];
    }
    static function isValidExpression(string $expr): bool
    {
        // 1. Allow single number or quoted string
        if (preg_match('/^\s*(\d+|"([^"\\\\]|\\\\.)*")\s*$/', $expr)) {
            return true;
        }

        // 2. Allow comparisons joined by && or ||
        return preg_match(
            '/^\s*
            (
                (?:
                    \d+
                    |
                    "([^"\\\\]|\\\\.)*"
                )
                \s*(<=|>=|==|!=|<|>)\s*
                (?:
                    \d+
                    |
                    "([^"\\\\]|\\\\.)*"
                )
            )
            (\s*(&&|\|\|)\s*
                (
                    (?:
                        \d+
                        |
                        "([^"\\\\]|\\\\.)*"
                    )
                    \s*(<=|>=|==|!=|<|>)\s*
                    (?:
                        \d+
                        |
                        "([^"\\\\]|\\\\.)*"
                    )
                )
            )*
            \s*$/x',
            $expr
        ) === 1;
    }

    static function evaluateCondition($condition, $context)
    {
        $expr = static::normalizeCondition($condition, $context);
        //return ["status"=>0, "message"=>"Invalid condition: $condition convertion $expr" ];
        // Optional: strict validation (VERY IMPORTANT)
        if (!static::isValidExpression($expr)) {
            //throw new \Exception("Invalid condition: $condition");
            Log::error("Invalid condition: $condition" );
            return ["status"=>0, "message"=>"Invalid condition:<b> $condition </b> <br/> EVALUATION: $expr" ];
        }
        return[
            "status"=>1,
            "result"=> eval("return ($expr);"),
        ];
    }

    static function getSetRead($line, &$context, $client ){
        return ["status"=>1, "message"=>"Success"];
        // Extract command
        preg_match('/^(\/[^\s]+)/', $line, $cmdMatch);
        $command = $cmdMatch[1] ?? null;

        
        // Extract --get and --set
        preg_match('/--get="([^"]+)"/', $line, $getMatch);
        preg_match('/--set="([^"]+)"/', $line, $setMatch);

        $getFields = isset($getMatch[1])
            ? array_map('trim', explode(',', $getMatch[1]))
            : [];

        $setVars = isset($setMatch[1])
            ? array_map('trim', explode(',', $setMatch[1]))
            : [];

        // Execute RouterOS query
        $result = $client->query($command)->read();
        $row = $result[0] ?? [];

        // Mapping logic
        if (!empty($getFields) && !empty($setVars)) {

            if (count($getFields) !== count($setVars)) {
                //throw new Exception("Mismatch between GET and SET fields");
                Log::error("Mismatch between GET and SET fields at $line" );
                return ["status"=>0, "message"=>"Mismatch between GET and SET fields at $line"];
            }

            foreach ($getFields as $index => $field) {
                $targetVar = $setVars[$index];

                // MikroTik uses '.id' not 'id'
                $value = $row[$field] ?? $row[".$field"] ?? null;

                $context[$targetVar] = $value;
            }
        }

    }

    static function lineContext($line, $context){
           $has_error=false;
            $result = preg_replace_callback('/{(.*?)}/', function ($matches) use ($context, &$has_error) {
                $key = trim($matches[1]);

                // Handle array access like _added_ids[1]
                if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\[(?:(\d+)|"(first|last)")\]$/', $key, $parts)) {
                    $parts = array_values( array_filter($parts, function($item){
                        return "$item" !== '';
                    }));
                    $var = $parts[1];
                    //return implode(',', $parts);
                    if( $parts[2] === 'last'){
                        $index = count($context[$var]) - 1;
                    }
                    else if($parts[2] === 'first'){
                        $index = count($context[$var]) - 1;
                    }
                    else
                        $index = (int)$parts[2];

                    if(isset($context[$var][$index]))
                        return $context[$var][$index];
                    $has_error = true;
                    return " <b><code> {{$parts[0]}} </code> </b> ";
                }

                // Normal variable
                if(isset($context[$key])){
                    return  $context[$key];
                }
                //throw new \Exception("<b> $parts[0] </b>")
                $has_error = true;
                return " <b><code> {{$key}} </code> </b> ";
            }, $line);
        return [
            "status"=>($has_error?0:1), 
            "result"=>$result
            ];
    }

    static function tableRead($line, $context, &$tables, $client){

        $line_result = static::lineContext($line, $context);
        $line_value =  $line_result["result"];
        if($line_result["status"] != 1){
            $data = [ [
                    "message"=>"Error",
                    "line_raw"=>$line,
                    "line_context"=>$line_value
                ]
            ];
            $tables[] = [ 
                "status"=>0,
                "message"=>"Error Encountered",
                "data"=> static::convertDataToTable($data) 
            ];
            return ["status"=>0, "message"=>"Something goes wrong"];
        } 

        


            $data = [
                [
                    "test" => 1,
                    "test2" => 2
                ],
                [
                    "test2" => 4,
                    "test" => 2
                ],
                [
                    "test3"=>5
                ]
            ];

        $tables[] = [ 
            "status"=>1,
            "data"=> static::convertDataToTable($data) 
        ];
        return [
            "status"=>1,
            "message"=>"Success"
        ];
    }

    static function convertDataToTable(array $data){ 

        // 1. Collect all headers dynamically
        $headers = [];
        foreach ($data as $row) {
            $headers = array_unique(array_merge($headers, array_keys($row)));
        }

        // 2. Build final table
        $table = [];
        $table[] = $headers; // first row = headers

        foreach ($data as $row) {
            $line = [];
            foreach ($headers as $header) {
                $line[] = $row[$header] ?? null; // keep order consistent
            }
            $table[] = $line;
        }
        return $table;
    }


    static function executeNoRead($line, &$context, $client){
        if(static::$is_print_only){
            return ["status"=>1, "message"=>"Not Executed"];
        }
        //TODO:: Make execution here..
        return ["status"=>1, "message"=>"Not Executed"];

        //Check if its add
        if(preg_match('#^/\S+/add(\s|$)#', $line)){
            $context["_added_ids"][] = 1; //replace with actual ids
        }

        return ["status"=>1, "message"=>"Not Executed"];
    }

    static function executeCommand($line, &$context, &$tables, $client)
    {
        if ( preg_match('#^/\S+/print(\s|$)#', $line) && (stripos($line, '--set') !== false || stripos($line, '--get') !== false) ){
            //return ["status"=>0, "message"=>"GETSET".$line];
            return static::getSetRead($line, $context, $client);
        }
        else if(preg_match('#^/\S+/print(\s|$)#', $line)){
            //return ["status"=>0, "message"=>"TABLE".$line];
            return static::tableRead($line, $context, $tables, $client);
        }
        
        //return ["status"=>0, "message"=>"ANY".$line];
        return static::executeNoRead($line, $context , $client);
    }

    static function normalizeCondition($condition, $context)
    {
        //SAMPLE: id>0 && _added_ids["last"] > 1 
        // Replace logical operators
        $condition = str_replace(
            ['AND', 'OR', 'NOT'],
            ['&&', '||', '!'],
            $condition
        );

        // Replace [ ] with ( )
        // $condition = //str_replace(['[', ']'], ['(', ')'], $condition);
        $condition = trim($condition, "[] \t\n\r\0\x0B");

        $condition = preg_replace_callback(
            '/\b([a-zA-Z_]\w*)(?:\[(?:(\d+)|"(first|last)")\])?/',
            function ($matches) use ($context) {

                $matches = array_values( array_filter($matches, function($item){
                    return "$item" !== '';
                }));
                $var   = $matches[1];
                $index = $matches[2] ?? null;

                // ❌ unknown variable → reject (recommended)
                if (!array_key_exists($var, $context)) {
                    throw new \Exception("Invalid variable: {$var}");
                }

                $value = $context[$var];
                // ✅ array access
                if ($index !== null) {

                    if($index == 'last'){
                        $index = count($value) - 1;
                    }
                    else if($index == 'first'){
                        $index = 0;
                    }
                    
                    if (is_array($value) && isset($value[$index])) {
                        return $value[$index];
                    }
                    return " <code>$matches[0]</code>"; // fallback if index missing
                }

                // ❌ raw array used without index
                if (is_array($value)) {
                    return 0;
                }

                return $value;
            },
            $condition
        );

        return $condition;
    }

    static function validateScript($script, &$lines=[])
    {
        //$lines = array_values(array_filter(array_map('trim', explode("\n", $script))));
        $lines = array_values(array_map('trim', explode("\n", $script)));

        $stack = [];
        $count = 0;
        foreach ($lines as $line) {
            $count++;
            $line = trim($line);
            if(!$line) continue;

            if (str_starts_with($line, 'IF[')) {
                $stack[] = 'IF';
            } elseif ($line === 'END') {
                if (empty($stack)) {
                    //throw new Exception("Unexpected END");
                    Log::error("Unexpected END" );
                    return ["status"=>0, "message"=>"Unexpected END at line:".$count];
                }
                array_pop($stack);
            }
        }

        if (!empty($stack)) {
            Log::error("Missing END");
            return ["status"=>0, "message"=>"Missing END"];
        }

        return ["status"=>1, "message"=>"No error in ".count($lines)." line(s)"];
    }


}