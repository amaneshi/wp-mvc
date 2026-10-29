<?php

class MvcPublicController extends MvcController {


    public array $pagination = [];
    public ?array $default_searchable_fields = null;
    public ?array $default_search_joins = null;

    public bool $is_admin = false;
    
    function __construct() {
        parent::__construct();
        $this->clean_wp_query();
    }
    
    public function index(): void {
        $this->set_objects();
    }
    
    public function show(): void {
        $this->set_object();
    }
    
    public function set_objects(): void {
        $this->process_params_for_search();
        $collection = $this->require_model()->paginate($this->params);
        $this->set('objects', $collection['objects']);
        $this->set_pagination($collection);
    }
    
    public function set_pagination(array $collection): void {
        $params = $this->params;
        unset($params['page']);
        unset($params['conditions']);
        $url = MvcRouter::public_url(['controller' => $this->name, 'action' => $this->action]);
        $this->pagination = [
            'base' => $url.'%_%',
            'format' => '?page=%#%',
            'total' => $collection['total_pages'],
            'current' => $collection['page'],
            'add_args' => $params
        ];
        
        unset($collection['objects']);
        $this->pagination = array_merge($collection, $this->pagination);
    }
    
    /**
     * @return null|string|string[]
     *
     * @psalm-return list<string>|null|string
     * @param array $options
     */
    public function pagination($options=[]) {
        return paginate_links($this->pagination);
    }
    
    public function set_page_title(): void {
        add_filter('wp_title', $this->set_wp_title(...));
    }
    
    /**
     * @param string $action
     */
    public function after_action($action): void {
        $this->set_page_title();
    }
    
    /**
     * @param string $original_title
     */
    public function set_wp_title($original_title) : string {
        $separator = ' | ';
        $controller_name = MvcInflector::titleize($this->name);
        $object_name = null;
        $object = null;
        if ($this->action && ($this->action == 'show' && is_object($this->object))) {
            $object = $this->object;
            if (!empty($this->object->__name)) {
                $object_name = $this->object->__name;
            }
        }
        $pieces = [
            $object_name,
            $controller_name
        ];
        $pieces = array_filter($pieces);
        $title = implode($separator, $pieces);
        $title .= $separator;
        $title_options = apply_filters('mvc_page_title', [
            'controller' => $controller_name,
            'action' => $this->action,
            'object_name' => $object_name,
            'object' => $object,
            'title' => $title
        ]);
        return $title_options['title'];
    }
    
    /**
     * @return void
     */
    protected function process_params_for_search() {
        $this->params['page'] = empty($this->params['page']) ? 1 : $this->params['page'];
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
    protected function clean_wp_query() {
        global $wp_query;
        $wp_query->is_single = false;
        $wp_query->is_page = false;
        $wp_query->queried_object = null;
        $wp_query->is_home = false;
    }

}

?>
