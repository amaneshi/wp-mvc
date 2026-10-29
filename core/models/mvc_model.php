<?php

/**
 * @psalm-consistent-constructor
 * @method MvcModelObject|null find_one_by_post_id(mixed $post_id)
 *
 * Public framework API or convention-loaded MVC/WordPress entrypoint.
 * @api
 */
#[\AllowDynamicProperties]
class MvcModel {

    public int|string|null $insert_id = null;
    public ?array $invalid_data = null;
    public ?string $display_field = null;
    public string $name = '';
    public string $table = '';
    public ?string $database = null;

    public string $primary_key = 'id';
    public ?array $belongs_to = null;
    public ?array $has_many = null;
    public ?array $has_and_belongs_to_many = null;
    public ?array $custom_joins = null;

    public ?array $associations = null;

    public ?array $properties = null;
    public MvcDataValidationError|array|null $validation_error = null;
    public string $validation_error_html = '';

    /**
     * @var string
     *
     */
    public $validation_mode = 'single';

    public ?array $schema = null;
    public bool|array|null $wp_post = null;
    public string $model_name = '';
    public string $table_reference = '';
    public array|string|null $selects = null;
    public array|string|null $order = null;
    public ?array $joins = null;
    public array|string|null $conditions = null;
    public int|string|null $limit = null;
    public array|string|null $includes = null;
    public array|string|null $group = null;
    public array|string|null $having = null;

    public int $per_page = 10;
    public ?array $validate = null;
    private readonly \MvcDataValidator $data_validator;
    protected \MvcDatabaseAdapter $db_adapter;
    private ?\MvcPostAdapter $wp_post_adapter = null;
    protected static array $describe_cache = [];

    public function __construct() {
        
        global $wpdb;
        
        $this->name = (string) preg_replace('/Model$/', '', static::class);
        
        $this->check_for_obsolete_functionality();
        
        $table = empty($this->table) ? $wpdb->prefix.MvcInflector::tableize($this->name) : self::process_table_name($this->table);
        
        $defaults = [
            'model_name' => $this->name,
            'table' => $table,
            'table_reference' => empty($this->database) ? '`'.$table.'`' : '`'.$this->database.'`.`'.$table.'`',
            'selects' => empty($this->selects) ? ['`'.$this->name.'`.*'] : $this->selects,
            'order' => empty($this->order) ? null : $this->order,
            'joins' => empty($this->joins) ? null : $this->joins,
            'conditions' => empty($this->conditions) ? null : $this->conditions,
            'limit' => empty($this->limit) ? null : $this->limit,
            'includes' => empty($this->includes) ? null : $this->includes,
            'group' => empty($this->group) ? null : $this->group,
            'having' => empty($this->having) ? null : $this->having,
            'per_page' => empty($this->per_page) ? 10 : $this->per_page,
            'validate' => empty($this->validate) ? null : $this->validate
        ];
        
        foreach ($defaults as $key => $value) {
            $this->{$key} = $value;
        }
        
        $this->db_adapter = new MvcDatabaseAdapter();
        $this->db_adapter->set_defaults($defaults);
        
        $this->data_validator = new MvcDataValidator();
        
        $this->init_schema();
        
        if ($this->wp_post) {
            $this->wp_post_adapter = new MvcPostAdapter();
            $this->wp_post_adapter->verify_settings($this);
            if (empty($this->belongs_to)) {
                $this->belongs_to = [];
            }
            $association = [
                'Post' => [
                    'class' => 'MvcPost'
                ]
            ];
            $this->belongs_to = array_merge($association, $this->belongs_to);
        }
        
        $this->init_associations();
        $this->init_properties();
    
    }
    
    public function new_object(array $data): ?MvcModelObject {
        $object = new MvcModelObject($this);
        foreach ($data as $field => $value) {
            $object->$field = $value;
        }
        return $this->process_single_object($object);
    }
    
