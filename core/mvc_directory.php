<?php

// To do: make this more robust and add error messages
class MvcDirectory {

    /**
     * @param null|string $path
     * @param array $options
     */
    public function create($path=null, $options=[]): ?bool {
        $defaults = [
            'create_nonexistent_parent_directories' => true
        ];
        $options = array_merge($defaults, $options);
        
        if (!$path) {
            return false;
        }
        if ($options['create_nonexistent_parent_directories']) {
            $directories = explode('/', dirname($path));
            $counter = count($directories);
            for ($i=1; $i<=$counter; $i++) {
                $directory_path = implode('/', array_slice($directories, 0, $i));
                if (!$this->exists($directory_path)) {
                    $this->create($directory_path);
                }
            }
        }
        
        if (!is_dir($path) && !is_file($path)) {
            mkdir($path);
        }
        return null;
        
    }
    
    /**
     * @param null|string $path
     */
    public function delete($path=null): ?bool {
    
        if (!$path) {
            return false;
        }
        
        if ($this->exists($path)) {
        
            $objects = scandir($path);
            
            foreach ($objects as $object) {
                if ($object !== '.' && $object !== '..') {
                    if (filetype($path.'/'.$object) == 'dir') {
                        $this->delete($path.'/'.$object);
                    } else {
                        unlink($path.'/'.$object);
                    }
                }
            }
            
            reset($objects);
            rmdir($path);
        
            return true;
            
        }
        return null;

    }
    
    /**
     * @param null|string $path
     */
    public function exists($path=null): bool {
        return $path !== null && is_dir($path);
    }

}

?>
