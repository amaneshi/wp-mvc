<?php

declare(strict_types=1);

/**
 * Public framework API or convention-loaded MVC/WordPress entrypoint.
 * @api
 */
class MvcError {

    public static function fatal(string $message): never {
        self::write('fatal', $message);
        die();
    }

    public static function warning(string $message): void {
        self::write('warning', $message);
    }

    public static function notice(string $message): void {
        self::write('notice', $message);
    }
    
    private static function write(string $type_key, string $message): void {
    
        $type_name = self::get_type($type_key);
        
        $context = self::get_context();
        $line = $context['line'];
        $file = $context['file'];
        
        $execution_context = MvcConfiguration::get('ExecutionContext');
        
        if ($execution_context == 'shell') {
        
            echo '-- '.$type_name.': '.$message."\n".'   (Thrown on line '.$line.' of '.$file.")\n";
        
        } else {
        
            echo '
                <br />
                <strong>[MVC] '.$type_name.'</strong>: '.$message.'
                <br />
                Thrown on line '.$line.' of '.$file.' <br />';
                
        }
    
    }
    
    private static function get_type(string $type_key): string {
    
        $types = [
            'fatal' => 'Fatal Error',
            'warning' => 'Warning',
            'notice' => 'Notice'
        ];
        
        return $types[$type_key];
    
    }
    
    private static function get_context(): array {
    
        $backtrace = debug_backtrace();
        
        return empty($backtrace[3]['line']) ? $backtrace[2] : $backtrace[3];
    
    }

}
