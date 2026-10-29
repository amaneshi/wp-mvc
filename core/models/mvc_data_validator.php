<?php

/**
 * Public framework API or convention-loaded MVC/WordPress entrypoint.
 * @api
 */
class MvcDataValidator {

    private array $common_patterns = [
        'hostname' => '(?:[a-z0-9][-a-z0-9]*\.)*(?:[a-z0-9][-a-z0-9]{0,62})\.(?:(?:[a-z]{2}\.)?[a-z]{2,4}|museum|travel)'
    ];
    
    /**
     * @param string $field
     * @param mixed $value
     * @param string|array $rule
     */
    public function validate(string|int|null $field, $value, $rule): true|\MvcDataValidationError {
        if (is_string($rule)) {
            if (method_exists($this, $rule)) {
                $result = $this->apply_rule($rule, $value);
                if ($result === true) {
                    return true;
                }
                $message = $result;
                $message = $this->process_message($message, $field);
                return new MvcDataValidationError($field, $message);
            }
            MvcError::fatal(sprintf(__("The validation rule %s wasn't found.", 'wpmvc'), $rule));
        }
        if (!empty($rule['pattern']) || !empty($rule['rule'])) {
            return $this->validate_using_array_rule($field, $value, $rule);
        }
        MvcError::fatal(sprintf(__("The validation rule %s wasn't defined correctly.", 'wpmvc'), print_r($rule, true)));
    }
    
    /**
     * @param string $field
     * @param mixed $value
     */
    private function validate_using_array_rule(string|int|null $field, $value, array $rule): true|\MvcDataValidationError {
        $message = '';
        $valid = false;
        if (isset($rule['required']) && !$rule['required'] && empty($value)) {
            return true;
        }
        if (isset($rule['pattern'])) {
            $valid = preg_match($rule['pattern'], (string) $value);
        } elseif (isset($rule['rule'])) {
            if (method_exists($this, $rule['rule'])) {
                $result = $this->apply_rule($rule['rule'], $value);
                $valid = $result === true;
                $message = $result;
            }
        }
        if ($valid) {
            return true;
        }
        if (!empty($rule['message'])) {
            $message = $rule['message'];
        }
        $message = $this->process_message($message, $field);
        return new MvcDataValidationError($field, $message);
    }
    
    /**
     * @param mixed $value
     */
    private function matches_pattern($value, string $pattern): int|false {
        if ($pattern === '') {
            throw new InvalidArgumentException('A validation pattern cannot be empty.');
        }
        return preg_match($pattern, (string) $value);
    }

    private function apply_rule(string $rule, mixed $value): mixed {
        return match ($rule) {
            'alphanumeric' => $this->alphanumeric($value),
            'numeric' => $this->numeric($value),
            'email' => $this->email($value),
            'not_empty' => $this->not_empty($value),
            'url' => $this->url($value),
            default => $this->{$rule}($value),
        };
    }
    
    /**
     * @param string $message
     * @param string $field
     */
    private function process_message($message, string|int|null $field): string {
        $titleized_field = MvcInflector::titleize($field);
        return str_replace('{field}', $titleized_field, $message);
    }
    
    /**
     * @return string|true
     * @param mixed $value
     */
    private function alphanumeric($value) {
        $pattern = '/[\w]+/';
        if ($this->matches_pattern($value, $pattern)) {
            return true;
        }
        return __('{field} must only contain letters and numbers.', "wpmvc");
    }
    
    /**
     * @return string|true
     * @param mixed $value
     */
    private function numeric($value) {
        if (is_numeric($value)) {
            return true;
        }
        return __('{field} must be a number.', 'wpmvc');
    }
    
    /**
     * @return string|true
     * @param mixed $value
     */
    private function email($value) {
        $pattern = '/^[a-z0-9!#$%&\'*+\/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&\'*+\/=?^_`{|}~-]+)*@'.$this->common_patterns['hostname'].'$/i';
        if ($this->matches_pattern($value, $pattern)) {
            return true;
        }
        return __('{field} must be a valid email address.', 'wpmvc');
    }
    
    /**
     * @return string|true
     * @param mixed $value
     */
    private function not_empty($value) {
        $pattern = '/[^\s]+/';
        if ($this->matches_pattern($value, $pattern)) {
            return true;
        }
        return __("{field} can't be empty.", 'wpmvc');
    }
    
