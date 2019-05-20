<?php

require_once 'mvc_loader.php';

class MvcAdminLoader extends MvcLoader {

    public $settings = null;

    public function admin_init() {
        $this->load_controllers();
        $this->load_settings();
        $this->register_settings();
        $this->dispatch();
    }

    public function dispatch() {

        global $plugin_page;

        // If the beginning of $plugin_page isn't 'mvc_', then this isn't a WP MVC-generated page
        if (substr($plugin_page, 0, 4) != 'mvc_') {
            return false;
        }

        $plugin_page_split = explode('-', $plugin_page, 2);

        $controller = $plugin_page_split[0];
        // Remove 'mvc_' from the beginning of the controller value
        $controller = substr($controller, 4);

        if (!empty($controller)) {

            global $title;

            // Necessary for flash()-related functionality in the admin area only
            if (is_admin() && session_id() == '' && !headers_sent()) {
                session_start();
            }

            $action = empty($plugin_page_split[1]) ? 'index' : $plugin_page_split[1];

            $mvc_admin_init_args = array(
                'controller' => $controller,
                'action' => $action
            );
            do_action('mvc_admin_init', $mvc_admin_init_args);

            $title = MvcInflector::titleize($controller);
            if (!empty($action) && $action != 'index') {
                $title = MvcInflector::titleize($action) . ' &lsaquo; ' . $title;
            }
            $title = apply_filters('mvc_admin_title', $title);

        }

    }

