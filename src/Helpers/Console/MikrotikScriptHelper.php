<?php
namespace iProtek\Device\Helpers\Console;

use DB; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use iProtek\Device\Helpers\MClientHelper as MikroTikClient;
use iProtek\Device\Helpers\MQueryHelper as MikroTikQuery;
use iProtek\Device\Helpers\Console\MikrotikHelper;
use iProtek\Device\Models\DeviceAccess;
use iProtek\Device\Helpers\DeviceVariableHelper;

class MikrotikScriptHelper
{  
    public static $is_print_only = true;
    public static function executeScript($script, $device_access_id, $is_print_only = true, $added_ids=[], $ini_context="[]")
    {
        $client = null;
        /////////// DEVICE CHECK /////////////////
        
        $device_access = DeviceAccess::where('type','mikrotik')->find($device_access_id);
        if(!$device_access){
            return ["status"=>0, "message"=>"Device Not found"];
        }
        $credInfo = [
            "host"=>$device_access->host,
            "user"=>$device_access->user,
            "password"=>$device_access->password,
            "port"=>(int)$device_access->port,
            "is_ssl"=>$device_access->is_ssl
        ];

        $result = MikrotikHelper::credential_login_check($credInfo, true);
        if($result['status'] != 1){
            return $result;
        } 
            
        //return $result;
        ////////////////// START ///////////

        $client = $result['client'];/* */
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

                $depth = 1; // 🔥 track nesting

                while ($i < count($lines) && $depth > 0) {

                    if (preg_match('/^IF\[/', $lines[$i])) {
                        $depth++;
                    }

                    if ($lines[$i] === 'END') {
                        $depth--;

                        if ($depth === 0) {
                            break; // correct END for this IF
                        }
                    }

                    // ELSE only applies at depth 1
                    if ($lines[$i] === 'ELSE' && $depth === 1) {
                        $current = &$falseBlock;
                        $i++;
                        continue;
                    }

                    $current[] = $lines[$i];
                    $i++;
                }

                // skip END
                $i++;

                // EVALUATE
                $evaluate = static::evaluateCondition($condition, $context);

                if ($evaluate["status"] != 1) {
                    $evaluate["line_no"] = $i;
                    $evaluate["line"] = $line;
                    return $evaluate;
                }

                $subIndex = 0;

                if ($evaluate["result"]) {
                    $result = static::runBlock($trueBlock, $subIndex, $context, $tables, $client);
                } else {
                    $result = static::runBlock($falseBlock, $subIndex, $context, $tables, $client);
                }

                if ($result["status"] != 1) {
                    $evaluate["line_no"] = $i;
                    $evaluate["line"] = $line;
                    return $result;
                }

                continue;
            }
            else if(preg_match('/SET\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*(?:"((?:\\\\.|[^"])*)"|([^\s]*))?/i', $line, $matches)){
                $translate_line = static::lineContext($line, $context);
                $result = DeviceVariableHelper::getVariable($translate_line["result"]);
                if($result){
                    $context[$result["name"]] = $result["value"];
                }
                //return ["status"=>0, "message"=>json_encode($context)];
            }
            // COMMAND
            elseif (str_starts_with($line, '/')) {
                //return ["status"=>0, "message"=>"Failed"];
                $result = static::executeCommand($line, $context, $tables, $client);

                if ($result["status"] != 1) {
                    $result["line"] = $i;
                    return $result;
                }
            }

            // END (only for parent caller)
            elseif ($line === 'END') {
                return ["status" => 1, "message" => "Completed"];
            }

