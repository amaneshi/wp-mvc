<?php

/**
 * Public framework API or convention-loaded MVC/WordPress entrypoint.
 * @api
 */
class MvcDataValidationError {

    private array $errors = [];
    
    /**
     * @param string|null $field
     * @param string|null $message
     */
    public function __construct($field=null, $message=null) {
        if ($field && $message) {
            $this->add_error($field, $message);
        }
    }
    
    /**
     * @param string $field
     * @param string $message
     */
    public function add_error($field, $message): void {
        $this->errors[] = [
            'field' => $field,
            'message' => $message
        ];
    }
    
    public function get_errors(): array {
        return $this->errors;
    }
    
    public function get_html(): false|string {
        if ($this->errors === []) {
            return false;
        }
        $html = '';
        foreach ($this->errors as $error) {
            $html .= '
                <div class="mvc-validation-error">
                    '.$error['message'].'
                </div>';
        }
        return $html;
    }

}

?>