    /**
     * @param (mixed|null|string)[] $data
     *
     */
    public function create($data) : int|false {
        $data = $this->object_to_array($data);
        if (empty($data[$this->name])) {
            $data = [$this->name => $data];
        }
        $model_data = $data[$this->name];
        if (method_exists($this, 'before_save') && !$this->before_save($model_data)) {
            return false;
        }
        $valid = $this->validate_data($model_data);
        if ($valid !== true) {
            return false;
        }
        $id = $this->insert($model_data);
        $this->update_associations($id, $model_data);
        if (method_exists($this, 'after_create') || method_exists($this, 'after_save')) {
            $object = $this->find_by_id($id);
            if (method_exists($this, 'after_create')) {
                $this->after_create($object);
            }
            if (method_exists($this, 'after_save')) {
                $this->after_save($object);
            }
        }
        return $id;
    }
    
    /**
     * @param mixed $data
     */
    public function save($data): bool {
        $data = $this->object_to_array($data);
        if (empty($data[$this->name])) {
            $data = [$this->name => $data];
        }
        if (!empty($data[$this->name][$this->primary_key])) {
            $model_data = $data[$this->name];
            $id = $model_data[$this->primary_key];
            unset($model_data[$this->primary_key]);
            $valid = $this->validate_data($model_data);
            if ($valid !== true) {
                return false;
            }
            if (method_exists($this, 'before_save') && !$this->before_save($model_data)) {
                return false;
            }
            $this->update($id, $model_data);
            $this->update_associations($id, $model_data);
        } elseif (!$id = $this->create($data)) {
            return false;
        }
        if (method_exists($this, 'after_save')) {
            $object = $this->find_by_id($id);
            $this->after_save($object);
        }
        return true;
    }
    
    public function insert(array $data) : int {
        $insert_id = $this->db_adapter->insert($data);
        $this->insert_id = $insert_id;
        if ($this->has_post()) {
            $data[$this->primary_key] = $insert_id;
            $this->save_post($data);
        }
        return $insert_id;
    }
    
    /**
     * @param array $data
     * @param int|string $id
     */
    public function update(int|string|null $id, $data, array $update_options=[]): void {
        $options = [
            'conditions' => [$this->name.'.'.$this->primary_key => $id]
        ];
        $this->db_adapter->update_all($data, $options);
        if ($this->has_post() && (!isset($update_options['bypass_save_post']) || !$update_options['bypass_save_post'])) {
            $object = $this->find_by_id($id);
            if ($object instanceof \MvcModelObject) {
                $this->save_post($object);
            }
        }
    }
    
    /**
     * @param mixed $data
     */
    public function update_all($data, array $options=[], array $update_options=[]): void {
        $this->db_adapter->update_all($data, $options);
        if ($this->has_post() && (!isset($update_options['bypass_save_post']) || !$update_options['bypass_save_post'])) {
            $objects = $this->find($options);
            foreach ($objects ?? [] as $object) {
                if ($object !== null) {
                    $this->save_post($object);
                }
            }
        }
    }
    
    /**
     * @param int|string $id
     */
    public function delete($id): void {
        $options = [
            'conditions' => [$this->primary_key => $id]
        ];
        $this->delete_all($options);
    }
    
    /**
     * @param array $options
     *
     */
    public function delete_all($options=[]): void {
        $has_post = $this->has_post();
        $before_delete_method_exists = method_exists($this, 'before_delete');
        $objects = null;
        if ($has_post || $before_delete_method_exists) {
            $objects = $this->find($options);
            foreach ($objects ?? [] as $object) {
                if ($has_post) {
                    wp_delete_post($object->post_id, true);
                }
                if ($before_delete_method_exists) {
                    $this->{'before_delete'}($object);
                }
            }
        }
        $this->delete_dependent_associations($objects, $options);
        $this->db_adapter->delete_all($options);
    }
    
    /**
     * @return void
     * @param array|object|null $objects
     * @param array $options
     */
    protected function delete_dependent_associations($objects, $options=[]) {
        if (empty($objects)) {
            $objects = $this->find($options);
        }
        if (!empty($objects)) {
            foreach ($this->associations ?? [] as $association) {
                if ($association['dependent'] && $association['type'] == 'has_many') {
                    $model = MvcModelRegistry::require_model($association['class']);
                    foreach ($objects as $object) {
                        $options = [
                            'conditions' => [$association['foreign_key'] => $object->{$this->primary_key}]
                        ];
                        $model->delete_all($options);
                    }
                }
            }
        }
    }
    
    /**
     * @param array|object $object
     */
    public function save_post($object): void {
        if (!$this->wp_post_adapter instanceof \MvcPostAdapter) {
            throw new LogicException('WordPress post integration is not configured for ' . static::class);
        }
        $this->wp_post_adapter->save_post($this, $object);
    }
    
