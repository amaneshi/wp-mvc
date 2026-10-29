<?php

// Model and helper names are injected by the framework at runtime.
/**
 * @psalm-no-seal-properties
 * @psalm-consistent-constructor
 */
#[\AllowDynamicProperties]
class MvcController {

    /** @var HtmlHelper|null */
    public $html;
    /** @var MvcHelper|null */
    public $helper;
    /** @var MvcFormHelper */
    public $form;
    /** @var string|null */
    public $views_path;
    /** @var string|null */
    public $model_name;
    /** @var string|null */
    public $action;
    /** @var MvcModelObject|null */
    public $object;
    public bool $is_admin = false;
    /** @var string|null */
    public $main_view;

    protected \MvcFileIncluder $file_includer;

    /**
     * @var null|string|string[]
     *
     */
    public $after;

    /**
     * @var null|string|string[]
     *
     */
    public $before;
    public bool $is_controller = true;

    public ?MvcModel $model = null;

    /**
     * @var string
     */
    public $name = '';
    public array $params = [];

    /**
     * @var bool
     */
    public $view_rendered = false;
    public array $view_vars = [];

    protected function require_model(): MvcModel {
        if (!$this->model instanceof MvcModel) {
            throw new LogicException('No model loaded for ' . static::class);
        }
        return $this->model;
    }

    protected function require_html(): HtmlHelper {
        if (!$this->html instanceof HtmlHelper) {
            $this->load_helper('Html');
        }
        if (!$this->html instanceof HtmlHelper) {
            throw new LogicException('Unable to load HtmlHelper');
        }
        return $this->html;
    }
    
    function __construct() {

        // Necessary for flash()-related functionality in the admin area only
        if (is_admin() && session_id() == '' && !headers_sent()) {
            session_start();
        }

        $this->set_meta();
        $this->file_includer = new MvcFileIncluder();
    
    }
    
    public function init(): void {
    
        $this->load_helper('Html');
        
        $models = MvcModelRegistry::get_models();
        foreach ($models as $model_name => $model) {
            $underscore = MvcInflector::underscore($model_name);
            $tableize = MvcInflector::tableize($model_name);
            // Add dynamically-created methods to HtmlHelper in the form speaker_url($object), speaker_link($object)
            $method = $underscore . '_url';
            $this->require_html()->{$method} = /**
             * @param object|null $object
             * @param array $options
             *
             * @return string
             */
            function ($object, $options = []) use ($tableize): string {
                $defaults = [
                    'controller' => $tableize,
                    'action' => 'show'
                ];
                $options = array_merge($defaults, $options);

                return HtmlHelper::object_url($object, $options);
            };
            $method = $underscore . '_link';
            $this->require_html()->{$method} = /**
             * @param object|null $object
             * @param array $options
             *
             * @return string
             */
            function ($object, $options = []) use ($tableize): string {
                $defaults = [
                    'controller' => $tableize,
                    'action' => 'show'
                ];
                $options = array_merge($defaults, $options);

                return HtmlHelper::object_link($object, $options);
            };
        }
        
        if (is_admin()) {
            $this->load_helper('Form');
        }
        
        if (!$this->model instanceof \MvcModel) {
            $helper_name = class_exists('AppHelper') ? 'AppHelper' : 'MvcHelper';
        } else {
            $model = $this->require_model()->name;
            if (class_exists($model.'Helper')) {
                $helper_name = $model.'Helper';
            } elseif (class_exists('AppHelper')) {
                $helper_name = 'AppHelper';
            } else {
                $helper_name = 'MvcHelper';
            }
        }
        if (!is_a($helper_name, MvcHelper::class, true)) {
            MvcError::fatal('Expected a MvcHelper implementation.');
        }
        $this->helper = new $helper_name();
        
        if (is_string($this->before)) {
            $this->before = [$this->before];
        }
        if (is_string($this->after)) {
            $this->after = [$this->after];
        }
        
    }
    
    /**
     * @return void
     */
    public function index() {
    
    }
    
    private function set_meta(): void {
        $model = static::class;
        $model = preg_replace('/Controller$/', '', $model);
        $this->name = MvcInflector::underscore($model);
        $this->views_path = '';
        if (preg_match('/^Admin[A-Z]/', (string) $model)) {
            $this->views_path = 'admin/';
            $model = preg_replace('/^Admin/', '', (string) $model);
        }
        
        $model = MvcInflector::singularize($model);
        $this->views_path .= MvcInflector::tableize($model).'/';
        $this->model_name = $model;
        // To do: remove the necessity of this redundancy
        if (is_a($model, MvcModel::class, true)) {
            $model_instance = new $model();
            $this->model = $model_instance;
            $this->{$model} = $model_instance;
        }
    }
    
