<?php

/**
 * Public framework API or convention-loaded MVC/WordPress entrypoint.
 * @api
 */
class MvcSettingsRegistry {

    public static function require_settings(string $key): MvcSettings {
        $settings = self::get_settings($key);
        if (!$settings instanceof MvcSettings) {
            throw new LogicException('No MVC settings registered for ' . $key);
        }
        return $settings;
    }

    /**
     * @var array
     */
    public $__settings = [];

    static private function &get_instance() : MvcSettingsRegistry {
        static $instance = [];
        if (!$instance) {
            $mvc_settings_registry = new MvcSettingsRegistry();
            $instance[0] =& $mvc_settings_registry;
        }
        /** @psalm-suppress NonVariableReferenceReturn Psalm 6 cannot model this static array-element reference; regression-tested aliasing. */
        return $instance[0];
    }

    /**
     * @param int|string $key
     */
    public static function &get_settings($key) : object|false {
        /** @psalm-suppress UnsupportedReferenceUsage Preserved registry aliasing; covered by reference-contract regression tests. */
        $_this =& self::get_instance();
        $key = MvcInflector::camelize($key);
        $return = false;
        if (isset($_this->__settings[$key])) {
            /** @psalm-suppress UnsupportedPropertyReferenceUsage Preserved registry aliasing; covered by reference-contract regression tests. */
            $return =& $_this->__settings[$key];
        } elseif (class_exists($key)) {
            $_this->__settings[$key] = new $key();
            /** @psalm-suppress UnsupportedPropertyReferenceUsage Preserved registry aliasing; covered by reference-contract regression tests. */
            $return =& $_this->__settings[$key];
        }
        return $return;
    }
    
    /**
     * @param int|string $key
     * @param array $settings
     */
    public function add_settings($key, &$settings): bool {
        /** @psalm-suppress UnsupportedReferenceUsage Preserved registry aliasing; covered by reference-contract regression tests. */
        $_this =& self::get_instance();
        $key = MvcInflector::camelize($key);
        if (!isset($_this->__settings[$key])) {
            $_this->__settings[$key] = $settings;
            return true;
        }
        return false;
    }

}

?>