    public function has_post(): bool {
        return (bool) $this->wp_post_adapter;
    }
    
    public function find(array $options=[]): ?array {
        $options = $this->process_find_options($options);
        $objects = $this->db_adapter->get_results($options);
        $processed = $this->process_objects($objects, $options);
        if ($processed !== null && !is_array($processed)) {
            throw new LogicException('Expected a collection of model records');
        }
        return $processed;
    }
    
    public function find_one(array $options=[]): ?MvcModelObject {
        $options['limit'] = 1;
        $options = $this->process_find_options($options);
        $object = $this->db_adapter->get_results($options);
        $object = $object[0] ?? null;
        return $this->process_single_object($object, $options);
    }
    
    /**
     * @param int|string $id
     */
    public function find_by_id(int|string|null $id, array $options=[]): ?MvcModelObject {
        $options = $this->process_find_options($options);
        $options['conditions'] = [$this->name.'.'.$this->primary_key => $id];
        $object = $this->db_adapter->get_results($options);
        $object = $object[0] ?? null;
        return $this->process_single_object($object, $options);
    }

    private function process_single_object(?object $object, array $options=[]): ?MvcModelObject {
        $processed = $this->process_objects($object, $options);
        if ($processed !== null && !$processed instanceof MvcModelObject) {
            throw new LogicException('Expected a model record');
        }
        return $processed;
    }
    
    /**
     * @param array $options
     */
    public function paginate($options=[]): array {
        $options = $this->process_find_options($options);
        $options['page'] = empty($options['page']) ? 1 : intval($options['page']);
        $options['per_page'] = empty($options['per_page']) ? $this->per_page : intval($options['per_page']);
        $objects = $this->db_adapter->get_results($options);
        $objects = $this->process_objects($objects, $options);
        $total_count = $this->get_total_count($options);
        return [
            'objects' => $objects,
            'total_objects' => $total_count,
            'total_pages' => ceil((int) $total_count/$options['per_page']),
            'page' => $options['page']
        ];
    }
    
    public function count(array $options=[]) : ?string {
        $clauses = $this->db_adapter->get_sql_select_clauses($options);
        $clauses['select'] = 'SELECT COUNT(*)';
        $sql = implode(' ', $clauses);
        return $this->db_adapter->get_var($sql);
    }
    
    /**
     * @param int|string $column
     */
    public function max($column, array $options=[]) : ?string {
        $clauses = $this->db_adapter->get_sql_select_clauses($options);
        $clauses['select'] = 'SELECT MAX('.$this->db_adapter->escape($column).')';
        $sql = implode(' ', $clauses);
        return $this->db_adapter->get_var($sql);
    }
    
    /**
     * @param int|string $column
     */
    public function min($column, array $options=[]) : ?string {
        $clauses = $this->db_adapter->get_sql_select_clauses($options);
        $clauses['select'] = 'SELECT MIN('.$this->db_adapter->escape($column).')';
        $sql = implode(' ', $clauses);
        return $this->db_adapter->get_var($sql);
    }
    
    /**
     * @param int|string $column
     */
    public function sum($column, array $options=[]) : ?string {
        $clauses = $this->db_adapter->get_sql_select_clauses($options);
        $clauses['select'] = 'SELECT SUM('.$this->db_adapter->escape($column).')';
        $sql = implode(' ', $clauses);
        return $this->db_adapter->get_var($sql);
    }
    
    /**
     * @param int|string $column
     */
    public function average($column, array $options=[]) : ?string {
        $clauses = $this->db_adapter->get_sql_select_clauses($options);
        $clauses['select'] = 'SELECT AVERAGE('.$this->db_adapter->escape($column).')';
        $sql = implode(' ', $clauses);
        return $this->db_adapter->get_var($sql);
    }
    
