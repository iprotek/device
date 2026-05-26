<?php
namespace iProtek\Device\Helpers;

use RouterOS\Client;
use RouterOS\Interfaces\ClientInterface;
use Illuminate\Support\Facades\Log;

class MClientHelper extends Client {

    public $expressions = [];
    public $sort=null;

    public function query($endpoint, ?array $where = null, ?string $operations = null, ?string $tag = null):ClientInterface
    {
        if ($endpoint instanceof MQueryHelper){
           $this->expressions = $endpoint->expressions; 
           $this->sort = $endpoint->sort; 
        }
        
        return parent::query($endpoint, $where, $operations, $tag);
    }

    
    public function read(bool $parse = true, array $options = []){
        $result = parent::read($parse, $options);

        $filtered_result = $result;
        if( !($this->expressions && count($this->expressions) > 0)){

        }
        else{
            //$filtered_result = $result;
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
        }

        if($this->sort && $filtered_result && count($filtered_result) > 0 ){
            if($this->detectArrayShape($filtered_result) == 'array_of_arrays' )
                $filtered_result =  $this->sortData($filtered_result, $this->sort );
        }
        
        return $filtered_result;
    }

    function resolveType($value, string $type)
    {
        if ($type !== 'auto') {
            return $type;
        }

        if (is_numeric($value)) {
            return 'number';
        }

        if (strtotime($value) !== false) {
            return 'date';
        }

        // RouterOS duration detection (very useful heuristic)
        if (is_string($value) && preg_match('/\d+[YMwdhms]/', $value)) {
            return 'routeros-duration';
        }

        return 'string';
    }

    private function normalizeValue($value, string $type)
    {
        return match ($type) {

            'routeros-duration' => $this->parseRouterOSDuration($value ?? ''),

            'number' => (float) $value,

            'date' => strtotime($value) ?: 0,

            default => (string) $value,
        };
    }

    function sortData(array $data, array $sort): array
    {
        $field = $sort['field'];
        $type  = $sort['type'] ?? 'auto';
        $order = $sort['order'] ?? 'asc';

        // PRE-NORMALIZE (this is the key fix)
        foreach ($data as &$row) {
            $value = $row[$field] ?? '';

            $resolvedType = $type;

            if ($resolvedType === 'auto') {
                $resolvedType = $this->resolveType($value, 'auto');
            }

            $row['_sort'] = $this->normalizeValue($value, $resolvedType);
        }
        unset($row);

        usort($data, function ($a, $b) use ($order) {
            $result = $a['_sort'] <=> $b['_sort'];
            return $order === 'desc' ? -$result : $result;
        });

        return $data;
    }
    function routeros_sort(array &$items, string $field, string $dir = 'asc')
    {
        usort($items, function ($a, $b) use ($field, $dir) {

            $valA = $a[$field] ?? '';
            $valB = $b[$field] ?? '';

            $result = is_numeric($valA) && is_numeric($valB)
                ? $valA <=> $valB
                : strcmp($valA, $valB);

            return $dir === 'desc'
                ? -$result
                : $result;
        });
    }

    function detectArrayShape($value): string
    {
        // not an array
        if (!is_array($value)) {
            return 'not_array';
        }

        // empty
        if ($value === []) {
            return 'empty_array';
        }

        // if first element is array → it's a list of arrays
        if (is_array($value[0] ?? null)) {
            return 'array_of_arrays';
        }

        // otherwise it's associative (single record)
        return 'associative_array';
    }

   private function parseRouterOSDuration(string $value): int
    {
        if ($value === '') return 0;

        static $units = [
            'Y' => 31536000, // year
            'M' => 2592000,  // month
            'w' => 604800,   // week (lowercase supported)
            'd' => 86400,
            'h' => 3600,
            'm' => 60,       // minute
            's' => 1,
        ];

        preg_match_all('/(\d+)([YMWwdhms])/', $value, $matches, PREG_SET_ORDER);

        $total = 0;

        foreach ($matches as $match) {
            $unit = $match[2];
            $num  = (int) $match[1];

            if (isset($units[$unit])) {
                $total += $num * $units[$unit];
            }
        }

        return $total;
    }
    
}