<?php

/**
 *  Tool to automate deletion of files created by the Generate shell
 *
 * Public framework API or convention-loaded MVC/WordPress entrypoint.
 * @api
 */
class DestroyShell extends MvcShell {

    /**
     * Deletes controller source files
     * wpmvc destroy <plugin> <resource>
     */
    public function controllers(array $args): void {
        [$plugin, $name] = $this->get_plugin_model_args($args);
        $this->destroy_controllers($plugin, $name);
    }

    /**
     * Deletes model source files
     * wpmvc destroy <plugin> <resource>
     */
    public function model(array $args): void {
        [$plugin, $name] = $this->get_plugin_model_args($args);
        $this->destroy_model($plugin, $name);
    }

    /**
     * Deletes model, controller, and view files for a resource
     * wpmvc destroy <plugin> <resource>
     */
    public function scaffold(array $args): void {
        [$plugin, $name] = $this->get_plugin_model_args($args);
        $this->destroy_controllers($plugin, $name);
        $this->destroy_model($plugin, $name);
        $this->destroy_views($plugin, $name);
    }

    /**
     * Deletes view source files for a resource
     * wpmvc destroy views <plugin> <resource>
     */
    public function views(array $args): void {
        [$plugin, $name] = $this->get_plugin_model_args($args);
        $this->destroy_views($plugin, $name);
    }
    
    /**
     * Delete a generated widget
     */
    public function widget(array $args): void {
        [$plugin, $name] = $this->get_plugin_model_args($args);
        $this->destroy_widget($plugin, $name);   
    }
    
    private function destroy_controllers(string|int|null $plugin, string|int|null $name): void {
        $plugin_app_path = $this->get_plugin_app_path($plugin);
        $file = new MvcFile();
        $name_tableized = MvcInflector::tableize($name);
        $target_directory = $plugin_app_path.'controllers/';
        $file->delete($target_directory.$name_tableized.'_controller.php');
        $file->delete($target_directory.'admin/admin_'.$name_tableized.'_controller.php');
    }
    
    private function destroy_model(string|int|null $plugin, string|int|null $name): void {
        $plugin_app_path = $this->get_plugin_app_path($plugin);
        $file = new MvcFile();
        $name_underscore = MvcInflector::underscore($name);
        $target_path = $plugin_app_path.'models/'.$name_underscore.'.php';
        $file->delete($target_path);
    }
    
    private function destroy_views(string|int|null $plugin, string|int|null $name): void {
        $plugin_app_path = $this->get_plugin_app_path($plugin);
        $name_tableized = MvcInflector::tableize($name);
        $public_directory = $plugin_app_path.'views/'.$name_tableized;
        $admin_directory = $plugin_app_path.'views/admin/'.$name_tableized;
        $directory = new MvcDirectory();
        $directory->delete($public_directory);
        $directory->delete($admin_directory);
    }
    
    private function destroy_widget(string|int|null $plugin, string|int|null $name): void {
        $plugin_app_path = $this->get_plugin_app_path($plugin);
        $file = new MvcFile();
        $name_underscore = MvcInflector::underscore($name);
        $target_path = $plugin_app_path.'widgets/'.$name_underscore.'.php';
        $file->delete($target_path);
    }
    
    private function get_plugin_model_args(array $args): array {
        if (empty($args[0]) || empty($args[1])) {
            MvcError::fatal('Please specify a plugin and name for the model.');
        }
        $args[0] = str_replace('_', '-', MvcInflector::underscore($args[0]));
        return $args;
    }
    
    private function get_plugin_path(string|int|null $plugin_name): string {
        $plugin_underscored = MvcInflector::underscore($plugin_name);
        $plugin_hyphenized = str_replace('_', '-', $plugin_underscored);
        return WP_PLUGIN_DIR.'/'.$plugin_hyphenized.'/';
    }
    
    private function get_plugin_app_path(string|int|null $plugin_name): string {
        $plugin_path = $this->get_plugin_path($plugin_name);
        return $plugin_path.'app/';
    }

}

?>