    public function add_menu_pages() {

        global $_registered_pages;

        $sub_pages = array();
        $menu_position = 12;

        $menu_position = apply_filters('mvc_menu_position', $menu_position);

        foreach (mvc_get_plugins() as $plugin_name) {

            $admin_pages = MvcConfiguration::get('AdminPages.' . $plugin_name);
            $single_root_page = MvcConfiguration::get('SingleRootMenu.' . $plugin_name);
            $is_nested_menu = is_array($single_root_page);

            $root_page_slug = null;
            if ($is_nested_menu) {
                $add_pages = array();
                $root_page_slug = isset($single_root_page['controller'])
                    ? MvcInflector::underscore(MvcInflector::camelize($plugin_name)) . '_root_page'
                    : 'mvc_' . $this->admin_controller_names[$plugin_name][0];
                $this->add_root_menu_page($single_root_page, $plugin_name, $root_page_slug);
            }

            foreach ($this->admin_controller_names[$plugin_name] as $controller_name) {

                if (isset($admin_pages[$controller_name])) {
                    if (empty($admin_pages[$controller_name]) || !$admin_pages[$controller_name]) {
                        continue;
                    }
                    $pages = $admin_pages[$controller_name];
                } else {
                    $pages = null;
                }

                $hide_menu = isset($pages['hide_menu']) ? $pages['hide_menu'] : false;
                $menu_position_current = isset($pages['position']) ? $pages['position'] : $menu_position;

                if (!$hide_menu) {

                    $controller_titleized = __(MvcInflector::titleize($controller_name), $this->plugin_name);

                    $admin_controller_name = 'admin_' . $controller_name;

                    $top_level_handle = 'mvc_' . $controller_name;

                    $method = $admin_controller_name . '_index';
                    $this->dispatcher->{$method} = function () use ($admin_controller_name) {
                        MvcDispatcher::dispatch(array(
                            'controller' => $admin_controller_name,
                            'action' => 'index'
                        ));
                    };
                    $capability = !empty($pages['capability']) ? $pages['capability'] : $this->admin_controller_capabilities[$plugin_name][$controller_name];
                    $label = __(!empty($pages['label']) ? $pages['label'] : $controller_titleized, $plugin_name);

                    if ($is_nested_menu) {

                        $sub_pages[] = array(
                            'parent_slug' => $root_page_slug,
                            'page_title' => $label,
                            'menu_title' => $label,
                            'capability' => $capability,
                            'menu_slug' => $top_level_handle,
                            'function' => array($this->dispatcher, $method),
                            'order' => isset($pages['position']) ? $pages['position'] : 0
                        );

                    } else {
                        $menu_icon = isset($pages['icon']) ? $pages['icon'] : 'dashicons-admin-generic';

                        /* check if there is a corresponding model with a menu_icon post type argument */
                        try {
                            $model_name = MvcInflector::singularize(MvcInflector::camelize($controller_name));
                            $model = mvc_model($model_name);
                            if (isset($model->wp_post['post_type']['args']['menu_icon'])) {
                                $menu_icon = $model->wp_post['post_type']['args']['menu_icon'];
                            }
                        } catch (Exception $e) {
                            ; //not every controller must have a corresponding model, continue silently
                        }

                        if (empty($pages['parent_slug'])) {
                            add_menu_page(
                                $label,
                                $label,
                                $capability,
                                $top_level_handle,
                                array($this->dispatcher, $method),
                                $menu_icon,
                                $menu_position_current
                            );
                        } else {
                            $sub_pages[] = array(
                                'parent_slug' => $pages['parent_slug'],
                                'page_title' => $label,
                                'menu_title' => $label,
                                'capability' => $capability,
                                'menu_slug' => $top_level_handle,
                                'function' => array($this->dispatcher, $method),
                                'order' => isset($pages['order']) ? $pages['order'] : 0
                            );
                        }
                    }

                    $processed_pages = $this->process_admin_pages($controller_name, $pages, $plugin_name);

                    foreach ($processed_pages as $key => $admin_page) {

                        $method = $admin_controller_name . '_' . $admin_page['action'];

                        if (!method_exists($this->dispatcher, $method)) {
                            $this->dispatcher->{$method} = function () use ($admin_controller_name, $admin_page) {
                                MvcDispatcher::dispatch(array(
                                    'controller' => $admin_controller_name,
                                    'action' => $admin_page['action']
                                ));
                            };
                        }

                        $page_handle = $top_level_handle . '-' . $key;
                        $parent_slug = !empty($pages['parent_slug']) ? $pages['parent_slug'] : $top_level_handle;
                        $parent_slug = empty($admin_page['parent_slug']) ? $parent_slug : $admin_page['parent_slug'];

                        if ($admin_page['in_menu']) {
                            if ($is_nested_menu) {
                                $add_pages[] = array(
                                    'menu_title' => __($admin_page['label'] . ' ' . MvcInflector::singularize($controller_titleized), $plugin_name),
                                    'menu_slug' => $page_handle,
                                    'function' => array($this->dispatcher, $method),
                                    'href' => MvcRouter::admin_url(array('controller' => $admin_controller_name, 'action' => $admin_page['action'])),
                                    'order' => isset($admin_page['order']) ? $admin_page['order'] : 0
                                );
                            } else {
                                $sub_pages[] = array(
                                    'parent_slug' => $parent_slug,
                                    'page_title' => __($admin_page['label'] . ' &lsaquo; ' . $controller_titleized, $plugin_name),
                                    'menu_title' => __($admin_page['label'], $plugin_name),
                                    'capability' => $admin_page['capability'],
                                    'menu_slug' => $page_handle,
                                    'function' => array($this->dispatcher, $method),
                                    'order' => isset($admin_page['order']) ? $admin_page['order'] : 0
                                );
                            }
                        } else {
                            // It looks like there isn't a more native way of creating an admin page without
                            // having it show up in the menu, but if there is, it should be implemented here.
                            // To do: set up capability handling and page title handling for these pages that aren't in the menu
                            $hookname = get_plugin_page_hookname($page_handle, '');
                            if (!empty($hookname)) {
                                add_action($hookname, array($this->dispatcher, $method));
                            }
                            $_registered_pages[$hookname] = true;
                        }
                    }
                    $menu_position++;
                }
            }

            //sort sub pages
            $sub_pages_order = array_column($sub_pages, 'order');
            $sub_pages_title = array_column($sub_pages, 'menu_title');
            array_multisort($sub_pages_order, SORT_ASC, $sub_pages_title, SORT_ASC, $sub_pages);

            //add sub pages
            foreach ($sub_pages as $page) {
                add_submenu_page(
                    $page['parent_slug'],
                    $page['page_title'],
                    $page['menu_title'],
                    $page['capability'],
                    $page['menu_slug'],
                    $page['function']
                );
            }

            if (!empty($add_pages)) {
                //sort add pages
                $add_pages_order = array_column($add_pages, 'order');
                $add_pages_title = array_column($add_pages, 'menu_title');
                array_multisort($add_pages_order, SORT_ASC, $add_pages_title, SORT_ASC, $add_pages);

                foreach ($add_pages as $add_page) {
                    $hookname = get_plugin_page_hookname($add_page['menu_slug'], '');
                    if (!empty($hookname)) {
                        add_action($hookname, $add_page['function']);
                    }
                    $_registered_pages[$hookname] = true;
                }

                add_action('wp_before_admin_bar_render', function () use ($add_pages, $plugin_name) {
                    $this->add_admin_bar_pages($add_pages, $plugin_name);
                });
            }
            add_filter('parent_file', function ($parent_file) use ($root_page_slug) {
                return $this->set_active_parent_menu_slug($parent_file, $root_page_slug);
            });
            add_filter('submenu_file', function ($submenu_file) use ($root_page_slug) {
                return $this->set_active_submenu_slug($submenu_file, $root_page_slug);
            });
        }
    }

    public function set_active_parent_menu_slug($parent_file, $root_page_slug) {
        global $plugin_page, $pagenow;
        if ($pagenow === 'admin.php' and substr($plugin_page, 0, 4) === 'mvc_')
            $parent_file = $root_page_slug ?: explode('-', $plugin_page, 2)[0];

        return $parent_file;
    }

    public function set_active_submenu_slug($submenu_file, $root_page_slug) {
        global $plugin_page, $pagenow;
        if ($pagenow === 'admin.php' and substr($plugin_page, 0, 4) === 'mvc_')
            $submenu_file = $root_page_slug ? explode('-', $plugin_page, 2)[0] : null;

        return $submenu_file;
    }

