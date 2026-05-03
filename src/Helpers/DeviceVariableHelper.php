<?php
namespace iProtek\Device\Helpers;

use DB; 
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use iProtek\Device\Models\DeviceAccount;
use iProtek\Device\Models\DeviceAccess;
use Illuminate\Support\Facades\File;

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
        $pattern = '/\[\s*(account)\s*(?:field\s*=\s*"([^"]*)"\s*)?(?:data-json\s*=\s*"([^"]*)"\s*)?(?:data-model\s*=\s*"([^"]*)"\s*)?(?:connector\s*=\s*"([^"]*)"\s*)*\]/';
        //$pattern = '/\[account_name format="[^"]+"\]/';
        preg_match($pattern, $sample, $matches);
        $matching_string = isset($matches[0]) ? $matches[0] : "";
        if($matching_string){
            $field = isset( $matches[2]) ?  $matches[2]:null;
            $data_json = isset( $matches[3]) ?  $matches[3]:null;
            $data_model = isset( $matches[4]) ?  $matches[4]: null;
            $connector = isset( $matches[5]) ?  $matches[5]: null;
            //$str = static::event_time_setup($event->utc_start, $format, $timezone, $offset_mins);
            
            $str = "";
            if( $field ){

                if($data_model){
                    $str = \DB::select( " SELECT  fnGetDataTextValue(?,?,?,?) as val",[ $target_name, $traget_id, $data_model, $field ] )[0]->val;
                }
                else if($data_json){ 
                    $json = json_decode( $account->{$data_json} ?? "{}", TRUE);

                    if($json){
                       $str = $json[$field] ?? "";
                    }

                }
                else{
                    $str = $account->{$field} ?? ""; 
                }

            } 
            if($connector && trim($connector)){
                $str = str_replace(' ', $connector, $str);
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
            $pattern = '/([\.\w-]+)="([^"]*)"/';
            //OLD: $pattern = '/([\w-]+)="([^"]*)"/';
            
            if (preg_match_all($pattern, $attributesString, $innerMatches)) {
                // Combine the keys ($innerMatches[1]) and values ($innerMatches[2])
                $fieldValues = array_combine($innerMatches[1], $innerMatches[2]);
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
}