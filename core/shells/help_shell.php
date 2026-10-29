<?php

/**
 * Help system for the WPMVC Console.
 * Also provides the capability for shells to be self documenting.
 *
 * Public framework API or convention-loaded MVC/WordPress entrypoint.
 * @api
 */
class HelpShell extends MvcShell {

    /**
     * @param string $name
     */
    protected function get_shell_meta($name): \stdClass {
        $exclude_methods = [
            '__construct',
            '__call',
            'init',
            'out',
            'nl',
            'hr'
        ];
        
        $name = MvcInflector::underscore($name);

        $shell = new stdClass();
        $shell->name = $name;
        $shell->shell_name = $name.'_shell';
        $shell->class_name = MvcInflector::camelize($shell->shell_name);
        $shell->title = MvcInflector::titleize($shell->name);
        $shell->methods = [];

        $shell_path = 'shells/'.$shell->shell_name.'.php';

        $file_path = $this->file_includer->find_first_app_file_or_core_file($shell_path);

        if (!$file_path) {
            throw new InvalidArgumentException($shell->shell_name." could not be found.\n");
        }

        $this->file_includer->require_first_app_file_or_core_file($shell_path);

        $class_name = $shell->class_name;
        if (!class_exists($class_name)) {
            throw new InvalidArgumentException('Shell class could not be loaded: ' . $class_name);
        }
        $class = new ReflectionClass($class_name);
        $methods = $class->getMethods(ReflectionMethod::IS_PUBLIC);

        $shell->doc = $this->parse_doc_block($class->getDocComment());

        if (empty($shell->doc)) {
            $shell->doc = '(No Documentation)';
        }

        foreach ($methods as $method) {
            $method_name = $method->getName();
            $method_doc_block = $this->parse_doc_block($method->getDocComment());

            if (!in_array($method_name, $exclude_methods)) {
                if (empty($method_doc_block)) {
                    $method_doc_block = '(No Documentation)';
                }

                if ($method_name == 'main') {
                    $method_name = '(default)';
                }

                $shell->methods[$method_name] = $method_doc_block;
            }
        }
        
        ksort($shell->methods);
        
        return $shell;
    }

    /**
     * @return array<array-key, list<string>>
     */
    protected function get_available_shells(): array {
        $exclude = [
            'mvc_shell.php',
            'mvc_shell_dispatcher.php'
        ];

        $pluginAppPaths = MvcConfiguration::get('PluginAppPaths');
        $pluginAppPaths['core'] = MVC_CORE_PATH;

        $shells = [];

        foreach ($pluginAppPaths as $plugin => $path) {
            $path .= '/shells';

            $files = $this->file_includer->get_php_files_in_directory($path);

            $key = MvcInflector::camelize($plugin);

            $shells[$plugin] = [];

            foreach ($files as $file) {
                if (!in_array($file, $exclude)) {
                    $name = str_replace('_shell.php', '', $file);
                    $shells[$plugin][] = $name;
                }
            }
        }

        return $shells;
    }

    /**
     * Get a list of the available shells.
     * Also is executed if the wpmvc console is run with no arguments.
     *
     * @param mixed $args
     */
    #[\Override]
    public function main($args): void {
        $shells = $this->get_available_shells();
        $this->out('Available Shells:');

        $table = new Console_Table(
            CONSOLE_TABLE_ALIGN_LEFT,
            ' ',
            1,
            null,
            true /* this is important when using Console_Color */
        );

        foreach ($shells as $plugin => $shells) {

            $plugin_label = MvcInflector::camelize(MvcInflector::underscore($plugin));
            $counter = count($shells);

            for ($i = 0; $i < $counter; $i++) {
                if ($i > 0) {
                    $plugin_label = ' ';
                }
                $shell_name = MvcInflector::camelize($shells[$i]);
                $table->addRow([
                    $plugin_label,
                    Console_Color::convert('%_'.$shell_name.'%n')
                ]);
            }

            $table->addSeparator();
        }

        $this->out($table->getTable());

        $this->out('To get information about a shell try:');
        $this->out("\n\twpmvc help shell <name_of_shell>");
    }

    /**
     * @param false|string $text
     */
    protected function parse_doc_block($text): string {
        $doc = preg_replace('/^\\s*(\\/|\\*+)[\\*]*([\\/]|[\\s{1}]\\@.*|[\\ ]*|)/um', '', (string) $text);

        return trim((string) $doc);
    }

    /**
     * Show documentation for a shell.
     * Usage:
     * wpmvc Help Shell <shell_name> [command_name]
     * wpmvc Help Shell Generate
     * wpmvc Help Shell Generate Scaffold
     * @param mixed $args
     */
    public function shell($args): void {
        [$name, $method] = $args;

        if (empty($name)) {
            $this->out('No shell given');
            return;
        }

        try {
            $shell = $this->get_shell_meta($name);
        } catch (Exception $ex) {
            $this->out('Error:');
            $this->out($ex->getMessage());
            return;
        }
        
        $this->nl();
        $this->out(Console_Color::convert('%UShells > %n%U%9'.$shell->title.'%n'));
        $this->nl();
        $this->out($shell->doc);
        $this->nl(2);
        $this->out('Commands:');

        $table = new Console_Table(
            CONSOLE_TABLE_ALIGN_LEFT,
            ' ',
            1,
            null,
            true // This is important when using Console_Color
        );

        if ($method == 'default') {
            $method = '(default)';
        }
        
        if (!empty($method) && !empty($shell->methods[$method])) {
            $table->addRow([Console_Color::convert('%9'.$method.'%n'), $shell->methods[$method]]);
        } else {
            foreach ($shell->methods as $method => $doc) {
                $table->addRow([Console_Color::convert('%9'.$method.'%n'), $doc]);
                $table->addSeparator();
            }
        }

        $this->out($table->getTable());
    }

}

?>
