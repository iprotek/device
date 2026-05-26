<?php
namespace iProtek\Device\Helpers;

use RouterOS\Query;
use Illuminate\Support\Facades\Log;

class MQueryHelper extends Query {
    // We override the where method to bypass the internal "allowed list" check
    public $expressions = [];
    public $sort = null;
    function parseOrderField(string $value): array
    {
        $direction = 'asc';
        
        $input = trim($value);

        if (str_starts_with($input, '-')) {
            $direction = 'desc';
            $input = substr($input, 1);
        }

        [$field, $type] = array_pad(explode('|', $input, 2), 2, null);

        return [
            'field' => $field,
            'type' => $type ?? 'auto',
            'order' => $direction,
        ];
    }
    public function where($key, $operator = null, $value = null): self
    {
        
        if($key == 'order-by'){
            $this->sort = $this->parseOrderField($operator);
            return $this;
        }
        // We manually add the attribute without the library's validation
        // RouterOS API uses ~ for regex matches
        $operator = $operator ? trim($operator) : null;
        if ($operator === 'regexp' || $operator === '~') {
            //$this->attributes[] = "?~{$key}={$value}";
            //$this->attributes[] = "?~$key=$value";
            //$this->add();
            $this->expressions[] = [
                "key"=>$key,
                "operator"=>'~',
                "pattern"=>$value
            ];
            return $this;
        }

        // Otherwise, use the parent behavior for standard stuff (=, <, >)
        return parent::where($key, $operator, $value);
    }
 
}