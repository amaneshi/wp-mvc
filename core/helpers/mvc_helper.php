<?php

// Applications attach model-specific URL/link callbacks to helpers at runtime.
/**
 * @psalm-consistent-constructor
 */
#[\AllowDynamicProperties]
class MvcHelper {

    /** @var string */
    public $plugin_name = '';

    protected \MvcFileIncluder $file_includer;
    
    function __construct() {
        $this->file_includer = new MvcFileIncluder();
        $this->init();
        $this->plugin_name = MvcObjectRegistry::get_object('plugin_name');
        if (!property_exists($this, 'plugin_name') || $this->plugin_name === null) {
            $this->plugin_name = '';
        }
    }
    
    /**
     * @return void
     */
    public function init() {
    }
    
    /**
     * @param string $path
     * @param array $view_vars
     */
    public function render_view($path, $view_vars=[]): void {
        extract($view_vars);
        $filepath = $this->file_includer->find_first_app_file_or_core_file('views/'.$path.'.php');
        if (!$filepath) {
            $path = preg_replace('/admin\/(?!layouts)([\w_]+)/', 'admin', (string) $path);
            $filepath = $this->file_includer->find_first_app_file_or_core_file('views/'.$path.'.php');
            if (!$filepath) {
                MvcError::warning('View "'.$path.'" not found.');
            }
        }
        /** @psalm-suppress UnresolvableInclude MVC resolves this helper view from registered application paths. */
        require $filepath;
    }
    
    /**
     * @return string
     * @param string $string
     */
    static function esc_attr($string) {
        return esc_attr($string);
    }
    
    /**
     * @param array $attributes
     * @param array|string $valid_attributes_array_or_tag
     */
    static function attributes_html($attributes, $valid_attributes_array_or_tag): string {
    
        $event_attributes = [
            'standard' => [
                'onclick',
                'ondblclick',
                'onkeydown',
                'onkeypress',
                'onkeyup',
                'onmousedown',
                'onmousemove',
                'onmouseout',
                'onmouseover',
                'onmouseup'
            ],
            'form' => [
                'onblur',
                'onchange',
                'onfocus',
                'onreset',
                'onselect',
                'onsubmit'
            ]
        ];
    
        // To do: add on* event attributes
        $valid_attributes_by_tag = [
            'a' => [
                'accesskey',
                'charset',
                'class',
                'dir',
                'coords',
                'href',
                'hreflang',
                'id',
                'lang',
                'name',
                'rel',
                'rev',
                'shape',
                'style',
                'tabindex',
                'target',
                'title',
                'xml:lang'
            ],
            'input' => [
                'accept',
                'access_key',
                'align',
                'alt',
                'autocomplete',
                'checked',
                'class',
                'dir',
                'disabled',
                'id',
                'lang',
                'maxlength',
                'name',
                'placeholder',
                'readonly',
                'required',
                'size',
                'src',
                'style',
                'step',
                'tabindex',
                'title',
                'type',
                'value',
                'xml:lang',
                $event_attributes['form']
            ],
            'textarea' => [
                'access_key',
                'class',
                'cols',
                'dir',
                'disabled',
                'id',
                'lang',
                'maxlength',
                'name',
                'placeholder',
                'readonly',
                'required',
                'rows',
                'style',
                'tabindex',
                'title',
                'xml:lang',
                $event_attributes['form']
            ],
            'select' => [
                'class',
                'dir',
                'disabled',
                'id',
                'lang',
                'multiple',
                'name',
		        'required',
                'size',
                'style',
                'tabindex',
                'title',
                'xml:lang',
                $event_attributes['form']
            ]
        ];
        
        foreach ($valid_attributes_by_tag as $key => $valid_attributes) {
            $valid_attributes = array_merge($event_attributes['standard'], $valid_attributes);
            $valid_attributes = self::array_flatten($valid_attributes);
            $valid_attributes_by_tag[$key] = $valid_attributes;
        }
        
        $valid_attributes = is_array($valid_attributes_array_or_tag) ? $valid_attributes_array_or_tag : $valid_attributes_by_tag[$valid_attributes_array_or_tag];
        
        $attributes = array_intersect_key($attributes, array_flip($valid_attributes));
        
        $attributes_html = '';
        foreach ($attributes as $key => $value) {
            $attributes_html .= ' '.$key.'="'.esc_attr($value).'"';
        }
        return $attributes_html;
    
    }
    
    // Move these into an AdminHelper
    
    /**
     * @param MvcAdminController $controller
     */
    public function admin_header_cells($controller): string {
        $html = '';
        foreach ($controller->default_columns ?? [] as $column) {
            $html .= $this->admin_header_cell(__($column['label'], $this->plugin_name));
        }
        $html .= $this->admin_header_cell('');
        return '<tr>'.$html.'</tr>';
        
    }
    
    public function admin_header_cell(string $label): string {
        return '<th scope="col" class="manage-column">'.$label.'</th>';
    }
    
    /**
     * @param MvcAdminController $controller
     * @param array|null $objects
     * @param array $options
     */
    public function admin_table_cells($controller, $objects, $options = []): string {
        $html = '';
        foreach ($objects ?? [] as $object) {
            $html .= '<tr>';
            foreach ($controller->default_columns ?? [] as $column) {
                $html .= $this->admin_table_cell($controller, $object, $column, $options);
            }
            $html .= $this->admin_actions_cell($controller, $object, $options);
            $html .= '</tr>';
        }
        return $html;
    }
    
    /**
     * @param MvcAdminController $controller
     * @param object $object
     * @param array $options
     */
    public function admin_table_cell($controller, $object, array $column, $options = []): string {
        $value = empty($column['value_method']) ? $object->{$column['key']} : $controller->{$column['value_method']}($object);
        return '<td>'.$value.'</td>';
    }
    
    /**
     * @param MvcAdminController $controller
     * @param object $object
     * @param array $options
     */
    public function admin_actions_cell($controller, $object, $options = []): string {
        
        $default = [
            'actions' => [
                'edit' => true,
                'view' => true,
                'delete' => true,
            ]
        ];
        
        $options = array_merge($default, $options);
        
        $links = [];
        $object_name = empty($object->__name) ? 'Item #'.$object->__id : $object->__name;
        $encoded_object_name = static::esc_attr($object_name);
        
        if($options['actions']['edit']){
            $links[] = '<a href="'.MvcRouter::admin_url(['object' => $object, 'action' => 'edit']).'" title="' . __('Edit', 'wpmvc') . ' ' .$encoded_object_name.'">' . __('Edit', 'wpmvc') .'</a>';
        }
        
        if($options['actions']['view']){
            $links[] = '<a href="'.MvcRouter::public_url(['object' => $object]).'" title="' . __('View', 'wpmvc') . ' ' .$encoded_object_name.'">' . __('View', 'wpmvc') .'</a>';
        }
        
        if($options['actions']['delete']){
            $links[] = '<a href="'.MvcRouter::admin_url(['object' => $object, 'action' => 'delete']).'" title="' . __('Delete', 'wpmvc') . ' ' .$encoded_object_name.'" onclick="return confirm(&#039;' . __('Are you sure you want to delete', 'wpmvc') . ' ' .$encoded_object_name.'?&#039;);">' . __('Delete', 'wpmvc') .'</a>';
        }

        $html = implode(' | ', $links);
        return '<td>'.$html.'</td>';
    }
    
    // To do: move this into an MvcUtilities class (?)
    
    static function array_flatten(array $array): mixed {

        foreach ($array as $key => $value){
            $array[$key] = (array)$value;
        }
        
        return call_user_func_array(array_merge(...), $array);
    
    }

}

?>
