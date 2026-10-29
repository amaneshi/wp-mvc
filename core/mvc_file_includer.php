<?php

class MvcFileIncluder {

    private string $core_path = MVC_CORE_PATH;
    private array $plugin_app_paths = [];

    /**
     * @var string
     */
    private $theme_path = '';

    function __construct() {
        $this->plugin_app_paths = MvcConfiguration::get('PluginAppPaths') ?? [];
        $this->theme_path = get_stylesheet_directory();
    }

    /**
     * @param null|string $filepath
     *
     * @return false|string
     */
    public function find_theme_or_view_file($filepath): false|string
    {
        if ($filepath === null) {
            return false;
        }
		$possible_filepaths = [ $filepath, str_replace( '_', '-', $filepath ) ];
		foreach ( $possible_filepaths as $filepath ) {
			foreach(MvcConfiguration::get('Plugins') as $plugin) {
				if (file_exists($this->theme_path."/$plugin/$filepath.php")) {
					return $this->theme_path."/$plugin/$filepath.php";
				}
			}

			$app_or_cor_file = $this->find_first_app_file_or_core_file("views/$filepath.php");
			if ( $app_or_cor_file ) {
				return $app_or_cor_file;
			}
		}
		return false;
    }

    public function find_first_app_file_or_core_file(string $filepath): string|false {
        foreach ($this->plugin_app_paths as $plugin_app_path) {
            if (file_exists($plugin_app_path.$filepath)) {
                return $plugin_app_path.$filepath;
            }
        }
        if (file_exists($this->core_path.'pluggable/'.$filepath)) {
            return $this->core_path.'pluggable/'.$filepath;
        }
        if (file_exists($this->core_path.$filepath)) {
            return $this->core_path.$filepath;
        }
        return false;
    }

    /**
     * @return bool
     */
    public function require_first_app_file_or_core_file(string $filepath) {
        if ($this->include_first_app_file($filepath)) {
            return true;
        }
        if ($this->include_core_file('pluggable/'.$filepath)) {
            return true;
        }
        return $this->require_core_file($filepath);
    }

    /**
     * @return bool
     */
    public function require_all_app_files_or_core_file(string $filepath) {
        if ($this->include_all_app_files($filepath)) {
            return true;
        }
        if ($this->include_core_file('pluggable/'.$filepath)) {
            return true;
        }
        return $this->require_core_file($filepath);
    }

    /**
     * @return bool
     */
    public function require_core_file(string $filepath) {
        if ($this->include_core_file('pluggable/'.$filepath)) {
            return true;
        }
        return $this->require_file($this->core_path.$filepath);
    }

    public function require_first_app_file(string $filepath): ?bool {
        foreach ($this->plugin_app_paths as $plugin_app_path) {
            if ($this->include_file($plugin_app_path.$filepath)) {
                return true;
            }
        }
        MvcError::fatal('The file "'.$filepath.'" couldn\'t be found in any apps.');
        return null;
    }

    /**
     * @return bool
     */
    public function include_all_app_files(string $filepath) {
        $included = false;
        global $plugin_name;
        foreach ($this->plugin_app_paths as $plugin => $plugin_app_path) {
            $plugin_name = $plugin;
            if ($this->include_file($plugin_app_path.$filepath)) {
                $included = true;
            }
        }
        unset ($GLOBALS['plugin_name']);
        return $included;
    }

    public function include_core_file(string $filepath): bool {
        return $this->include_file($this->core_path.$filepath);
    }

    public function include_first_app_file(string $filepath): bool {
        foreach ($this->plugin_app_paths as $plugin_app_path) {
            if ($this->include_file($plugin_app_path.$filepath)) {
                return true;
            }
        }
        return false;
    }

    public function include_first_app_file_or_core_file(string $filepath): bool {
        foreach ($this->plugin_app_paths as $plugin_app_path) {
            if ($this->include_file($plugin_app_path.$filepath)) {
                return true;
            }
        }
        return !(!$this->include_core_file('pluggable/'.$filepath) && !$this->include_core_file($filepath));
    }

    /**
     * @return bool
     */
    public function require_all_app_files(string $filepath) {
        $included = false;
        foreach ($this->plugin_app_paths as $plugin_app_path) {
            if ($this->include_file($plugin_app_path.$filepath)) {
                $included = true;
            }
        }
        return $included;
    }

    /**
     * @param string $directory
     *
     * @return array<array-key, string>
     */
    public function get_php_files_in_directory($directory, array $options=[]) {

        $filenames = [];
        if (is_dir($directory)) {
            $filenames = isset($options['recursive']) ? $this->scandir_recursive($directory) : scandir($directory);
            $filenames = array_filter($filenames ?: [], [$this, 'is_php_file']);
        }

        return $filenames;

    }

    /**
     * @param array $options
     *
     * @return array
     */
    public function require_php_files_in_directory(string $directory, $options=[]) {

        $filenames = $this->get_php_files_in_directory($directory);
        $filepaths = [];

        foreach ($filenames as $filename) {
            $filepath = $directory.$filename;
            $filepaths[] = $filepath;
            $this->require_file($filepath);
        }

        return $filenames;

    }

    private function require_file(string $filepath): bool {
        /** @psalm-suppress UnresolvableInclude Callers resolve application files before invoking this runtime primitive. */
        require_once $filepath;
        return true;
    }

    private function include_file(string $filepath): bool {
        if (file_exists($filepath)) {
            $this->require_file($filepath);
            return true;
        }
        return false;
    }

    /**
     * @param string $filename
     */
    private function is_php_file($filename): int|false {
        return preg_match('/.+\.php$/', $filename);
    }

    /**
     * @return list<string>
     */
    private function scandir_recursive(string $directory): array {

        $file_tmp = glob($directory.'*', GLOB_MARK | GLOB_NOSORT);
        $files = [];

        foreach ($file_tmp ?: [] as $item){
            if (substr($item, -1) !== DIRECTORY_SEPARATOR) {
                $files[] = $item;
            } else {
                $files = array_merge($files, $this->scandir_recursive($item));
            }
        }

        return $files;

    }

}

?>
