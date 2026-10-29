<?php

class MvcModelRegistry {

    public static function require_model(string $key): MvcModel {
        $model = self::get_model($key);
        if (!$model instanceof MvcModel) {
            throw new LogicException('No MVC model registered for ' . $key);
        }
        return $model;
    }

    /**
     * @var array
     */
    public $__models = [];

    static function &get_instance() : MvcModelRegistry {
        static $instance = [];
        if (!$instance) {
            $mvc_model_registry = new MvcModelRegistry();
            $instance[0] =& $mvc_model_registry;
        }
        return $instance[0];
    }

    /**
     * @param string $key
     */
    static function &get_model($key) : object|false {
        /** @psalm-suppress UnsupportedReferenceUsage Preserved registry aliasing; covered by reference-contract regression tests. */
        $_this =& self::get_instance();
        $key = MvcInflector::camelize($key);
        $return = false;
        if (isset($_this->__models[$key])) {
            /** @psalm-suppress UnsupportedPropertyReferenceUsage Preserved registry aliasing; covered by reference-contract regression tests. */
            $return =& $_this->__models[$key];
        } elseif (class_exists($key)) {
            $_this->__models[$key] = new $key();
            /** @psalm-suppress UnsupportedPropertyReferenceUsage Preserved registry aliasing; covered by reference-contract regression tests. */
            $return =& $_this->__models[$key];
        }
        return $return;
    }

    static function &get_models() : array {
        /** @psalm-suppress UnsupportedReferenceUsage Preserved registry aliasing; covered by reference-contract regression tests. */
        $_this =& self::get_instance();
        /** @psalm-suppress UnsupportedPropertyReferenceUsage Preserved registry aliasing; covered by reference-contract regression tests. */
        $return =& $_this->__models;
        return $return;
    }
    
    /**
     * @param object $model
     * @param int|string $key
     */
    static function add_model($key, &$model): bool {
        /** @psalm-suppress UnsupportedReferenceUsage Preserved registry aliasing; covered by reference-contract regression tests. */
        $_this =& self::get_instance();
        $key = MvcInflector::camelize($key);
        if (!isset($_this->__models[$key])) {
            $_this->__models[$key] = $model;
            return true;
        }
        return false;
    }

}

?>
