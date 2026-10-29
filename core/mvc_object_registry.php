<?php

/**
 * Public framework API or convention-loaded MVC/WordPress entrypoint.
 * @api
 */
class MvcObjectRegistry {

    /**
     * @var array
     */
    public $__objects = [];

    public static function &get_instance() : MvcObjectRegistry {
        static $instance = [];
        if (!$instance) {
            $mvc_object_registry = new MvcObjectRegistry();
            $instance[0] =& $mvc_object_registry;
        }
        /** @psalm-suppress NonVariableReferenceReturn Psalm 6 cannot model this static array-element reference; regression-tested aliasing. */
        return $instance[0];
    }

    /**
     * @param string $key
     *
     * @return mixed Registry values include both model records and framework metadata.
     */
    public static function &get_object($key) {
        /** @psalm-suppress UnsupportedReferenceUsage Preserved registry aliasing; covered by reference-contract regression tests. */
        $_this =& MvcObjectRegistry::get_instance();
        $key = MvcInflector::camelize($key);
        $return = false;
        if (isset($_this->__objects[$key])) {
            /** @psalm-suppress UnsupportedPropertyReferenceUsage Preserved registry aliasing; covered by reference-contract regression tests. */
            $return =& $_this->__objects[$key];
        }
        return $return;
    }
    
    /**
     * @param MvcModelObject|null $object
     * @param int|string $key
     * @psalm-suppress PossiblyUnusedParam Psalm cannot follow the stored reference; aliasing is regression-tested.
     */
    public static function add_object($key, &$object): bool {
        /** @psalm-suppress UnsupportedReferenceUsage Preserved registry aliasing; covered by reference-contract regression tests. */
        $_this =& MvcObjectRegistry::get_instance();
        $key = MvcInflector::camelize($key);
        if (!isset($_this->__objects[$key])) {
            $_this->__objects[$key] =& $object;
            return true;
        }
        return false;
    }

}

?>
