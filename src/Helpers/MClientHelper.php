<?php
namespace iProtek\Device\Helpers;

use RouterOS\Client;
use RouterOS\Interfaces\ClientInterface;
use Illuminate\Support\Facades\Log;

class MClientHelper extends Client {

    public $expressions = [];

    public function query($endpoint, ?array $where = null, ?string $operations = null, ?string $tag = null):ClientInterface
    {
        if ($endpoint instanceof MQueryHelper)
           $this->expressions = $endpoint->expressions;
        
        return parent::query($endpoint, $where, $operations, $tag);
    }

    
    public function read(bool $parse = true, array $options = []){
        $result = parent::read($parse, $options);

        if( !($this->expressions && count($this->expressions) > 0)){
            return $result;
        }
        $filtered_result = $result;
        foreach($this->expressions as $expr ){
            if($expr['operator'] === '~' ){
                $filtered_result = array_values( array_filter($filtered_result, function($item)use($expr){
                    try{
                        if(!isset($item[$expr['key']]) ){
                            return false;
                        }
                        $pattern = $expr['pattern'];
                        $value = $item[$expr['key']];
                        if (preg_match($pattern, $value)) {
                            return true;
                        }
                    }catch(\Exception $ex){
                        Log::error($ex->getMessage());
                    }
                    return false;
                }) );
            }
        }
        return $filtered_result;
        //return parent::read($parse, $options);
    }
    
}