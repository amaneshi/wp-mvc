<?php

/**
 * Public framework API or convention-loaded MVC/WordPress entrypoint.
 * @api
 */
class MvcRouter {
    
    /**
     * @var array
     */
    public $routes = [];

    /**
     * @param array $options
     */
    public static function public_url($options=[]): string {
        $options = apply_filters('mvc_before_public_url', $options);
        $defaults = [
            'action' => 'index',
            'controller' => null
        ];
        $options = array_merge($defaults, $options);
        $routes = self::get_public_routes();
        $controller = $options['controller'];
        $action = $options['action'];
        $matched_route = null;
        if (!empty($options['object']) && is_object($options['object'])) {
            if (!empty($options['object']->__model_name) && !$controller) {
                $model_name = $options['object']->__model_name;
                $controller = MvcInflector::tableize($model_name);
            } elseif ($controller) {
                $model_name = MvcInflector::camelize(MvcInflector::singularize($controller));
            } else {
                MvcError::warning('Incomplete arguments for MvcRouter::public_url().');
                return home_url('/');
            }
            $model = MvcModelRegistry::get_model((string) $model_name);
            if (!empty($model) && method_exists($model, 'to_url')) {
                $route_prefix = self::get_route_prefix();
                $url = home_url($route_prefix . '/');

                $method = new ReflectionMethod($model::class, 'to_url');
                $parameter_count = $method->getNumberOfParameters();

                if ($parameter_count === 2) {
                    $url .= $model->to_url($options['object'], $options);
                } else {
                    $url .= $model->to_url($options['object']);
                }
                return $url;
            }
            if (empty($options['id']) && !empty($options['object']->__id)) {
                $options['id'] = $options['object']->__id;
            }
        }
        foreach ($routes as $route) {
            $route_path = $route[0];
            $route_defaults = $route[1];
            if (!empty($route_defaults['controller']) && $route_defaults['controller'] == $controller && (!empty($route_defaults['action']) && $route_defaults['action'] == $action)) {
                $matched_route = $route;
            }
        }
        $route_prefix = self::get_route_prefix();
        $url = home_url($route_prefix . '/');
        if ($matched_route) {
            $path_pattern = $matched_route[0];
            preg_match_all('/{:([\w]+).*?}/', $path_pattern, $matches, PREG_SET_ORDER);
            $path = $path_pattern;
            foreach ($matches as $match) {
                $pattern = $match[0];
                $option_key = $match[1];
                if (isset($options[$option_key])) {
                    $value = $options[$option_key];
                    $path = preg_replace('/'.preg_quote($pattern).'/', $value, (string) $path, 1);
                } elseif (isset($options['object']) && is_object($options['object'])) {
                    if (isset($options['object']->{$option_key})) {
                        $value = $options['object']->{$option_key};
                        $path = preg_replace('/'.preg_quote($pattern).'/', $value, (string) $path, 1);
                        $path = rtrim((string) $path, '.*?');
                    }
                }
            }
            $path = rtrim((string) $path, '/').'/';
            $url .= $path;
        } else {
            $url .= $controller.'/';
            if (!empty($action) && !in_array($action, ['show', 'index'])) {
                $url .= $action.'/';
            }
            if (!empty($options['id'])) {
                $url .= $options['id'].'/';
            }
        }
        return $url;
    }

    public static function admin_url(array $options=[]): string {
        if (!empty($options['object']) && is_object($options['object'])) {
            if (empty($options['id']) && !empty($options['object']->__id)) {
                $options['id'] = $options['object']->__id;
            }
            if (empty($options['controller']) && !empty($options['object']->__model_name)) {
                $options['controller'] = MvcInflector::tableize($options['object']->__model_name);
            }
        }
        $url = get_admin_url().'admin.php';
        $params = http_build_query(self::admin_url_params($options));
        if ($params !== '' && $params !== '0') {
            $url .= '?'.$params;
        }
        return $url;
    }
    
