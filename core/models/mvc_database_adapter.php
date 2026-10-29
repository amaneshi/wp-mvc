<?php

#[\AllowDynamicProperties]
class MvcDatabaseAdapter {

    /**
     * @var \MvcDatabase
     */
    public $db;
    public array $defaults = [];
    public ?array $schema = null;

    function __construct() {
        $this->db = new MvcDatabase();
    }
    
    /**
     * @param numeric|string $value
     */
    public function escape($value): string {
        return $this->db->escape($value);
    }
    
    /**
     * @param (array|int|mixed|null|string)[] $defaults
     *
     */
    public function set_defaults(array $defaults): void {
        $this->defaults = $defaults;
    }
    
    /**
     * @param string $sql
     */
    public function query($sql) : int|bool {
        return $this->db->query($sql);
    }
    
    public function get_results(array|string $options_or_sql): ?array {
        if (is_array($options_or_sql)) {
            $clauses = $this->get_sql_select_clauses($options_or_sql);
            $sql = implode(' ', $clauses);
        } else {
            $sql = $options_or_sql;
        }
        return $this->db->get_results($sql);
    }
    
    /**
     * @param string $sql
     */
    public function get_var($sql) : ?string {
        return $this->db->get_var($sql);
    }
    
    public function get_table_reference_sql(array $options=[]): string {
        $table_reference = empty($options['table_reference']) ? $this->defaults['table_reference'] : $options['table_reference'];
        $table_alias = $options['table_alias'] ?? ' `' . $this->defaults['model_name'] . '`';
        return $table_reference.$table_alias;
    }
    
    public function get_sql_select_clauses(array $options=[]): array {
        return [
            'select' => 'SELECT '.$this->get_select_sql($options),
            'from' => 'FROM '.$this->get_table_reference_sql($options),
            'joins' => $this->get_joins_sql($options),
            'where' => $this->get_where_sql($options),
            'group' => $this->get_group_sql($options),
            'having' => $this->get_having_sql($options),
            'order' => $this->get_order_sql($options),
            'limit' => $this->get_limit_sql($options),
        ];
    }
    
    public function get_select_sql(array $options=[]): string {
        $selects = empty($options['selects']) ? $this->defaults['selects'] : $options['selects'];
        if (!empty($options['additional_selects'])) {
            $selects = array_merge($this->defaults['selects'], $options['additional_selects']);
        }
        return implode(', ', $selects);
    }
    
    public function get_joins_sql(array $options=[]): string {
        $joins = empty($options['joins']) ? $this->defaults['joins'] : $options['joins'];
        if (empty($joins)) {
            return '';
        }
        $clauses = [];
        if (isset($joins['table'])) {
            $joins = [$joins];
        }
        foreach ($joins as $join) {
            $type = empty($join['type']) ? 'JOIN' : $join['type'];
            $clauses[] = $type.' '.$join['table'].' '.$join['alias'].' ON '.$join['on'];
        }
        return implode(' ', $clauses);
    }
    
    public function get_where_sql(array $options=[]): string {
        $conditions = empty($options['conditions']) ? $this->defaults['conditions'] : $options['conditions'];
        if (empty($conditions)) {
            return '';
        }
        if (is_array($conditions)) {
            $sql_clauses = $this->get_where_sql_clauses($conditions, $options);
            return 'WHERE '.implode(' AND ', $sql_clauses);
        }
        return 'WHERE '.$conditions;
    }
    
    public function get_group_sql(array $options=[]): string {
        if (empty($options['group'])) {
            return '';
        }
        return 'GROUP BY '.$options['group'];
    }

    public function get_having_sql(array $options=[]): string {
        if (empty($options['having'])) {
            return '';
        }
        return 'HAVING '.$options['having'];
    }
    
