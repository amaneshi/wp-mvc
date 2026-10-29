<?php

/** @psalm-consistent-constructor */
class MvcShell {

    /**
     * @var string
     *
     */
    protected $core_path = MVC_CORE_PATH;
    protected \MvcFileIncluder $file_includer;

    /**
     * @param array $args
     */
    function __construct($args=[]) {
    
        $this->file_includer = new MvcFileIncluder();
        
        $this->file_includer->require_core_file('console/color.php');
        $this->file_includer->require_core_file('console/table.php');
        
        $this->init($args);
    
    }
    
    /**
     * Empty callback method. This can be overwritten by descendant classes to 
     * add custom functionality during shell initialization
     *
     * @param array $args
     *
     * @return void
     */
    protected function init($args=[]) {
    }

    /**
     * @param array $args
     */
    public function main($args): void {
        $this->out('To handle commands without any arguments, please define a main() method in the shell.');
    }
    
    /**
     * @param null|string $string
     * @param bool $append_new_line
     */
    public function out($string, $append_new_line = true): void {
        echo $string;
        if ($append_new_line) {
            echo "\n";
        }
    }
    
    /**
     * @param int $multiplier
     *
     */
    public function nl($multiplier = 1): void {
        echo str_repeat("\n", $multiplier);
    }
    
    /**
     * @param int $length
     */
    public function hr($length = 40): void {
        echo str_repeat('-', $length)."\n";
    }
    
    /**
     * @return void
     */
    protected function load_helper(string $helper_name) {
        $helper_name .= 'Helper';
        $helper_underscore = MvcInflector::underscore($helper_name);
        
        $this->file_includer->require_first_app_file_or_core_file('helpers/'.$helper_underscore.'.php');
        
        if (class_exists($helper_name)) {
            $helper_method_name = str_replace('_helper', '', $helper_underscore);
        
            $this->{$helper_method_name} = new $helper_name();
        }
    }
    
    /**
     * @return void
     * @param string $model_name
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

}

?>
