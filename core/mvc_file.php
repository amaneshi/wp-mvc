<?php

// To do: make this more robust and add error messages
/**
 * Public framework API or convention-loaded MVC/WordPress entrypoint.
 * @api
 */
class MvcFile {

    /**
     * @param string|null $path
     */
    public function __construct(private $path=null)
    {
    }
    
    /**
     * @param bool|string $content
     * @param string|null $path
     * @param array $options
     */
    public function write($content, $path=null, $options=[]): ?bool {
        $defaults = [
            'create_nonexistent_parent_directories' => true
        ];
        $options = array_merge($defaults, $options);
        
        if (!$path) {
            $path = $this->path;
        }
        if (!$path) {
            return false;
        }
        if ($options['create_nonexistent_parent_directories']) {
            $directory = new MvcDirectory();
            $directories = explode('/', dirname($path));
            $counter = count($directories);
            for ($i=1; $i<=$counter; $i++) {
                $directory_path = implode('/', array_slice($directories, 0, $i));
                if (!$directory->exists($directory_path)) {
                    $directory->create($directory_path);
                }
            }
        }
        $handle = fopen($path, 'w');
        if ($handle === false) {
            return false;
        }
        fwrite($handle, (string) $content);
        fclose($handle);
        return null;
    }
    
    /**
     * @param null|string $path
     */
    public function delete($path=null): ?bool {
        if (!$path) {
            $path = $this->path;
        }
        if (!$path) {
            return false;
        }
        if (is_file($path)) {
            unlink($path);
        }
        return null;
    }

}

?>