    /**
     * @return list<string>
     *
     * @param array $conditions
     */
    public function get_where_sql_clauses($conditions, array $options=[]): array {
        $use_table_alias = $options['use_table_alias'] ?? true;
        $sql_clauses = [];
        foreach ($conditions as $key => $value) {
            if (is_array($value)) {
                if (is_string($key) && !in_array($key, ['OR', 'AND'])) {
                    $values = [];
                    foreach ($value as $val) {
                        $values[] = '"'.$this->escape($val).'"';
                    }
                    $values = implode(',', $values);
                    $sql_clauses[] = $this->escape($key).' IN ('.$values.')';
                } else {
                    $clauses = $this->get_where_sql_clauses($value, $options);
                    $logical_operator = $key === 'OR' ? ' OR ' : ' AND ';
                    $sql_clauses[] = '('.implode($logical_operator, $clauses).')';
                }
                continue;
            }
            if (!str_contains((string) $key, '.') && $use_table_alias) {
                $key = $this->defaults['model_name'].'.'.$key;
            }

            $operator = preg_match('/\s+(<|>|<=|>=|<>|!=|IS|[\w\s]+)/', (string) $key) ? ' ' : ' = ';
            
            $functions = ['NULL', 'NOW()', 'CURDATE()', 'CURTIME()', 'CURRENT_TIMESTAMP()'];
            
            if(in_array($value, $functions) || is_null($value)){
                if((is_null($value) || $value === 'NULL')){
                    $is_not_operator = preg_match('/(<>|!=)/', (string) $key);
                    if(!$is_not_operator){
                        $operator = " IS ";
                    }
                    else {
                        $key = preg_replace('/\s+(<>|!=)/', '', (string) $key);
                        $operator = " IS NOT ";
                    }
                    $value = "NULL";
                }
                $sql_clauses[] = $this->escape($key).$operator.$value;
            }
            else{
                //TODO: Temp comment to remove escape from search condition inside CONCAT_WS()
                $sql_clauses[] = /*$this->escape($key)*/ $key .$operator.'"'.$this->escape($value).'"';
            }
        }
        return $sql_clauses;
    }
    
    public function get_order_sql(array $options=[]): string {
        $order = empty($options['order']) ? $this->defaults['order'] : $options['order'];
        return $order ? 'ORDER BY '.$this->escape($order) : '';
    }
    
    public function get_limit_sql(array $options=[]): string {
        if (!empty($options['page'])) {
            $per_page = empty($options['per_page']) ? $this->defaults['per_page'] : $options['per_page'];
            $page = $options['page'];
            $offset = ($page - 1) * $per_page;
            return 'LIMIT '.$this->escape($offset).', '.$this->escape($per_page);
        }
        $limit = empty($options['limit']) ? $this->defaults['limit'] : $options['limit'];
        return $limit ? 'LIMIT '.$this->escape($limit) : '';
    }
    
    /**
     * @param mixed $data
     */
    public function get_set_sql($data): string {
        $clauses = [];
        foreach ($data as $key => $value) {
            if ($value === null) {
                $clauses[] = $key.' = NULL';
            } elseif ($value === false) {
                $clauses[] = $key.' = FALSE';
            } elseif ($value === true) {
                $clauses[] = $key.' = TRUE';
            } elseif (is_string($value) || is_numeric($value)) {
                $clauses[] = $key.' = "' . $this->escape($value) . '"';
            }
        }
        return implode(', ', $clauses);
    }
    
    /**
     * @param mixed $data
     */
    public function get_insert_columns_sql($data): string {
        $columns = array_keys($data);
        $columns = $this->db->escape_array($columns);
        return '('.implode(', ', $columns).')';
    }
    
    /**
     * @param mixed $data
     */
    public function get_insert_values_sql($data): string {
        $values = [];
        foreach ($data as $value) {
            if ($value === null) {
                $values[] = 'NULL';
            } elseif ($value === false) {
                $values[] = 'FALSE';
            } elseif ($value === true) {
                $values[] = 'TRUE';
            } else {
                $values[] = '"'.$this->escape($value).'"';
            }
        }
        return '('.implode(', ', $values).')';
    }
    
    /**
     * @param array $data
     */
    public function insert($data, array $options=[]) : int {
        $options['table_alias'] = false;
        $options['use_table_alias'] = false;
        if (empty($options['table_reference'])) {
            // Filter out any data with a key that doesn't correspond to a column name in the table
        $data = array_intersect_key($data, $this->schema ?? []);
        }
        $clauses = [
            'insert' => 'INSERT INTO '.$this->get_table_reference_sql($options),
            'insert_columns' => $this->get_insert_columns_sql($data),
            'insert_values' => 'VALUES '.$this->get_insert_values_sql($data)
        ];
        $sql = implode(' ', $clauses);
        $this->query($sql);
        return $this->db->insert_id();
    }
    
    /**
     * @param array[] $options
     *
     * @param mixed $data
     */
    public function update_all($data, array $options=[]): void {
        $clauses = [
            'update' => 'UPDATE '.$this->get_table_reference_sql($options),
            'set' => 'SET '.$this->get_set_sql($data),
            'where' => $this->get_where_sql($options),
            'limit' => $this->get_limit_sql($options)
        ];
        $sql = implode(' ', $clauses);
        $this->query($sql);
    }
    
    public function delete_all(array $options): void {
        $options['table_alias'] = false;
        $options['use_table_alias'] = false;
        $clauses = [
            'update' => 'DELETE FROM '.$this->get_table_reference_sql($options),
            'where' => $this->get_where_sql($options),
            'limit' => $this->get_limit_sql($options)
        ];
        $sql = implode(' ', $clauses);
        $this->query($sql);
    }

}

?>
