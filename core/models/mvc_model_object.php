<?php

// Row columns remain real public properties for serialization, null reads,
// property_exists(), and indirect writes such as $object->items[] = $item.
// __get() resolves associations and genuinely missing fields only.
#[\AllowDynamicProperties]
class MvcModelObject {
    
    public ?string $__model_name = null;
    private array $__settings = [];
    
    function __construct(MvcModel $model) {
        $this->__settings['properties'] = $model->properties;
        $this->__settings['model'] = [
            'name' => $model->name,
            'primary_key' => $model->primary_key
        ];
        $this->__model_name = $model->name;
    }
    
    public function to_array(): array {
        return get_object_vars($this);
    }

    // Preserve native public columns while exposing schema-dependent writes to Psalm 5.
    public function __set(string $property_name, mixed $value): void {
        $this->$property_name = $value;
    }

    public function __get(string $property_name): mixed {
        if (!empty($this->__settings['properties'][$property_name])) {
            $property = $this->__settings['properties'][$property_name];
            if ($property['type'] == 'association') {
                $objects = $this->get_associated_objects($property['association']);
                $this->$property_name = $objects;
                return $this->$property_name;
            }
        }
        if ($this->__settings['model']['name'] == 'MvcPost' && !empty($this->post_type) && str_starts_with((string) $this->post_type, 'mvc_')) {
            $model_name = MvcInflector::camelize(substr((string) $this->post_type, 4));
            $model = MvcModelRegistry::require_model($model_name);
            $object = $model->find_one([
                'post_id' => $this->ID,
                'recursive' => 0
            ]);
            $this->$property_name = $object;
            return $this->$property_name;
        }
        $class = empty($this->__model_name) ? 'MvcModelObject' : $this->__model_name;
        MvcError::warning('Undefined property: '.$class.'::'.$property_name.'.');
        return null;
    }
    
    private function get_associated_objects(array $association) : array|MvcModelObject|null {
        $model_name = $association['class'];
        $model = MvcModelRegistry::require_model($model_name);
        switch ($association['type']) {
            case 'belongs_to':
                return $model->find_by_id($this->{$association['foreign_key']}, [
                    'recursive' => 0
                ]);
                
            case 'has_many':
                return $model->find([
                    'selects' => $association['fields'],
                    'conditions' => [$association['foreign_key'] => $this->__id],
                    'recursive' => 0
                ]);
            
            case 'has_and_belongs_to_many':
                $join_alias = 'JoinTable';
                return $model->find([
                    'selects' => $association['fields'],
                    'joins' => [
                        'table' => MvcModel::process_table_name($association['join_table']),
                        'on' => $join_alias.'.'.$association['association_foreign_key'].' = '.$model_name.'.'.$model->primary_key,
                        'alias' => $join_alias
                    ],
                    'conditions' => [$join_alias.'.'.$association['foreign_key'] => $this->__id],
                    'recursive' => 0
                ]);
        }
        return null;
    }
    
}
