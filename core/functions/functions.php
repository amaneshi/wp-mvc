<?php

function mvc_plugin_app_path(string $plugin): string {
    $plugin_app_paths = MvcConfiguration::get('PluginAppPaths');
    if (!isset($plugin_app_paths[$plugin]) || !is_string($plugin_app_paths[$plugin])) {
        throw new LogicException('No application path configured for ' . $plugin);
    }
    return $plugin_app_paths[$plugin];
}

function mvc_plugin_app_url(string $plugin): string {
    $abspath = rtrim(ABSPATH, '/').'/';
    $site_url = rtrim(site_url(), '/').'/';
    return str_replace($abspath, $site_url, mvc_plugin_app_path($plugin));
}

function mvc_model(string $model_name): object {
    $model_underscore = MvcInflector::underscore($model_name);
    $file_includer = new MvcFileIncluder();
    $file_includer->include_first_app_file('models/'.$model_underscore.'.php');
    if (class_exists($model_name)) {
        return new $model_name();
    }

    throw new Exception('Unable to load the "'.$model_name.'" model.');
}

/**
 * @param string $settings_name
 * @param string $setting_key
 */
function mvc_setting($settings_name, $setting_key) : mixed {
    $settings_name = 'mvc_'.MvcInflector::underscore($settings_name);
    $option = get_option($settings_name);
    return $option[$setting_key] ?? null;
}

/**
 * @param string $view
 * @param array $vars
 */
function mvc_render_to_string($view, string|array $vars=[]): string|false {
    $view_pieces = explode('/', $view);
    $model_tableized = $view_pieces[0];
    $model_camelized = MvcInflector::camelize($model_tableized);
    $controller_name = $model_camelized.'Controller';
    if (!class_exists($controller_name)) {
        $controller_name = 'MvcPublicController';
    }
    if (!is_a($controller_name, MvcController::class, true)) {
        MvcError::fatal('Expected a MvcController implementation.');
    }
    $controller = new $controller_name();
    $controller->init();
    $controller->set($vars);
    return $controller->render_to_string($view);
}

/**
 * @param array $options
 */
function mvc_public_url($options): string {
    return MvcRouter::public_url($options);
}

function mvc_admin_url(array $options): string {
    return MvcRouter::admin_url($options);
}

/**
 * @param array $options
 */
function mvc_css_url(string $plugin, string $filename, $options=[]): string {

    $defaults = [
        'add_extension' => true
    ];

    $options = array_merge($defaults, $options);
    
    if ($options['add_extension'] && !preg_match('/\.[\w]{2,4}/', $filename)) {
        $filename .= '.css';
    }
    
    return mvc_plugin_app_url($plugin).'public/css/'.$filename;
    
}

/**
 * @param array $options
 */
function mvc_js_url(string $plugin, string $filename, $options=[]): string {

    $defaults = [
        'add_extension' => true
    ];

    $options = array_merge($defaults, $options);
    
    if ($options['add_extension'] && !preg_match('/\.[\w]{2,4}/', $filename)) {
        $filename .= '.js';
    }
    
    return mvc_plugin_app_url($plugin).'public/js/'.$filename;
    
}

/**
 * @param string $plugin
 */
function mvc_add_plugin($plugin): bool {
    $added = false;
    $plugins = mvc_get_plugins();
    if (!in_array($plugin, $plugins)) {
        $plugins[] = $plugin;
        $added = true;
    }
    update_option('mvc_plugins', $plugins);
    return $added;
}

/**
 * @param string $plugin
 */
function mvc_remove_plugin($plugin): bool {
    $removed = false;
    $plugins = mvc_get_plugins();
    if (in_array($plugin, $plugins)) {
        foreach ($plugins as $key => $existing_plugin) {
            if ($plugin == $existing_plugin) {
                unset($plugins[$key]);
                $removed = true;
            }
        }
        $plugins = array_values($plugins);
    }
    update_option('mvc_plugins', $plugins);
    return $removed;
}

function mvc_get_plugins() : array {
    return get_option('mvc_plugins', []);
}

function is_mvc_page(): bool {
    global $mvc_params;
    return !empty($mvc_params['controller']);
}

?>
