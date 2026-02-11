<?php

namespace App\Traits;

trait WithValidate
{
    protected array $rules = [];
    protected array $errors = [];

    public function validate(array $data): bool
    {
        $this->errors = [];
        
        foreach ($this->rules as $field => $rules) {
            $rulesArray = is_string($rules) ? explode('|', $rules) : $rules;
            
            foreach ($rulesArray as $rule) {
                if (!$this->validateRule($field, $data[$field] ?? null, $rule)) {
                    break;
                }
            }
        }
        
        return empty($this->errors);
    }

    private function validateRule(string $field, $value, string $rule): bool
    {
        if ($rule === 'required') {
            if (empty($value) && $value !== '0' && $value !== 0) {
                $this->errors[$field] = ucfirst($field) . ' is required';
                return false;
            }
        }
        
        if (str_starts_with($rule, 'min:')) {
            $min = (int) substr($rule, 4);
            if (strlen($value ?? '') < $min) {
                $this->errors[$field] = ucfirst($field) . " must be at least {$min} characters";
                return false;
            }
        }
        
        if (str_starts_with($rule, 'max:')) {
            $max = (int) substr($rule, 4);
            if (strlen($value ?? '') > $max) {
                $this->errors[$field] = ucfirst($field) . " must not exceed {$max} characters";
                return false;
            }
        }
        
        if ($rule === 'integer') {
            if (!is_numeric($value) || (int)$value != $value) {
                $this->errors[$field] = ucfirst($field) . ' must be an integer';
                return false;
            }
        }
        
        if ($rule === 'array') {
            if (!is_array($value)) {
                $this->errors[$field] = ucfirst($field) . ' must be an array';
                return false;
            }
        }
        
        return true;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
