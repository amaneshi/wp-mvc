<?php

class MvcTemplater {

    private ?string $template_directory = null;
    private array $template_vars = [];
    
    /**
     * @param string $directory
     */
    public function set_template_directory(?string $directory): void {
        $this->template_directory = $directory;
    }
    
    /**
     * @param string|array $variable_name_or_array
     * @param mixed $value
     */
    private function set($variable_name_or_array, $value=null): void {
        if (is_string($variable_name_or_array)) {
            $this->set_template_var($variable_name_or_array, $value);
        } else {
            foreach ($variable_name_or_array as $key => $value) {
                $this->set_template_var($key, $value);
            }
        }
    }
    
    /**
     * @param mixed $value
     */
    private function set_template_var(string|int $key, $value): void {
        $this->template_vars[$key] = $value;
    }

    /**
     * @param string $target_path
     * @param (mixed|string)[] $vars
     *
     */
    public function create(string $template, $target_path, $vars=[]): void {
        if (!empty($vars)) {
            $this->set($vars);
        }
        $template_path = $this->template_directory.$template.'.php';
        if (file_exists($template_path)) {
            extract($this->template_vars);
            ob_start();
            ob_implicit_flush(false);
            include $template_path;
            $content = ob_get_clean();
            $this->create_file($target_path, $content);
        } else {
            MvcError::fatal('A template at "'.$template_path.'" could not be found.');
        }
    }
    
    /**
     * @param string $path
     */
    private function create_file($path, string|bool $content): void {
        $file = new MvcFile($path);
        $file->write($content);
        
    }

}

?>
