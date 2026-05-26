<?php
namespace iProtek\Device\Helpers;

use DB; 
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use iProtek\Device\Models\DeviceAccount;
use iProtek\Device\Models\DeviceAccess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class DeviceVariableHelper
{ 
    /**
     * [account field="id" ] - get the data id
     * [device_account_id] - get the account id from the device upon registration.
     * [account field="plan" ] - get the "plan" field value form target source model.
     * [account field="User Name" data-json="json" connector="_"] - get the "User Name" field value form target source custom fields. 
     */
    static function account($template_str, $account, $target_name="", $traget_id = "", DeviceAccess $device_access=null){
        $sample = $template_str;

        // Define the regular expression pattern
        //$pattern = '/{{\s*(phb-event-start)\s*(?:format\s*=\s*"([^"]*)"\s*)?(?:timezone\s*=\s*"([^"]*)"\s*)?(?:offset_mins\s*=\s*([^"]*)\s*)*}}/';
        //OLD::$pattern = '/\[\s*(account)\s*(?:field\s*=\s*"([^"]*)"\s*)?(?:data-json\s*=\s*"([^"]*)"\s*)?(?:data-model\s*=\s*"([^"]*)"\s*)?(?:connector\s*=\s*"([^"]*)"\s*)?(?:instance\s*=\s*"([^"]*)"\s*)?(?:order\s*=\s*"([^"]*)"\s*)*\]/';
        //$pattern = '/\[account_name format="[^"]+"\]/';
        //OLD:: preg_match($pattern, $sample, $matches);
        preg_match('/\[account\s+([^\]]+)\]/', $sample, $matches);
        $matching_string = isset($matches[0]) ? $matches[0] : "";

        if($matching_string){
        
            $attributesString = $matches[1];
            $pattern = '/([\.\w-]+)="([^"]*)"/';
            if (preg_match_all($pattern, $attributesString, $innerMatches)) {
                $fieldValues = array_combine($innerMatches[1], $innerMatches[2]);
            }

            $field = null;
            $data_json = null;
            $data_model = null;
            $connector = null;
            $instance = null;
            $order = null;
            $order_by = null;
            $limit = null;

            foreach($fieldValues as $key=>$value){
                if(!$key || !trim($key)) continue;
                $key = strtolower(trim($key));
                if($key == 'field') $field = $value;
                else if($key == 'data-json') $data_json = $value;
                else if($key == 'data-model') $data_model = $value;
                else if($key == 'connector') $connector = $value;
                else if($key == 'instance') $instance = $value;
                else if($key == 'order') $order = $value;
                else if($key == 'order-by') $order_by = $value;
                else if($key == 'limit') $limit = $value;
            }
            
            $str = "";
            if( $field ){

                if($instance){
                    $str = static::getDataByInstance($target_name, $traget_id, $instance, $field, $order, $order_by);
                }
                else if($data_model){
                    $str = \DB::select( " SELECT  fnGetDataTextValue(?,?,?,?) as val",[ $target_name, $traget_id, $data_model, $field ] )[0]->val;
                }
                else if($data_json){ 
                    $json = json_decode( $account->{$data_json} ?? "{}", TRUE);

                    if($json){
                       $str = $json[$field] ?? "";
                    }

                }
                else{
                    $field = str_replace('-','_', $field);
                    $str = $account->{$field} ?? ""; 
                }

            } 
            if($connector && trim($connector)){
                $str = str_replace(' ', $connector, $str);
            }

            if($limit && is_numeric($limit) && $str){
                $str = substr($str, 0, $limit);
            }

            $result = str_replace($matching_string, $str, $sample);

            //recheck if still exists.
            return static::account($result, $account, $target_name, $traget_id);
        }
        return $template_str;
    }

    static function device_account_id($template_str, $target_name, $traget_id, DeviceAccess $device_access=null){
        
        $sample = $template_str;

        // Define the regular expression pattern
        //$pattern = '/{{\s*(phb-event-start)\s*(?:format\s*=\s*"([^"]*)"\s*)?(?:timezone\s*=\s*"([^"]*)"\s*)?(?:offset_mins\s*=\s*([^"]*)\s*)*}}/';
        $pattern = '/\[\s*(device_account_id)\s*\]/';
        //$pattern = '/\[account_name format="[^"]+"\]/';
        preg_match($pattern, $sample, $matches);
        $matching_string = isset($matches[0]) ? $matches[0] : "";
        if($matching_string){

            $deviceAccount = DeviceAccount::where([
                "target_name"=>$target_name,
                "target_id"=>$traget_id
            ])->first();

            //$field = isset( $matches[2]) ?  $matches[2]:null;
            //$data_json = isset( $matches[3]) ?  $matches[3]:null;
            //$offset_mins = isset( $matches[4]) ?  $matches[4]:0; 
            //$str = static::event_time_setup($event->utc_start, $format, $timezone, $offset_mins); 
            $str = "0";
            if( $deviceAccount ){
                $str = $deviceAccount->account_id;
            }
            $result = str_replace($matching_string, $str, $sample);

            //recheck if still exists.
            return static::device_account_id($result, $target_name, $traget_id);
        }
        return $template_str;

    }

    //fn should return string values
    static function find($template_str, callable $fn=null ){

        // 1. Verify the string starts with [find and ends with ]
        // We capture everything between them in a group.
        $mainLine = "";
        $fieldValues = [];
        $lineSplit = array_filter(explode(' ',$template_str));
        if( count($lineSplit) > 0){
            $mainLine = $lineSplit[0];
        }
        // 2. Extract the content inside [find ...]
        if (preg_match('/\[find\s+([^\]]+)\]/', $template_str, $outerMatches)) {
            $attributesString = $outerMatches[1];
            // 3. Extract key="value" pairs
            // Added \. to the character class to support keys like .id
            
            //OLD:
            $pattern = '/([a-zA-Z0-9._-]+)\s*(~=|!=|>=|<=|=|~)\s*"([^"]*)"/';
            preg_match_all($pattern, $attributesString, $matches);
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                $value = $matches[3][$i];
                $operator =  $matches[2][$i];
                $key = $matches[1][$i];
                if($operator == '~')
                    $value = "/$value/";
                $fieldValues[] = [
                    'key' => $key,
                    'operator' => $operator,
                    'value' => $value,
                ];

            }

        }else{
            return $template_str;
        }
        
        if($outerMatches && count($outerMatches)<=0){
            return $template_str;
        }
        //return $outerMatches[0];
        $replaceValue = '.id="**find-return-value**"';
        if(is_callable($fn)){

            //REPLACE the last
            $printPattern = '#/([^/\s]+)(/?)(?=\s|$)#';
            $printLine = preg_replace($printPattern, '/print$2', $mainLine, 1);
            $replaceValue = $fn($printLine, $fieldValues);
        }
        
        $template_str = preg_replace('/' . preg_quote($outerMatches[0], '/') . '/', $replaceValue, $template_str, 1);
        //$template_str = str_replace( $outerMatches[0], $replaceValue, $template_str);
        //Recursive and find until everything is fixed.
        return static::find( $template_str, $fn);
    }

    static function multi_find($template_str, callable $fn = null){
        $lines = explode("\n", $template_str);
        $result = [];
        foreach($lines as $line){
            if(trim($line)){
                $result[] = static::find($line, $fn);
            }
        }
        return implode("\n", $result);
    }

    static function getModelByTable($tableName)
    {
        $modelsPath = app_path('Models');
        $files = File::allFiles($modelsPath);

        foreach ($files as $file) {
            $class = 'App\\Models\\' . $file->getFilenameWithoutExtension();

            if (class_exists($class)) {
                $model = new $class;

                if ($model->getTable() === $tableName) {
                    return $class;
                }
            }
        }

        return null;
    }

    static function getDataByInstance($target_name, $target_id, $instance, $field, $order=0, $orderBy=null){
        $model = static::getModelByTable($target_name);
        if(!$model) return '';

        $model = new $model;
        $instance = str_replace('-','_', $instance);
        
        $selected = $model->find( $target_id );
        if(!$selected) return '';

        if (!method_exists($selected, $instance)) {
            return '';
        }
        if($orderBy){
            $selected->load([
                $instance => function ($query)use($orderBy) {
                    $query->orderByRaw("$orderBy ASC");
                }
            ]);
        }
        else
            $selected->load($instance);
        $field = str_replace('-','_', $field);
        if(is_array($model->{$instance})){
            if(count($model->{$instance}) <= 0 ) return '';


            if(lower(trim($order) == 'last')){
                return collect($model->{$instance})->last()->{$field} ?? "";
            }
            else if(lower(trim($order) == 'first')){
                return ($model->{$instance})[$order]->{$field} ?? "";
            }
            else if(is_numeric($order)){
                return ($model->{$instance})[((int)$order)-1]->{$field} ?? "";
            }
            return '';
        }
        return $selected->{$instance}->{$field} ?? '';

    }

    static function stripCommentsOutsideQuotes($line) {
        $inQuote = false;
        $result = '';
        $length = strlen($line);

        for ($i = 0; $i < $length; $i++) {
            $char = $line[$i];
            $next = $i + 1 < $length ? $line[$i + 1] : '';

            // Toggle quote state (ignore escaped quotes)
            if ($char === '"' && ($i === 0 || $line[$i - 1] !== '\\')) {
                $inQuote = !$inQuote;
            }

            // Detect comments only if OUTSIDE quotes
            if (!$inQuote) {
                if ($char === '#' || ($char === '/' && $next === '/')) {
                    break;
                }
            }

            $result .= $char;
        }

        return trim($result);
    }

    static function getVariable($line) {
        $line = trim($line);

        // Skip full-line comments
        if (preg_match('/^\s*(#|\/\/)/', $line)) {
            return null;
        }

        $line = static::stripCommentsOutsideQuotes($line);

        // Capture variable name + value /SET\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*(?:"((?:\\\\.|[^"])*)"|(\S+))?/i
        //if (preg_match('/SET\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*(?:"((?:\\\\.|[^"])*)"|([^\s]*))?/i', $line, $matches)) {
        if (preg_match('/SET\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*(?:"((?:\\\\.|[^"])*)"|(\S+))?/i', $line, $matches)) {
            $matches = array_filter($matches,function($item)
                {return trim("$item")!="";
            });
            $name = $matches[1];

            if (isset($matches[2])) {
                $value = stripcslashes($matches[2]); // handles \" etc
            } elseif (isset($matches[3])) {
                $value = $matches[3];
            } else {
                $value = "";
            }
            //return $matches;
            return [
                'name' => $name,
                'value' => $value
            ];
        }

        return null;
    }

    static function modelHasColumn($model, string $column): bool
    {
        $instance = is_string($model)
            ? new $model
            : $model;

        return Schema::hasColumn(
            $instance->getTable(),
            $column
        );
    }

}