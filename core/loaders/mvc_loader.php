<?php

#[\AllowDynamicProperties]
abstract class MvcLoader {

    protected array $admin_controller_names = [];
    protected array $admin_controller_capabilities = [];
    protected string $core_path;
    protected \MvcDispatcher $dispatcher;
    protected \MvcFileIncluder $file_includer;

    /**
     * @var array
     *
     */
    protected $model_names = [];
    protected array $public_controller_names = [];

    /**
     * @var string[]
     *
     */
    protected $query_vars = [];

    /**
     * @var string[]
     *
     */
    public $plugin_app_paths = [];
    public string $plugin_name = '';

    /**
     * @var array
     *
     */
    protected $settings_names = [];

    public function __construct() {
    
        if (!defined('MVC_CORE_PATH')) {
            define('MVC_CORE_PATH', MVC_PLUGIN_PATH.'core/');
        }
        
        $this->core_path = MVC_CORE_PATH;

        $this->query_vars = ['mvc_controller','mvc_action','mvc_id','mvc_extra','mvc_layout'];
        
        $this->load_core();
        $this->load_plugins();
        
        $this->file_includer = new MvcFileIncluder();
        $this->file_includer->include_all_app_files('config/bootstrap.php');
        $this->file_includer->include_all_app_files('config/routes.php');

        $this->dispatcher = new MvcDispatcher();

        $this->plugin_name = MvcObjectRegistry::get_object('plugin_name');
        if ($this->plugin_name === null) {
            $this->plugin_name = '';
        }

    }

    
    /**
     * @return void
     */
    protected function load_core() {
        
        $files = [
            'mvc_error',
            'mvc_configuration',
            'mvc_directory',
            'mvc_dispatcher',
            'mvc_file',
            'mvc_file_includer',
            'mvc_model_registry',
            'mvc_object_registry',
            'mvc_settings_registry',
            'mvc_plugin_loader',
            'mvc_templater',
            'mvc_inflector',
            'mvc_router',
            'mvc_settings',
            'controllers/mvc_controller',
            'controllers/mvc_admin_controller',
            'controllers/mvc_public_controller',
            'functions/functions',
            'models/mvc_database_adapter',
            'models/mvc_database',
            'models/mvc_data_validation_error',
            'models/mvc_data_validator',
            'models/mvc_model_object',
            'models/mvc_model',
            'models/wp_models/mvc_comment',
            'models/wp_models/mvc_comment_meta',
            'models/wp_models/mvc_post_adapter',
            'models/wp_models/mvc_post',
            'models/wp_models/mvc_post_meta',
            'models/wp_models/mvc_user',
            'models/wp_models/mvc_user_meta',
            'helpers/mvc_helper',
            'helpers/mvc_form_tags_helper',
            'helpers/mvc_form_helper',
            'helpers/mvc_html_helper',
            'shells/mvc_shell',
            'shells/mvc_shell_dispatcher'
        ];
        
        foreach ($files as $file) {
            /** @psalm-suppress UnresolvableInclude Fixed core file list, exercised by public/admin bootstrap checks. */
            require_once $this->core_path.$file.'.php';
        }
        
    }
    
    /**
     * @return void
     */
    protected function load_plugins() {
    
        $plugins = $this->get_ordered_plugins();
        $plugin_app_paths = [];
        foreach ($plugins as $plugin) {
            $plugin_app_paths[$plugin] = rtrim(WP_PLUGIN_DIR, '/').'/'.$plugin.'/app/';
        }

        MvcConfiguration::set([
            'Plugins' => $plugins,
            'PluginAppPaths' => $plugin_app_paths
        ]);

        $this->plugin_app_paths = $plugin_app_paths;
    
    }
    
    protected function get_ordered_plugins() : array {
    
        $plugins = get_option('mvc_plugins', []);
        
        // Allow plugins to be loaded in a specific order by setting a PluginOrder config value like
        // this ('all' is an optional token; it includes all unenumerated plugins):
        // MvcConfiguration::set(array(
        //      'PluginOrder' => array('my-first-plugin', 'my-second-plugin', 'all', 'my-last-plugin')
        // );
        $plugin_order = MvcConfiguration::get('PluginOrder');
        if (!empty($plugin_order)) {
            $ordered_plugins = [];
            $index_of_all = array_search('all', $plugin_order);
            if ($index_of_all !== false) {
                $first_plugins = array_slice($plugin_order, 0, $index_of_all - 1);
                $last_plugins = array_slice($plugin_order, $index_of_all);
                $middle_plugins = array_diff($plugins, $first_plugins, $last_plugins);
                $plugins = array_merge($first_plugins, $middle_plugins, $last_plugins);
            } else {
                $unordered_plugins = array_diff($plugins, $plugin_order);
                $plugins = array_merge($plugin_order, $unordered_plugins);
            }
        }
        
        return $plugins;
        
    }
    