            $i++;
        }

        return ["status" => 1, "message" => "Completed"];
    }
    static function isValidExpression(string $expr): bool
    {
        $str = '(?:"([^"\\\\]|\\\\.)*"|\'([^\'\\\\]|\\\\.)*\')';

        // 1. Allow single number or quoted string
        if (preg_match('/^\s*(\d+|' . $str . ')\s*$/', $expr)) {
            return true;
        }

        // 2. Allow comparisons joined by && or ||
        return preg_match(
            '/^\s*
            (
                (?:
                    \d+
                    |
                    ' . $str . '
                )
                \s*(<=|>=|==|!=|<|>)\s*
                (?:
                    \d+
                    |
                    ' . $str . '
                )
            )
            (\s*(&&|\|\|)\s*
                (
                    (?:
                        \d+
                        |
                        ' . $str . '
                    )
                    \s*(<=|>=|==|!=|<|>)\s*
                    (?:
                        \d+
                        |
                        ' . $str . '
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
            //Log::error("Invalid condition: $condition" );
            return ["status"=>0, "message"=>"Invalid condition:<b> $condition </b> <br/> EVALUATION: $expr" ];
        }
        return[
            "status"=>1,
            "result"=> eval("return ($expr);"),
        ];
    }

    static function getSetRead($line, &$context, $client ){
        //return ["status"=>1, "message"=>"Success"];
        $line_result = static::lineContext($line, $context);
        $line_value =  $line_result["result"];

        if($line_result["status"] != 1){
            return [
                     "status"=>0,
                    "message"=>"Error $line converted $line_value"
                ];
        } 


        // Extract command
        //preg_match('/^(\/[^\s]+)/', $line, $cmdMatch);
        //$command = $cmdMatch[1] ?? null;

        
        // Extract --get and --set
        preg_match('/--get=?\"([^"]+)\"/', $line, $getMatch);
        preg_match('/--set=?\"([^"]+)\"/', $line, $setMatch);

        $getFields = isset($getMatch[1])
            ? array_map('trim', explode(',', $getMatch[1]))
            : [];

        $setVars = isset($setMatch[1])
            ? array_map('trim', explode(',', $setMatch[1]))
            : []; 
        //return ["status"=>0, "message"=>"GG".implode(',', $getFields)];
        $line_clean = str_replace($getMatch[0], '',  $line_value);
        $line_clean = str_replace($setMatch[0], '',  $line_clean);

        //return ["status"=>0, "message"=>implode(',',$getFields)];
        if ( count($getFields) <=0 || count($getFields) !== count($setVars)) {
            return ["status"=>0, "message"=>"Please make get and set same parameter count at <b>".$line."</b>"];
        }

        // Execute RouterOS query
        
        //return ["status"=>0, "message"=>"Error: $line converted ".$line_value." ".implode(',',$setMatch)];
        
        $query = MikrotikHelper::convertCliToApiQuery($line_clean, function($baseLine, $keyValues)use($client){
            return MikrotikHelper::find_command($client, $baseLine, $keyValues);
        });

        $response =  $client->query($query['query'])->read();
        $status = 1;
        if(is_array($response) && isset($response['after']) && isset($response['after']['message'])){
            
            return [
                "status"=>0,
                "message"=>$response['after']['message'],
                "line_raw"=>$line,
                "line_context"=>$line_value
            ];
        }

        $row = $response[0] ?? [];

        // Mapping logic
        if (!empty($getFields) && !empty($setVars)) {


            foreach ($getFields as $index => $field) {
                $targetVar = $setVars[$index];

                // MikroTik uses '.id' not 'id'
                $value = $row[$field] ?? $row[".$field"] ??'';

                $context[$targetVar] = $value;
            }
        }
        return ["status"=>1, "message"=>"Success"];

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
            return ["status"=>0, "message"=>"Something goes wrong $line => $line_value"];
        }



        $query = MikrotikHelper::convertCliToApiQuery($line_value, function($baseLine, $keyValues)use($client){
            return MikrotikHelper::find_command($client, $baseLine, $keyValues);
        });

        $response =  $client->query($query['query'])->read();
        $status = 1;
        if(is_array($response) && isset($response['after']) && isset($response['after']['message'])){
            
            $data = [
                "message"=>$response['after']['message'],
                "line_raw"=>$line,
                "line_context"=>$line_value
            ];
        }else{
            $data = $response;
        }

        $tables[] = [ 
            "status"=>$status,
            "data"=> static::convertDataToTable($data) 
        ];

        return [
            "status"=>$status,
            "message"=> $status? "Success" : "Has an error."
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
        if(count($headers) > 0 ){
            $table[] = $headers; // first row = headers

            foreach ($data as $row) {
                $line = [];
                foreach ($headers as $header) {
                    $line[] = $row[$header] ?? null; // keep order consistent
                }
                $table[] = $line;
            }
        }
        return $table;
    }


    static function executeNoRead($line, &$context, $client){
        
        $line_result = static::lineContext($line, $context);
        $line_value =  $line_result["result"];
        if($line_result["status"] != 1){
            return ["status"=>0, "message"=>"Something goes wrong $line => $line_value"];
        }

        //Check if its add
        if(preg_match('#^/\S+/add(\s|$)#', $line)){
            //$context["_added_ids"][] = 1; //replace with actual ids
            return static::add($line, $line_value, $context, $client);
        }
        //Check if its add
        if(preg_match('#^/\S+/set(\s|$)#', $line)){
            return static::set($line, $line_value, $client);
        }
        //Check if its add
        if(preg_match('#^/\S+/remove(\s|$)#', $line)){
            return static::remove($line, $line_value, $client);
        }

        //IMPLEMENT OTHER COMMAND ASIDE FROM add set and remove

        return ["status"=>0, "message"=>"Command unknown not implement, use ( print, add, set, remove) on $line"];
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
                if(is_numeric($value))
                    return $value;

                return "'$value'";
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

    static function add($line, $line_value, &$context, $client){
        //GET THE 
        $setVarName = null;
        $line_clean = $line_value;
        preg_match('/--pass-id=?\"([^"]+)\"/', $line_value, $setIdMatch);
        if(count($setIdMatch) > 1){
            $setVarName = trim($setIdMatch[1]);
            $line_clean = str_replace($setIdMatch[0], '',  $line_value);
        }

        if(static::$is_print_only){
            if($setVarName){ 
                if(isset($context[$setVarName]))
                    return ["status"=>1, "message"=>"Added", "id"=> $context[$setVarName]];
                return ["status"=>0, "message"=>"The test print <b> $setVarName </b> was not set on $line"];
            }
            return ["status"=>1, "message"=>"Warning: Id not passed to any variable."];
        }

        
        $query = MikrotikHelper::convertCliToApiQuery($line_clean);
        
        $response =  $client->query($query['query'])->read();

        if(is_array($response) && isset($response['after']) && isset($response['after']['message'])){   
            return [
                "status"=>0,
                "message"=>"Error ".$response['after']['message']." on $line",
                "line_raw"=>$line,
                "line_context"=>$line_value
            ];
        }
        if(!isset($response["ret"])){
            return ["status"=>0, "message"=>"Unable to retrieve .id from adding on this line: $line"];
        }

        $context["_added_ids"][] = $response["ret"];

        if($setVarName)
           $context[$setVarName] = $response["ret"];
        return ["status"=>1, "message"=>"Successfully Added.", "id"=>$response["ret"] ];
    }

    static function set($line, $line_value, $client){
        
        if(static::$is_print_only){
            return ["status"=>1, "message"=>"By pass [set] for testing purposes at $line."];
        }
    
        $query = static::convertCliToApiQuery($line_value, function($baseLine, $keyValues)use($client){
            return static::find_command($client, $baseLine, $keyValues);
        });

        $response =  $client->query($query['query'])->read();
        //Error popup
        if(is_array($response) && isset($response['after']) && isset($response['after']['message'])){
            return ["status"=>0,"message"=>"Error: ".$response['after']['message']]." at line $line";
        }
        return [
            "status"=>1, "message"=>"Successfully Updated"
        ];

    }

    static function remove($line, $line_value, $client){
        if(static::$is_print_only){
            return ["status"=>1, "message"=>"By pass [remove] for testing purposes at $line."];
        }
        
        $query = static::convertCliToApiQuery($line_value, function($baseLine, $keyValues)use($client){
            return static::find_command($client, $baseLine, $keyValues);
        });

        $response =  $client->query($query['query'])->read();
        //Error popup
        if(is_array($response) && isset($response['after']) && isset($response['after']['message'])){
            return ["status"=>0,"message"=>"Error: ".$response['after']['message']]." at line $line";
        }
        return [
            "status"=>1, "message"=>"Successfully Removed"
        ];

    }

}