    /**
     * @return list<array<string, string>>
     * @param array $fields
     * @param array|string $keywords
     */
    public function get_keyword_conditions($fields, $keywords): array {
        $conditions = [];
        if (is_string($keywords)) {
            $keywords = preg_split('/[\s]+/', $keywords);
            if ($keywords === false) {
                throw new RuntimeException('Cannot split search keywords.');
            }
        }
        $formatted_fields = [];
        foreach ($fields as $field) {
            if (!str_contains((string) $field, '.')) {
                $field = $this->name.'.'.$field;
            }
            $formatted_fields[] = $field;
        }
        if (count($formatted_fields) === 1) {
            $field_reference = $formatted_fields[0];
        } else {
            $field_reference = 'CONCAT_WS( " ", '.implode(', ', $formatted_fields).')';
        }
        foreach ($keywords as $keyword) {
            $conditions[] = [$field_reference.' LIKE' => '%'.$keyword.'%'];
        }
        return $conditions;
    }
    
    protected function get_total_count(array $options=[]) : ?string {
        $clauses = $this->db_adapter->get_sql_select_clauses($options);
        unset($clauses['limit']);
        $sql = implode(' ', $clauses);
        $sql = 'SELECT COUNT(*) FROM('.$sql.') AS count';
        return $this->db_adapter->get_var($sql);
    }

    protected function process_find_options(array $options): array {
        if (!empty($options['joins'])) {
            if (is_string($options['joins'])) {
                $options['joins'] = [$options['joins']];
            }
            foreach ($options['joins'] as $key => $join) {
                $join_extra_on = '';
                if (is_string($join)) {
					$join_name = $join;
					$join_model_name = $join;
					$join_type = 'JOIN';
				} else {
					$join_name = $key;
					$join_model_name = $join['class'] ?? $key;
					$join_type  = $join['type'] ?? 'JOIN';
                    if(isset($join['extra_on'])){
                        $join_extra_on_clauses = $this->db_adapter->get_where_sql_clauses($join['extra_on']);
                        $join_extra_on = ' AND (' . implode(' AND ', $join_extra_on_clauses) . ')';
                    }

				}

				if (!empty($this->associations[$join_name])) {
					$association = $this->associations[$join_name];

					if (!is_string($join_model_name) || !is_a($join_model_name, MvcModel::class, true)) {
					    MvcError::fatal('Expected a MvcModel implementation.');
					}
					$join_model = new $join_model_name();

					switch ($association['type']) {
						case 'belongs_to':
							$join = [
								'table' => $join_model->table,
								'on' => '('.$join_name.'.'.$join_model->primary_key.' = '.$this->name.'.'.$association['foreign_key'].$join_extra_on.')',
								'alias' => $join_name,
								'type' => $join_type
							];

							break;

						case 'has_many':
							$join = [
								'table' => $join_model->table,
								'on' => '('.$join_name.'.'.$association['foreign_key'].' = '.$this->name.'.'.$this->primary_key.$join_extra_on.')',
								'alias' => $join_name,
								'type' => $join_type
							];

							break;

						case 'has_and_belongs_to_many':
							$join_table_alias = $join_model_name.$this->name;
							// The join for the HABTM join table
							$join = [
								'table' => self::process_table_name($association['join_table']),
								'on' => '('.$join_table_alias.'.'.$association['foreign_key'].' = '.$this->name.'.'.$this->primary_key.')',
								'alias' => $join_table_alias,
								'type' => $join_type
							];
							// The join for the association model's table
							$second_join = [
								'table' => $join_model->table,
								'on' => '('.$join_table_alias.'.'.$association['association_foreign_key'].' = '.$join_model_name.'.'.$join_model->primary_key.$join_extra_on.')',
								'alias' => $join_model_name,
								'type' => $join_type
							];
							$options['joins'][] = $second_join;
							break;
					}

                    $options['joins'][$key] = $join;
                }
            }
        }
        return $this->process_custom_joins($options);
    }

    protected function process_custom_joins(array $options): array {
        if (!empty($options['custom_joins'])) {
            foreach ($options['custom_joins'] as $join) {
                if (isset($this->custom_joins[$join]) && (in_array($this->custom_joins[$join]['reference'], array_column($options['joins'] ?? [], 'alias')) || isset($this->custom_joins[$this->custom_joins[$join]['reference'] ?? '']) || is_null($this->custom_joins[$join]['reference']))) {
                    $options['joins'][$join] = $this->custom_joins[$join];
                }
            }
        }
        return $options;
    }
    