    /**
     * @return void
     */
    protected function load_helper(string $helper_name) {
        $helper_name .= 'Helper';
        $helper_underscore = MvcInflector::underscore($helper_name);
        
        $this->file_includer->require_first_app_file_or_core_file('helpers/'.$helper_underscore.'.php');
        
        if (is_a($helper_name, MvcHelper::class, true)) {
            $helper_method_name = str_replace('_helper', '', $helper_underscore);
        
            $helper = new $helper_name();
            $this->{$helper_method_name} = $helper;
        
            if ($helper instanceof MvcFormHelper) {
                $helper->controller = new stdClass();
                $helper->controller->action = $this->action;
                $helper->controller->name = $this->name;
            }
        }
    }
    
    /**
     * @param string $model_name
     *
     * @return void
     */
    protected function load_model($model_name) {
        $model_underscore = MvcInflector::underscore($model_name);
        
        $this->file_includer->require_first_app_file_or_core_file('models/'.$model_underscore.'.php');
        
        if (class_exists($model_name)) {
            $this->{$model_name} = new $model_name();
        }
    }
    
    /**
     * @return void
     * @param array $model_names
     */
    protected function load_models($model_names) {
        foreach ($model_names as $model_name) {
            $this->load_model($model_name);
        }
    }
    
    public function set_object(): bool {
        $object = null;
        $model = $this->require_model();
        if (!empty($model->invalid_data)) {
            if (!empty($this->params['id']) && empty($model->invalid_data[$model->primary_key])) {
                $model->invalid_data[$model->primary_key] = $this->params['id'];
            }
            $object = $model->new_object($model->invalid_data);
        } elseif (!empty($this->params['id'])) {
            $object = $this->require_model()->find_by_id($this->params['id']);
        }
        $this->set('object', $object);
        if ($object instanceof \MvcModelObject) {
            MvcObjectRegistry::add_object($this->require_model()->name, $this->object);
            return true;
        }
        MvcError::warning('Object not found.');
        return false;
    }
    
    public function set(string|array $variable_name_or_array, mixed $data=null): void {
        if (is_string($variable_name_or_array)) {
            $this->set_view_var($variable_name_or_array, $data);
        } else {
            foreach ($variable_name_or_array as $key => $value) {
                $this->set_view_var($key, $value);
            }
        }
    }
    
    /**
     * @param string $path
     */
    public function render_view($path, array $options=[]): void {
    
        // Rendering from within a controller
        if ($this->is_controller) {
            if ($this->is_admin) {
                $layout = empty($options['layout']) ? 'admin' : $options['layout'];
                $layout_directory = 'admin/layouts/';
            } else {
                $layout = empty($options['layout']) ? 'public' : $options['layout'];
                $layout_directory = 'layouts/';
            }
            $this->main_view = $path;
            // We're now entering the view, so $this should no longer be a controller
            $this->is_controller = false;
            $this->set('params', $this->params);
            $this->render_view_with_view_vars($layout_directory.$layout, $options);
            if (!$this->is_admin) {
                die();
            }
        // Rendering from within a view
        } else {
            $this->render_view_from_view($path, $options);
        }
    
    }
    
    public function render_main_view(): void {
        $this->render_view_with_view_vars($this->main_view);
    }
    
    /**
     * @param null|string $path
     *
     * @return void
     */
    protected function render_view_with_view_vars($path, array $options=[]) {
        if ($path === null) {
            throw new LogicException('No view path selected.');
        }
        
        $view_vars = [
            'model' => $this->model,
            'views_path' => $this->views_path,
            'helper' => $this->helper
        ];
        
        if (!empty($options['locals'])) {
            $view_vars = array_merge($view_vars, $options['locals']);
        }
        
        $this->view_vars = array_merge($this->view_vars, $view_vars);
        $this->include_view($path, $this->view_vars);
        $this->view_rendered = true;
    
    }
    