    /**
     * @return string|true
     * @param mixed $value
     */
    private function url($value) {
        $this->populate_ip_patterns();
        $valid_chars = '(['.preg_quote('!"$&\'()*+,-.@_:;=~').'\/0-9a-z]|(%[0-9a-f]{2}))';
        $pattern = '/^(?:(?:https?|ftps?|file|news|gopher):\/\/)?'.
            '(?:'.$this->common_patterns['ipv4'].'|\['.$this->common_patterns['ipv6'].'\]|'.$this->common_patterns['hostname'].')'.
            '(?::[1-9][0-9]{0,4})?'.
            '(?:\/?|\/'.$valid_chars.'*)?'.
            '(?:\?'.$valid_chars.'*)?'.
            '(?:#'.$valid_chars.'*)?$/i';
        if ($this->matches_pattern($value, $pattern)) {
            return true;
        }
        return __('{field} must be a valid URL.', 'wpmvc');
    }
    
    private function populate_ip_patterns(): void {
        if (!isset($this->common_patterns['ipv6'])) {
            $pattern = '((([0-9A-Fa-f]{1,4}:){7}(([0-9A-Fa-f]{1,4})|:))|(([0-9A-Fa-f]{1,4}:){6}'.
            '(:|((25[0-5]|2[0-4]\d|[01]?\d{1,2})(\.(25[0-5]|2[0-4]\d|[01]?\d{1,2})){3})'.
            '|(:[0-9A-Fa-f]{1,4})))|(([0-9A-Fa-f]{1,4}:){5}((:((25[0-5]|2[0-4]\d|[01]?\d{1,2})'.
            '(\.(25[0-5]|2[0-4]\d|[01]?\d{1,2})){3})?)|((:[0-9A-Fa-f]{1,4}){1,2})))|(([0-9A-Fa-f]{1,4}:)'.
            '{4}(:[0-9A-Fa-f]{1,4}){0,1}((:((25[0-5]|2[0-4]\d|[01]?\d{1,2})(\.(25[0-5]|2[0-4]\d|[01]?\d{1,2}))'.
            '{3})?)|((:[0-9A-Fa-f]{1,4}){1,2})))|(([0-9A-Fa-f]{1,4}:){3}(:[0-9A-Fa-f]{1,4}){0,2}'.
            '((:((25[0-5]|2[0-4]\d|[01]?\d{1,2})(\.(25[0-5]|2[0-4]\d|[01]?\d{1,2})){3})?)|'.
            '((:[0-9A-Fa-f]{1,4}){1,2})))|(([0-9A-Fa-f]{1,4}:){2}(:[0-9A-Fa-f]{1,4}){0,3}'.
            '((:((25[0-5]|2[0-4]\d|[01]?\d{1,2})(\.(25[0-5]|2[0-4]\d|[01]?\d{1,2}))'.
            '{3})?)|((:[0-9A-Fa-f]{1,4}){1,2})))|(([0-9A-Fa-f]{1,4}:)(:[0-9A-Fa-f]{1,4})'.
            '{0,4}((:((25[0-5]|2[0-4]\d|[01]?\d{1,2})(\.(25[0-5]|2[0-4]\d|[01]?\d{1,2})){3})?)'.
            '|((:[0-9A-Fa-f]{1,4}){1,2})))|(:(:[0-9A-Fa-f]{1,4}){0,5}((:((25[0-5]|2[0-4]'.
            '\d|[01]?\d{1,2})(\.(25[0-5]|2[0-4]\d|[01]?\d{1,2})){3})?)|((:[0-9A-Fa-f]{1,4})'.
            '{1,2})))|(((25[0-5]|2[0-4]\d|[01]?\d{1,2})(\.(25[0-5]|2[0-4]\d|[01]?\d{1,2})){3})))(%.+)?';
            $this->common_patterns['ipv6'] = $pattern;
        }
        if (!isset($this->common_patterns['ipv4'])) {
            $pattern = '(?:(?:25[0-5]|2[0-4]\d|(?:(?:1\d)?|[1-9]?)\d)\.){3}(?:25[0-5]|2[0-4]\d|(?:(?:1\d)?|[1-9]?)\d)';
            $this->common_patterns['ipv4'] = $pattern;
        }
    }

}

?>