    /**
     * @param int|string $id
     */
    private function update_associations($id, array $model_data): void {
        if (!empty($this->associations)) {
            foreach ($this->associations as $association) {
                switch($association['type']) {
                    case 'has_many':
                        $this->update_has_many_associations($association, $model_data);
                        break;
                    case 'has_and_belongs_to_many':
                        $this->update_has_and_belongs_to_many_associations($id, $association, $model_data);
                        break;
                }
            }
        }
    }
    
    private function update_has_many_associations(array $association, array $model_data): void {
        $association_name = $association['name'];
        if (!empty($model_data[$association_name]) && isset($model_data[$association_name]['ids']) && !empty($model_data[$association_name]['ids'])) {
            // To do: Implement this by first emptying the foreign key values of associated records
            // that currently equal $object_id, then loop through 'ids', setting those foreign key
            // values.

        }
    }
    
    /**
     * @param int|string $object_id
     */
    private function update_has_and_belongs_to_many_associations($object_id, array $association, array $model_data): void {
        $association_name = $association['name'];
        if (!empty($model_data[$association_name])) {
            if (isset($model_data[$association_name]['ids'])) {
                $this->db_adapter->delete_all([
                    'table_reference' => self::process_table_name($association['join_table']),
                    'conditions' => [$association['foreign_key'] => $object_id]
                ]);
                if (!empty($model_data[$association_name]['ids'])) {
                    foreach ($model_data[$association_name]['ids'] as $association_id) {
                        if (!empty($association_id)) {
                            $this->db_adapter->insert(
                                [
                                    $association['foreign_key'] => $object_id,
                                    $association['association_foreign_key'] => $association_id,
                                ],
                                [
                                    'table_reference' => self::process_table_name($association['join_table'])
                                ]
                            );
                        }
                    }
                }
            } elseif (is_array ($model_data[$association_name])) {
                $this->db_adapter->delete_all([
                    'table_reference' => self::process_table_name($association['join_table']),
                    'conditions' => [$association['foreign_key'] => $object_id]
                ]);
                foreach ($model_data[$association_name] as $obj) {
                    if (empty($obj['id'])) {
                        continue;
                    }
                    
                    $fields = [
                        $association['foreign_key'] => $object_id,
                    ];
                    //insert fields
                    foreach ($obj as $field => $value) {
                        $field = $field == 'id' ? $association['association_foreign_key'] : $field;
                        $fields[$field] = $value;
                    }
                    
                    $this->db_adapter->insert(
                        $fields,
                        [
                            'table_reference' => self::process_table_name($association['join_table'])
                        ]
                    );
                }
            }
        }
    }

    /**
     * @return MvcDataValidationError|array|true
     */
    protected function validate_data(array $data): array|true|\MvcDataValidationError {
        if($this->validation_mode == 'all'){
            return $this->validate_all_data($data);
        }
        if(!is_null($this->validation_error)){
            return $this->validation_error;
        }
        $rules = $this->validate;
        if (!empty($rules)) {
            foreach ($rules as $field => $rule) {
                if (isset($data[$field])) {
                    $valid = $this->data_validator->validate($field, $data[$field], $rule);
                    if ($valid !== true) {
                        $this->validation_error = $valid;
                        $this->validation_error_html = $this->validation_error->get_html() ?: '';
                        $this->invalid_data = $data;
                        return $valid;
                    }
                }
            }
        }
        return true;
    }

    protected function validate_all_data(array $data): array|true
    {
        $rules = $this->validate;
        if (!empty($rules)) {
            $error_arr = [];
            foreach ($rules as $field => $rule) {
                if (isset($data[$field])) {
                    $valid = $this->data_validator->validate($field, $data[$field], $rule);
                    if ($valid !== true) {
                        $error_arr[] = $valid;
                    }
                }
            }
            if ($error_arr !== [] || !is_null($this->validation_error)) {
                $previous_errors = $this->validation_error === null ? [] : (is_array($this->validation_error) ? $this->validation_error : [$this->validation_error]);
                $this->validation_error = array_merge($previous_errors, $error_arr);
                $this->validation_error_html = '';
				foreach ( $this->validation_error as $error ) {
						$this->validation_error_html .= $error->get_html();
					}
                $this->invalid_data = $data;
                return $error_arr;
            }

        }
        return true;
    }

