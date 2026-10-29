<?php

/**
 * Public framework API or convention-loaded MVC/WordPress entrypoint.
 * @api
 */
class MvcConfiguration {

    private array $config = [];

    /**
     * @param bool $_boot
     */
    public static function &get_instance($_boot = true) : MvcConfiguration {
        static $instance = [];
        
        if (!$instance) {
            $mvc_configuration = new MvcConfiguration();
            $instance[0] =& $mvc_configuration;
        }
        
        /** @psalm-suppress NonVariableReferenceReturn Psalm 6 cannot model this static array-element reference; regression-tested aliasing. */
        return $instance[0];
    }

    /**
     * @param array|string $config
     * @param mixed $value
     *
     */
    public static function set($config, $value = null): bool {
        $_this = MvcConfiguration::get_instance();

        if (!is_array($config)) {
            $config = [$config => $value];
        }

        foreach ($config as $name => $value) {
            if (!str_contains((string) $name, '.')) {
                $_this->config[$name] = $value;
            } else {
                $names = explode('.', (string) $name, 2);
                if (count($names) === 2) {
                    if (!isset($_this->config[$names[0]]) || !is_array($_this->config[$names[0]])) {
                        $_this->config[$names[0]] = [];
                    }
                    $_this->config[$names[0]][$names[1]] = $value;
                }
            }
        }
        
        return true;
    }

    /**
     * @param array|string $config
     *
     * @param mixed $value
     */
    public static function append($config, $value = null): bool {
        global $plugin_name;
        $_this = MvcConfiguration::get_instance();

        if (!is_array($config)) {
            $config = [$config => $value];
        }

        foreach ($config as $name => $value) {
            // The fork stores application configuration under the current plugin.
            $_this->config[$name][$plugin_name] = empty($_this->config[$name]) ? $value : array_merge($_this->config[$name], $value ?? []);
        }
        
        return true;
    }

    /**
     * @param string $config
     */
    public static function get($config) : mixed {
        $_this = MvcConfiguration::get_instance();

        if (str_contains($config, '.')) {
            $names = explode('.', $config, 2);
            $config = $names[0];
        }
        if (!isset($_this->config[$config])) {
            return null;
        }
        if (!isset($names[1])) {
            return $_this->config[$config];
        }
        return $_this->config[$config][$names[1]] ?? null;
    }

    public function __get(string $name): mixed {
        return $this->config[$name] ?? null;
    }

    public function __set(string $name, mixed $value): void {
        $this->config[$name] = $value;
    }

    public function __isset(string $name): bool {
        return isset($this->config[$name]);
    }

}

?>