    public function add_root_menu_page($root_page, $plugin_name, $root_page_slug) {

        $default_root_page = array(
            'page_title' => null,
            'menu_title' => MvcInflector::titleize($plugin_name) . ' Plugin',
            'capability' => 'administrator',
            'menu_slug' => $root_page_slug,
            'controller' => null,
            'action' => null,
            'icon_url' => 'dashicons-admin-generic',
            'position' => '12'
        );

        $root_page = array_merge($default_root_page, $root_page);
        $controller = $root_page['controller'];
        $action = $root_page['action'] ?: 'index';

        $root_page['function'] = array($this->dispatcher, 'admin_' . ($controller ?: substr($root_page_slug, 4)) . '_' . $action);

        add_menu_page(
            $root_page['page_title'],
            __($root_page['menu_title'], $plugin_name),
            $root_page['capability'],
            $root_page['menu_slug'],
            $root_page['function'],
            $root_page['icon_url'],
            $root_page['position']
        );
    }

    public function add_admin_bar_pages($add_pages, $plugin_name) {

        global $wp_admin_bar;
        $root_id = MvcInflector::underscore(MvcInflector::camelize($plugin_name));
        $root_title = __(MvcInflector::titleize($plugin_name), $plugin_name);
        $wp_admin_bar->add_menu(array(
            'id' => $root_id,
            'title' => '<span class="ab-icon dashicons dashicons-sos"></span>' . $root_title,
        ));
        foreach ($add_pages as $add_page) {

            $wp_admin_bar->add_node(array(
                'parent' => $root_id,
                'id' => $add_page['menu_slug'],
                'title' => $add_page['menu_title'],
                'href' => $add_page['href'],
            ));
        }
    }

    protected function process_admin_pages($controller_name, $pages, $plugin_name) {

        $titleized = MvcInflector::titleize($controller_name);

        $default_pages = array(
            'add' => array(
                'label' => __('Add New', 'wpmvc')
            ),
            'delete' => array(
                'label' => __('Delete', 'wpmvc') . ' ' . $titleized,
                'in_menu' => false
            ),
            'edit' => array(
                'label' => __('Edit', 'wpmvc') . ' ' . $titleized,
                'in_menu' => false
            )
        );

        if (!$pages) {
            $pages = $default_pages;
        }

        $processed_pages = array();

        foreach ($pages as $key => $value) {
            if (is_int($key)) {
                $key = $value;
                $value = array();
            }
            if (!is_array($value)) {
                continue;
            }
            $capability = $this->admin_controller_capabilities[$plugin_name][$controller_name];
            $defaults = array(
                'action' => $key,
                'in_menu' => true,
                'label' => MvcInflector::titleize($key),
                'capability' => $capability
            );
            if (isset($default_pages[$key])) {
                $value = array_merge($default_pages[$key], $value);
            }
            $value = array_merge($defaults, $value);
            $processed_pages[$key] = $value;
        }

        return $processed_pages;
    }

    public function init_settings() {
        $this->settings = array();
        if (!empty($this->settings_names) && empty($this->settings)) {
            foreach ($this->settings_names as $settings_name) {
                $instance = MvcSettingsRegistry::get_settings($settings_name);
                $this->settings[$settings_name] = array(
                    'settings' => $instance->settings
                );
            }
        }
    }

    public function register_settings() {
        $this->init_settings();
        foreach ($this->settings as $settings_name => $settings) {
            $instance = MvcSettingsRegistry::get_settings($settings_name);
            $title = $instance->title;
            $settings_key = $instance->key;
            $section_key = $settings_key . '_main';
            add_settings_section($section_key, '', array($instance, 'description'), $settings_key);
            register_setting($settings_key, $settings_key, array($instance, 'validate_fields'));
            foreach ($instance->settings as $setting_key => $setting) {
                add_settings_field($setting_key, $setting['label'], array($instance, 'display_field_' . $setting_key), $settings_key, $section_key);
            }
        }
    }

    public function add_settings_pages() {
        $this->init_settings();
        foreach ($this->settings as $settings_name => $settings) {
            $title = MvcInflector::titleize($settings_name);
            $title = str_replace(' Settings', '', $title);
            $title = __($title, $this->plugin_name);
            $instance = MvcSettingsRegistry::get_settings($settings_name);
            add_options_page($title, $title, 'manage_options', $instance->key, array($instance, 'page'));
        }
    }

    public function add_admin_ajax_routes() {
        $routes = MvcRouter::get_admin_ajax_routes();
        if (!empty($routes)) {
            foreach ($routes as $route) {
                $route['is_admin_ajax'] = true;
                $method = 'admin_ajax_' . $route['wp_action'];
                $this->dispatcher->{$method} = function () use ($route) {
                    MvcDispatcher::dispatch(array(
                        'controller' => $route['controller'],
                        'action' => $route['action']
                    ));
                    die();
                };
                add_action('wp_ajax_' . $route['wp_action'], array($this->dispatcher, $method));
            }
        }

    }

}

?>