    /**
     * @return void
     * @param string $path
     */
    protected function render_view_from_view($path, array $options=[]) {

        $view_vars = [];
        
        if (!str_contains($path, '/')) {
            $path = $this->name.'/'.$path;
        }
        
        if (isset($options['collection'])) {
            $var_name = empty($options['as']) ? 'object' : $options['as'];
            foreach ($options['collection'] as $object) {
                $view_vars = [];
                $view_vars[$var_name] = $object;
                if (!empty($options['locals'])) {
                    $view_vars = array_merge($view_vars, $options['locals']);
                }
                $this->include_view($path, $view_vars);
            }
            return;
        }
        
        if (!empty($options['locals'])) {
            $view_vars = $options['locals'];
        }
        
        $this->include_view($path, $view_vars);
    
    }
    
    /**
     * @param string $path
     * @param array $options
     */
    public function render_to_string($path, $options=[]): string|false {
        $defaults = [
            'bypass_layout' => true,
            'vars' => $this->view_vars
        ];
        $is_controller = $this->is_controller;
        $this->is_controller = false;
        $options = array_merge($defaults, $options);
        ob_start();
        $this->include_view($path, $options['vars']);
        $string = ob_get_contents();
        ob_end_clean();
        $this->is_controller = $is_controller;
        return $string;
    }
    
    /**
     * @param array $view_vars
     *
     *
     * @return void
     * @param string $path
     */
    protected function include_view($path, $view_vars=[]) {
        extract($view_vars);
        $path = preg_replace('/^admin_([^\/]+)/', 'admin/$1', (string) $path);
        $filepath = $this->file_includer->find_theme_or_view_file($path);
        if (!$filepath) {
            $path = preg_replace('/admin\/(?!layouts)([\w_]+)/', 'admin', (string) $path);
            $filepath = $this->file_includer->find_theme_or_view_file($path);
            if (!$filepath) {
                MvcError::warning('View "'.$path.'" not found.');
                return;
            }
        }
        /** @psalm-suppress UnresolvableInclude MVC resolves this view from registered application/theme paths. */
        require $filepath;
    }
    
    /**
     * @param mixed $value
     */
    private function set_view_var(string|int $key, $value): void {
        if ($key == 'object') {
            $this->object = $value;
        }
        $this->view_vars[$key] = $value;
    }
    
    /**
     * @return void
     * @param int|string $type
     * @param string $message
     */
    protected function set_flash($type, $message) {
        $this->init_flash();
        $_SESSION['mvc_flash'][$type] = $message;
    }
    
    /**
     * @return void
     * @param int|string $type
     */
    protected function unset_flash($type) {
        $this->init_flash();
        unset($_SESSION['mvc_flash'][$type]);
    }
    
    /**
     * @param int|string $type
     */
    protected function get_flash($type) : ?string {
        $this->init_flash();
        return empty($_SESSION['mvc_flash'][$type]) ? null : $_SESSION['mvc_flash'][$type];
    }
    
    protected function get_all_flashes() : array {
        $this->init_flash();
        return $_SESSION['mvc_flash'];
    }
    
    /**
     * @param string $type
     * @param null|string $message
     *
     */
    public function flash($type, $message=null) : ?string {
        if (func_num_args() === 1) {
            $message = $this->get_flash($type);
            $this->unset_flash($type);
            return $message;
        }
        $this->set_flash($type, $message ?? '');
        return null;
    }
    
    public function display_flash(): void {
        $flashes = $this->get_all_flashes();
        $html = '';
        foreach ($flashes as $type => $message) {
            $classes = [];
            $classes[] = $type;
            if ($this->is_admin && $type == 'notice') {
                $classes[] = 'updated';
            }
            $html .= '
                    <div id="message" class="'.implode(' ', $classes).'">
                        <p>
                            '.$message.'
                        </p>
                    </div>';
            $this->unset_flash($type);
        }
        echo $html;
    }
    
    private function init_flash(): void {
        if (!isset($_SESSION['mvc_flash'])) {
            $_SESSION['mvc_flash'] = [];
        }
    }
    
    public function refresh(): void {
        $location = $this->current_url();
        $this->redirect($location);
    }
    
    /**
     * @param int|string $status
     */
    public function redirect(string $location, $status=302): void {
        
        // MvcDispatcher::dispatch() doesn't run until after the WP has already begun to print out HTML, unfortunately, so
        // this will almost always be done with JS instead of wp_redirect(). 
        if (headers_sent()) {
            $html = '
                <script type="text/javascript">
                    window.location = "'.$location.'";
                </script>';
            echo $html;
        } else {
            wp_redirect($location, (int) $status);
        }
        
        die();

    }
    
    /**
     * @return string
     */
    public function current_url() {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }

}

?>
