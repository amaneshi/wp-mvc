<?php

/**
 * Public framework API or convention-loaded MVC/WordPress entrypoint.
 * @api
 */
class MvcDatabase {

    private readonly wpdb $wpdb;
    private readonly bool $debug;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->debug = (bool) MvcConfiguration::get('Debug');
    }
    
    /**
     * @param string $string
     * @param 'OBJECT'|'OBJECT_K'|'ARRAY_A'|'ARRAY_N' $output_type
     */
    public function get_results($string, $output_type=OBJECT): ?array {
        $this->add_to_log($string);
        $rows = $this->wpdb->get_results($string, $output_type);
        return is_array($rows) ? $rows : null;
    }
    
    /**
     * @param string $string
     * @param int $column_offset
     * @param int $row_offset
     *
     * @return null|string
     */
    public function get_var($string, $column_offset=0, $row_offset=0) {
        $this->add_to_log($string);
        return $this->wpdb->get_var($string, $column_offset, $row_offset);
    }
    
    /**
     * @param string $string
     *
     * @return bool|int
     */
    public function query($string) {
        $this->add_to_log($string);
        return $this->wpdb->query($string);
    }
    
    /**
     * @return int
     */
    public function insert_id() {
        return $this->wpdb->insert_id;
    }
    
    /**
     * @param numeric|string $string
     */
    public function escape($string): string {
        $escaped = esc_sql((string) $string);
        if (!is_string($escaped)) {
            throw new UnexpectedValueException('Escaping a scalar SQL value must return a string.');
        }
        return $escaped;
    }
    
    public function escape_array(array $array): array {
        foreach ($array as $key => $value) {
            $array[$key] = $this->escape($value);
        }
        return $array;
    }
    
    private function add_to_log(string $string): void {
        if ($this->debug) {
            echo '<pre>'.$string.'</pre>';
        }
    }

}

?>