    /**
     * @return mixed[]
     */
    public static function admin_url_params(array $options=[]): array {
        $params = [];
        if (!empty($options['controller'])) {
            $controller = preg_replace('/^admin_/', '', (string) $options['controller']);
            $params['page'] = 'mvc_'.$controller;
            if (!empty($options['action']) && $options['action'] != 'index') {
                $params['page'] .= '-'.$options['action'];
            }
        }
        if (!empty($options['id'])) {
            $params['id'] = $options['id'];
        }
        return $params;
    }
    
    /**
     * @param array|string $options
     */
    public static function admin_page_param($options=[]): string|false {
        if (is_string($options)) {
            $options = ['model' => $options];
        }
        if (!empty($options['model'])) {
            return 'mvc_'.MvcInflector::tableize($options['model']);
        }
        return false;
    }

    /**
     * @param string $route
     * @param string[] $defaults
     *
     */
    public static function public_connect( $route, $defaults = [] ): void {
        /** @psalm-suppress UnsupportedReferenceUsage Preserved registry aliasing; covered by reference-contract regression tests. */
        $_this =& MvcRouter::get_instance();
        $_this->maybe_add_route_prefix($route);
        $_this->add_public_route($route, $defaults);
    }
    
    /**
     * @param string[] $route
     *
     */
    public static function admin_ajax_connect(array $route): void {
        /** @psalm-suppress UnsupportedReferenceUsage Preserved registry aliasing; covered by reference-contract regression tests. */
        $_this =& MvcRouter::get_instance();
        $_this->add_admin_ajax_route($route);
    }

    public static function &get_instance() : MvcRouter {
        static $instance = [];
        if (!$instance) {
            $mvc_router = new MvcRouter();
            $instance[0] =& $mvc_router;
            $instance[0]->routes = [
                'public' => [],
                'admin_ajax' => []
            ];
        }
        /** @psalm-suppress NonVariableReferenceReturn Psalm 6 cannot model this static array-element reference; regression-tested aliasing. */
        return $instance[0];
    }

    /** @return array<array{0: string, 1: array}> */
    public static function &get_public_routes() {
        /** @psalm-suppress UnsupportedReferenceUsage Preserved registry aliasing; covered by reference-contract regression tests. */
        $_this =& self::get_instance();
        /** @psalm-suppress UnsupportedPropertyReferenceUsage Preserved registry aliasing; covered by reference-contract regression tests. */
        $return =& $_this->routes['public'];
        return $return;
    }

    public static function &get_admin_ajax_routes() : array {
        /** @psalm-suppress UnsupportedReferenceUsage Preserved registry aliasing; covered by reference-contract regression tests. */
        $_this =& self::get_instance();
        /** @psalm-suppress UnsupportedPropertyReferenceUsage Preserved registry aliasing; covered by reference-contract regression tests. */
        $return =& $_this->routes['admin_ajax'];
        return $return;
    }
    
    /**
     * @param string $route
     * @param array $defaults
     */
    public static function add_public_route($route, $defaults): void {
        /** @psalm-suppress UnsupportedReferenceUsage Preserved registry aliasing; covered by reference-contract regression tests. */
        $_this =& self::get_instance();
        $_this->routes['public'][] = [$route, $defaults];
    }
    
    public static function add_admin_ajax_route(array $route): void {
        /** @psalm-suppress UnsupportedReferenceUsage Preserved registry aliasing; covered by reference-contract regression tests. */
        $_this =& self::get_instance();
        if (empty($route['wp_action'])) {
            $route['wp_action'] = $route['controller'].'_'.$route['action'];
        }
        $_this->routes['admin_ajax'][] = $route;
    }

    /**
     * @param string $route
     */
    public static function maybe_add_route_prefix(&$route): void{
        $route_prefix = MvcConfiguration::get( 'CustomRoutePrefix' );
        if ( ! empty( $route_prefix ) ) {
            $route = $route_prefix . '/' . $route;
        }
    }

    public static function get_route_prefix(): mixed {
        return MvcConfiguration::get( 'CustomRoutePrefix' );
    }

}

?>
