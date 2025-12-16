<?php

namespace App\Utils;

/**
 * Validator Class
 * 
 * Handles input validation
 */
class Validator
{
    private $data;
    private $rules;
    private $errors = [];
    
    public function __construct($data, $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
    }
    
    /**
     * Validate data against rules
     */
    public function validate()
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = explode('|', $ruleString);
            
            foreach ($rules as $rule) {
                $this->applyRule($field, $rule);
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * Apply a validation rule
     */
    private function applyRule($field, $rule)
    {
        $value = $this->data[$field] ?? null;
        
        // Required rule
        if ($rule === 'required') {
            if (empty($value) && $value !== '0' && $value !== 0) {
                $this->addError($field, ucfirst($field) . ' is required');
            }
        }
        
        // Email rule
        if ($rule === 'email') {
            if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $this->addError($field, ucfirst($field) . ' must be a valid email address');
            }
        }
        
        // Min length rule
        if (strpos($rule, 'min:') === 0) {
            $minLength = (int)substr($rule, 4);
            if (!empty($value) && strlen($value) < $minLength) {
                $this->addError($field, ucfirst($field) . " must be at least {$minLength} characters");
            }
        }
        
        // Max length rule
        if (strpos($rule, 'max:') === 0) {
            $maxLength = (int)substr($rule, 4);
            if (!empty($value) && strlen($value) > $maxLength) {
                $this->addError($field, ucfirst($field) . " must not exceed {$maxLength} characters");
            }
        }
        
        // Numeric rule
        if ($rule === 'numeric') {
            if (!empty($value) && !is_numeric($value)) {
                $this->addError($field, ucfirst($field) . ' must be a number');
            }
        }
        
        // URL rule
        if ($rule === 'url') {
            if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                $this->addError($field, ucfirst($field) . ' must be a valid URL');
            }
        }
    }
    
    /**
     * Add an error
     */
    private function addError($field, $message)
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }
    
    /**
     * Get all errors
     */
    public function errors()
    {
        return $this->errors;
    }
}
?>
