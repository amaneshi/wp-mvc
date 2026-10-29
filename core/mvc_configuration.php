<?php

// Configuration keys are supplied by applications at runtime.
/** @psalm-no-seal-properties */
#[\AllowDynamicProperties]
class MvcConfiguration {

    /**
     * @param bool $boot
     */
    static function &get_instance($boot = true) : MvcConfiguration {
        static $instance = [];
        
        if (!$instance) {
            $mvc_configuration = new MvcConfiguration();
            $instance[0] =& $mvc_configuration;
        }
        
        return $instance[0];
    }

    /**
     * @param (mixed|string[])[]|string $config
     * @param null|string $value
     *
     */
    static function set($config, $value = null): bool {
        /** @psalm-suppress UnsupportedReferenceUsage Preserved registry aliasing; covered by reference-contract regression tests. */
        $_this =& MvcConfiguration::get_instance();

        if (!is_array($config)) {
            $config = [$config => $value];
        }

        foreach ($config as $name => $value) {
            if (!str_contains((string) $name, '.')) {
                $_this->{$name} = $value;
            } else {
                $names = explode('.', (string) $name, 2);
                if (count($names) === 2) {
                    $_this->{$names[0]}[$names[1]] = $value;
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
    static function append($config, $value = null): bool {
        global $plugin_name;
        /** @psalm-suppress UnsupportedReferenceUsage Preserved registry aliasing; covered by reference-contract regression tests. */
        $_this =& MvcConfiguration::get_instance();

        if (!is_array($config)) {
            $config = [$config => $value];
        }

        foreach ($config as $name => $value) {
            $_this->{$name}[$plugin_name] = empty($_this->{$name}) ? $value : array_merge($_this->{$name}, $value ?? []);
        }
        
        return true;
    }

    /**
     * @param string $config
     */
    static function get($config) : mixed {
        /** @psalm-suppress UnsupportedReferenceUsage Preserved registry aliasing; covered by reference-contract regression tests. */
        $_this =& MvcConfiguration::get_instance();

        if (str_contains($config, '.')) {
            $names = explode('.', $config, 2);
            $config = $names[0];
        }
        if (!isset($_this->{$config})) {
            return null;
        }
        if (!isset($names[1])) {
            return $_this->{$config};
        }
        if (count($names) === 2 && isset($_this->{$config}[$names[1]])) {
            return $_this->{$config}[$names[1]];
        }
        return null;
    }

}

?>