    public function init(): void {

        $this->load_controllers();
        $this->load_libs();
        $this->load_models();
        $this->load_settings();
        $this->load_functions();
    
    }
    
    /**
     * @param string $permalink
     * @param WP_Post $post
     *
     * @return string
     */
    public function filter_post_link($permalink, $post) {
        if (str_starts_with($post->post_type, 'mvc_')) {
            $model_name = substr($post->post_type, 4);
            $controller = MvcInflector::tableize($model_name);
            $model_name = MvcInflector::camelize($model_name);
            $model = MvcModelRegistry::require_model($model_name);
            $object = $model->find_one_by_post_id($post->ID);
            if ($object) {
                $url = MvcRouter::public_url(['object' => $object]);
                if ($url !== '' && $url !== '0') {
                    return $url;
                }
            }
        }
        return $permalink;
    }
    
    public function register_widgets(): void {
        foreach ($this->plugin_app_paths as $plugin_app_path) {
            $directory = $plugin_app_path.'widgets/';
            $widget_filenames = $this->file_includer->require_php_files_in_directory($directory);
  
            $path_segments_to_remove = [WP_CONTENT_DIR, '/plugins/', '/app/'];
            $plugin = str_replace($path_segments_to_remove, '', $plugin_app_path);

            foreach ($widget_filenames as $widget_file) {
                $widget_name = str_replace('.php', '', (string) $widget_file);
                $widget_class = MvcInflector::camelize($plugin).'_'.MvcInflector::camelize($widget_name);
                if (!is_a($widget_class, WP_Widget::class, true)) {
                    throw new UnexpectedValueException('An MVC widget must extend WP_Widget.');
                }
                register_widget($widget_class);
            }
        }
    }
    
    /**
     * @return void
     */
    protected function load_controllers() {
    
        foreach ($this->plugin_app_paths as $plugin_name => $plugin_app_path) {
        
            $admin_controller_filenames = $this->file_includer->require_php_files_in_directory($plugin_app_path.'controllers/admin/');
            $public_controller_filenames = $this->file_includer->require_php_files_in_directory($plugin_app_path.'controllers/');
            
            foreach ($admin_controller_filenames as $filename) {
                if (preg_match('/admin_([^\/]+)_controller\.php/', (string) $filename, $match)) {
                    $controller_name = $match[1];
                    $this->admin_controller_names[$plugin_name][] = $controller_name;
                    $capabilities = MvcConfiguration::get('admin_controller_capabilities');
                    if (empty($capabilities) || !isset($capabilities[$controller_name])) {
                        $capabilities = [$controller_name => 'administrator'];
                    }
                    $this->admin_controller_capabilities[$plugin_name][$controller_name] = $capabilities[$controller_name];
                }
            }
            
            foreach ($public_controller_filenames as $filename) {
                if (preg_match('/([^\/]+)_controller\.php/', (string) $filename, $match)) {
                    $this->public_controller_names[] = $match[1];
                }
            }
        
        }
        
    }
    
    /**
     * @return void
     */
    protected function load_libs() {
        
        foreach ($this->plugin_app_paths as $plugin_app_path) {
        
            $this->file_includer->require_php_files_in_directory($plugin_app_path.'libs/');
            
        }
        
    }
    
    /**
     * @return void
     */
    protected function load_models() {
        
        $models = [];
        
        foreach ($this->plugin_app_paths as $plugin_app_path) {
        
            $model_filenames = $this->file_includer->require_php_files_in_directory($plugin_app_path.'models/');
            
            foreach ($model_filenames as $filename) {
                $models[] = MvcInflector::class_name_from_filename($filename);
            }
        
        }
        
        $this->model_names = [];
        
        foreach ($models as $model) {
            $this->model_names[] = $model;
            $model_class = MvcInflector::camelize($model);
            if (!is_a($model_class, MvcModel::class, true)) {
                MvcError::fatal('Expected a MvcModel implementation.');
            }
            $model_instance = new $model_class();
            MvcModelRegistry::add_model($model, $model_instance);
        }
        
    }
    
    /**
     * @return void
     */
    protected function load_settings() {
        
        $settings_names = [];
        
        foreach ($this->plugin_app_paths as $plugin_app_path) {
        
            $settings_filenames = $this->file_includer->require_php_files_in_directory($plugin_app_path.'settings/');
            
            foreach ($settings_filenames as $filename) {
                $settings_names[] = MvcInflector::class_name_from_filename($filename);
            }
        
        }
        
        $this->settings_names = $settings_names;
        
    }
    
    /**
     * @return void
     */
    protected function load_functions() {
        
        foreach ($this->plugin_app_paths as $plugin_app_path) {
        
            $this->file_includer->require_php_files_in_directory($plugin_app_path.'functions/');
            
        }
    
    }

}

?>