    /**
     * @return void
     * @param string $field
     * @param string $message
     */
    protected function invalidate( ?array $data, $field, $message ){
        if($this->validation_mode == 'all'){
            $this->validation_error = $this->validation_error === null ? [] : (is_array($this->validation_error) ? $this->validation_error : [$this->validation_error]);
            $this->validation_error[] = new MvcDataValidationError($field, $message);
            $this->validation_error_html = '';
        }else{
            $this->validation_error = new MvcDataValidationError($field, $message);
            $this->validation_error_html = $this->validation_error->get_html() ?: '';
        }

        $this->invalid_data = $data;
    }
    
    /**
     * @param array|object|null $objects
     *
     * @return array<array-key, MvcModelObject>|MvcModelObject|null
     */
    protected function process_objects($objects, array $options=[]) {
        if (!is_array($objects) && !is_object($objects)) {
            return null;
        }
        $single_object = false;
        if (is_object($objects)) {
            $objects = [$objects];
            $single_object = true;
        }
        
        $includes = array_key_exists('includes', $options) ? $options['includes'] : $this->includes;
        
        if (is_string($includes)) {
            $includes = [$includes];
        }
        
        $recursive = isset($options['recursive']) ? $options['recursive'] - 1 : 2;
        
        foreach ($objects as $key => $object) {
        
            if ($object::class != 'MvcModelObject') {
                $array = get_object_vars($object);
                $object = $this->new_object($array);
            }
            if ($object === null) {
                throw new UnexpectedValueException('A database row could not be converted to a model object.');
            }
            
            if ( (!empty($this->primary_key)) && (!empty($object->{$this->primary_key})) ) {
                $object->__id = $object->{$this->primary_key};
            }
            
            if (!empty($includes) && $recursive != 0) {
                foreach ($includes as $include_key => $include) {
                    if (is_string($include)) {
                        $model_name = $include;
                        $association = $this->associations[$model_name] ?? null;
                    } else {
                        $model_name = $include_key;
                        $association = $include;
                        if (!empty($this->associations[$model_name])) {
                            if (!empty($association['selects'])) {
                                if (is_string($association['selects'])) {
                                    $association['selects'] = [$association['selects']];
                                }
                                $association['fields'] = $association['selects'];
                            }
                            $association = array_merge($this->associations[$model_name], $association);
                        }
                    }
                    if (!is_array($association)) {
                        throw new LogicException('Unknown association: ' . $model_name);
                    }
                    if (empty($association['fields'])) {
                        $association['fields'] = [$association['name'].'.*'];
                    }
                    $model = MvcModelRegistry::require_model($association['class']);
                    switch ($association['type']) {
                        case 'belongs_to':
                            $associated_object = $model->find_by_id($object->{$association['foreign_key']}, [
                                'recursive' => $recursive
                            ]);
                            $object->{MvcInflector::underscore($model_name)} = $associated_object;
                            break;
                            
                        case 'has_many':
                            $associated_objects = $model->find([
                                'selects' => $association['fields'],
                                'conditions' => [$association['foreign_key'] => $object->__id],
                                'recursive' => $recursive
                            ]);
                            $object->{MvcInflector::tableize($model_name)} = $associated_objects;
                            break;
                        
                        case 'has_and_belongs_to_many':
                            
                            $join_alias = 'JoinTable';
                            $associated_objects = $model->find([
                                'selects' => $association['fields'],
                                'joins' => [
                                    'table' => self::process_table_name($association['join_table']),
                                    'on' => $join_alias.'.'.$association['association_foreign_key'].' = '.$model_name.'.'.$model->primary_key,
                                    'alias' => $join_alias
                                ],
                                'conditions' => [$join_alias.'.'.$association['foreign_key'] => $object->__id],
                                'recursive' => $recursive
                            ]);
                            $object->{MvcInflector::tableize($model_name)} = $associated_objects;
                            break;
                    }
                }
            }
            
            if (method_exists($this, 'after_find')) {
                $this->after_find($object);
            }
            
            // Set this after after_find, in case after_find sets this field
            if (!empty($this->display_field)) {
                $object->__name = empty($object->{$this->display_field}) ? null : $object->{$this->display_field};
            }
            
            $objects[$key] = $object;
        }
        if ($single_object) {
            return $objects[0];
        }
        return $objects;
    }
    
