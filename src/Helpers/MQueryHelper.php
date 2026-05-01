<?php
namespace iProtek\Device\Helpers;

use RouterOS\Query;

class MQueryHelper extends Query {
    // We override the where method to bypass the internal "allowed list" check
    public $expressions = [];
    public function where($key, $operator = null, $value = null): self
    {
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