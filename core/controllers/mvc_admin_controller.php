<?php

class MvcAdminController extends MvcController {

    public ?array $default_searchable_fields = null;
    public ?array $default_search_joins = null;
    public ?array $default_columns = null;

    public bool $is_admin = true;
    
    public function index(): void {
        $this->set_objects();
    }
    
    public function add(): void {
        $this->create_or_save();
    }
    
    public function edit(): void {
        $this->verify_id_param();
        $this->create_or_save();
        $this->set_object();
    }
    
    public function delete(): void {
        $this->verify_id_param();
        $this->set_object();
        if (!empty($this->object)) {
            $this->require_model()->delete($this->params['id']);
            $this->flash('notice', __('Successfully deleted!', 'wpmvc'));
        } else {
            $this->flash('warning', 'A '.MvcInflector::titleize($this->require_model()->name).' with ID "'.$this->params['id'].'" couldn\'t be found.');
        }
        $url = MvcRouter::admin_url(['controller' => $this->name, 'action' => 'index']);
        $this->redirect($url);
    }
    
    public function verify_id_param(): void {
        if (empty($this->params['id'])) {
            die('No ID specified');
        }
    }
    
    public function create_or_save(): void {
        if (!empty($this->params['data'][$this->require_model()->name])) {
            $object = $this->params['data'][$this->require_model()->name];
            if (empty($object['id'])) {
                if($this->require_model()->create($this->params['data'])) {
                    $id = $this->require_model()->insert_id;
                    $url = MvcRouter::admin_url(['controller' => $this->name, 'action' => 'edit', 'id' => $id]);
                    $this->flash('notice', __('Successfully created!', 'wpmvc'));
                    $this->redirect($url);
                } else {
                    $this->flash('error', $this->require_model()->validation_error_html);
                    $this->set_object();
                }
            } elseif ($this->require_model()->save($this->params['data'])) {
                $this->flash('notice', __('Successfully saved!', 'wpmvc'));
                $this->refresh();
            } else {
                $this->flash('error', $this->require_model()->validation_error_html);
            }
        }
    }
    
    public function create(): void {
        if (!empty($this->params['data'][$this->require_model()->name])) {
            $id = $this->require_model()->create($this->params['data']);
            $url = MvcRouter::admin_url(['controller' => $this->name, 'action' => 'edit', 'id' => $id]);
            $this->flash('notice', __('Successfully created!', 'wpmvc'));
            $this->redirect($url);
        }
    }
    
    public function save(): void {
        if (!empty($this->params['data'][$this->require_model()->name])) {
            if ($this->require_model()->save($this->params['data'])) {
                $this->flash('notice', __('Successfully saved!', 'wpvmc'));
                $this->refresh();
            } else {
                $this->flash('error', $this->require_model()->validation_error_html);
            }
        }
    }
    
    public function set_objects(): void {
        $this->init_default_columns();
        $this->process_params_for_search();
        $collection = $this->require_model()->paginate($this->params);
        $this->set('objects', $collection['objects']);
        $this->set_pagination($collection);
    
    }
    
    public function set_pagination(array $collection): void {
        $url_params = MvcRouter::admin_url_params(['controller' => $this->name]);
        $params = $this->params;
        unset($params['page_num']);
        $params['page'] = $url_params['page'];
        $this->set('pagination', [
            'base' => get_admin_url().'admin.php%_%',
            'format' => '?page_num=%#%',
            'total' => (int) $collection['total_pages'],
            'current' => (int) $collection['page'],
            //TODO: Was passed all search array, now limit only to controller name
            'add_args' => ['page' => $params['page']]
        ]);
    }
    
    /**
     * @return void
     * @param string $action
     */
    public function after_action($action) {
    }
    
    /**
     * @return void
     */
    protected function process_params_for_search() {
        $this->params['page'] = empty($this->params['page_num']) ? 1 : $this->params['page_num'];
        if (!empty($this->params['q']) && !empty($this->default_searchable_fields)) {
            $this->params['conditions'] = $this->require_model()->get_keyword_conditions($this->default_searchable_fields, $this->params['q']);
            if (!empty($this->default_search_joins)) {
                $this->params['joins'] = $this->default_search_joins;
                $this->params['group'] = $this->require_model()->name.'.'.$this->require_model()->primary_key;
            }
        }
    }
    
    /**
     * @return void
     */
    protected function init_default_columns() {
        if (empty($this->default_columns)) {
            MvcError::fatal('No columns defined for this view.  Please define them in the controller, like this:
                <pre>
                    class '.MvcInflector::camelize($this->name).'Controller extends MvcAdminController {
                        var $default_columns = array(\'id\', \'name\');
                    }
                </pre>');
        }
        $admin_columns = [];
        foreach ($this->default_columns as $key => $value) {
            if (is_array($value)) {
                if (!isset($value['label'])) {
                    $value['label'] = MvcInflector::titleize($key);
                }
            } elseif (is_int($key)) {
                $key = $value;
                $value = $value == 'id' ? ['label' => 'ID'] : ['label' => MvcInflector::titleize($value)];
            } else {
                $value = ['label' => $value];
            }
            $value['key'] = $key;
            $admin_columns[$key] = $value;
        }
        $this->default_columns = $admin_columns;
    }

}

?>