    public static function process_table_name(string $table_name): string {
        global $wpdb;
        return str_replace('{prefix}', $wpdb->prefix, $table_name);
    }
    
    /**
     * @return void
     */
    protected function init_schema() {
        if ( isset( self::$describe_cache[ $this->table ] ) ) {
            $results = self::$describe_cache[ $this->table ];
        } else {
            $found = false;
            $results = wp_cache_get($this->table, 'wp-mvc-schema', false, $found);
            if (!$found || !is_array($results)) {
                $results = $this->db_adapter->get_results(
                    "DESCRIBE {$this->table_reference}"
                );
                if (is_array($results) && $results !== []) {
                    wp_cache_set($this->table, $results, 'wp-mvc-schema');
                }
            }
            if (is_array($results) && $results !== []) {
                self::$describe_cache[$this->table] = $results;
            }
        }
        
        $schema = [];
        
        foreach ($results ?? [] as $result) {
            $field = $result->Field;
            $type_schema = explode('(', (string) $result->Type);
            $type = $type_schema[0];
            $length = isset($type_schema[1]) ? rtrim($type_schema[1], ')') : null; 
            $column = [];
            $column['field'] = $field;
            $column['key'] = $result->Key ?: null;
            $column['type'] = $type;
            $column['length'] = $length;
            $column['null'] = $result->Null == 'YES';
            $column['default'] = $result->Default;
            $schema[$field] = $column;
        }
        
        $this->schema = $schema;
        $this->db_adapter->schema = $schema;
    }
    
    /**
     * @return void
     */
    protected function init_associations() {
        if (!is_array($this->associations)) {
            $this->associations = [];
        }
        if (!empty($this->belongs_to)) {
            foreach ($this->belongs_to as $key => $value) {
                $config = null;
                $association_name = null;
                if (is_string($value)) {
                    $association_name = $value;
                    $config = [
                        'type' => 'belongs_to',
                        'name' => $association_name,
                        'class' => $association_name,
                        'foreign_key' => MvcInflector::underscore($association_name).'_id',
                        'includes' => null,
                        'dependent' => false
                    ];
                } elseif (is_string($key) && is_array($value)) {
                    $association_name = $key;
                    $config = [
                        'type' => 'belongs_to',
                        'name' => empty($value['name']) ? $association_name : $value['name'],
                        'class' => empty($value['class']) ? $association_name : $value['class'],
                        'foreign_key' => empty($value['foreign_key']) ? MvcInflector::underscore($association_name).'_id' : $value['foreign_key'],
                        'includes' => $value['fields'] ?? null,
                        'dependent' => $value['dependent'] ?? false
                    ];
                }
                if ($config !== null && $association_name !== null) {
                    $this->associations[$association_name] = $config;
                }
            }
        }
        if (!empty($this->has_many)) {
            foreach ($this->has_many as $key => $value) {
                $config = null;
                $association_name = null;
                if (is_string($value)) {
                    $association_name = $value;
                    $config = [
                        'type' => 'has_many',
                        'name' => $association_name,
                        'class' => $association_name,
                        'foreign_key' => MvcInflector::underscore($this->name).'_id',
                        'fields' => null,
                        'includes' => null,
                        'dependent' => false
                    ];
                } elseif (is_string($key) && is_array($value)) {
                    $association_name = $key;
                    $config = [
                        'type' => 'has_many',
                        'name' => empty($value['name']) ? $association_name : $value['name'],
                        'class' => empty($value['class']) ? $association_name : $value['class'],
                        'foreign_key' => empty($value['foreign_key']) ? MvcInflector::underscore($this->name).'_id' : $value['foreign_key'],
                        'fields' => $value['fields'] ?? null,
                        'includes' => null,
                        'dependent' => $value['dependent'] ?? false
                    ];
                }
                if ($config !== null && $association_name !== null) {
                    $this->associations[$association_name] = $config;
                }
            }
        }
        if (!empty($this->has_and_belongs_to_many)) {
            foreach ($this->has_and_belongs_to_many as $key => $value) {
                if (is_string($key) && is_array($value)) {
                    $association_name = $key;
                    if (isset($value['fields'])) {
                        foreach ($value['fields'] as $key => $field) {
                            if (str_contains((string) $field,'.')) {
                                //AR: contains '.', dont prepand a table
                                $value['fields'][$key] = $field;
                            }
                            else{
                                $value['fields'][$key] = $association_name.'.'.$field;
                            }
                            
                        }
                    }
                    $config = [
                        'type' => 'has_and_belongs_to_many',
                        'name' => $association_name,
                        'class' => $association_name,
                        'foreign_key' => $value['foreign_key'] ?? MvcInflector::underscore($this->name) . '_id',
                        'association_foreign_key' => $value['association_foreign_key'] ?? MvcInflector::underscore($association_name) . '_id',
                        'join_table' => self::process_table_name($value['join_table']),
                        'fields' => $value['fields'] ?? null,
                        'includes' => $value['includes'] ?? null,
                        'dependent' => $value['dependent'] ?? false
                    ];
                    $this->associations[$association_name] = $config;
                }
            }
        }
    }
    
    /**
     * @return void
     */
    protected function init_properties() {
        $this->properties = [];
        foreach ($this->associations ?? [] as $association_name => $association) {
            $property_name = null;
            if ($association['type'] == 'belongs_to') {
                $property_name = MvcInflector::underscore($association_name);
            } elseif (in_array($association['type'], ['has_many', 'has_and_belongs_to_many'])) {
                $property_name = MvcInflector::tableize($association_name);
            }
            if ($property_name) {
                $this->properties[$property_name] = [
                    'type' => 'association',
                    'association' => $association
                ];
            }
        }
    }
    
    /**
     * @return void
     */
    protected function check_for_obsolete_functionality() {
        $obsolete_attributes = [
            'admin_pages' => ['should be defined as \'AdminPages\' in MvcConfiguration', 'http://wpmvc.org/documentation/1.2/66/adminpages/'],
            'admin_columns' => ['should be defined as \'default_columns\' in the admin controller', 'http://wpmvc.org/documentation/1.2/16/default_columns/'],
            'admin_searchable_fields' => ['should be defined as \'default_searchable_fields\' in the admin controller', 'http://wpmvc.org/documentation/1.2/18/default_searchable_fields/'],
            'admin_search_joins' => ['should be defined as \'default_searchable_joins\' in the admin controller', 'http://wpmvc.org/documentation/1.2/17/default_search_joins/'],
            'public_searchable_fields' => ['should be defined as \'default_searchable_fields\' in the public controller', 'http://wpmvc.org/documentation/1.2/18/default_searchable_fields/'],
            'public_search_joins' => ['should be defined as \'default_searchable_joins\' in the public controller', 'http://wpmvc.org/documentation/1.2/17/default_search_joins/'],
            'hide_menu' => ['should be defined as \'in_menu\' in \'AdminPages\' in MvcConfiguration', 'http://wpmvc.org/documentation/1.2/66/adminpages/']
        ];
        /** @var array<string, mixed> $attributes Includes legacy/dynamic model configuration. */
        $attributes = get_object_vars($this);
        foreach ($obsolete_attributes as $attribute => $value) {
            if (isset($attributes[$attribute])) {
                $message = $value[0];
                $url = $value[1];
                $message = 'The \''.$attribute.'\' attribute (in the '.$this->name.' model) '.$message.' as of WP MVC 1.2';
                $message .= ' (<a href="'.$url.'">read more</a>).';
                MvcError::fatal($message);
            }
        }
    }
    
    /**
     * @param mixed $data
     */
    protected function object_to_array($data) : mixed {
        if (is_object($data)) {
            return get_object_vars($data);
        }
        return $data;
    }
    
    public function __call(string $method, array $args) {
        if (str_starts_with($method, 'find_by_')) {
            $attribute = substr($method, 8);
            if (isset($this->schema[$attribute])) {
                return $this->find(['conditions' => [$attribute => $args[0]]]);
            }
            MvcError::fatal('Undefined attribute: '.$attribute.' for class: '.static::class.' when calling: '.$method.'.');
        }
        if (str_starts_with($method, 'find_one_by_')) {
            $attribute = substr($method, 12);
            if (isset($this->schema[$attribute])) {
                return $this->find_one(['conditions' => [$attribute => $args[0]]]);
            }
            MvcError::fatal('Undefined attribute: '.$attribute.' for class: '.static::class.' when calling: '.$method.'.');
        }
        MvcError::fatal('Undefined method: '.static::class.'::'.$method.'.');
    }

}
