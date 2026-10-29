<?php

/**
 * @psalm-consistent-constructor
 * Public framework API or convention-loaded MVC/WordPress entrypoint.
 * @api
 */
class MvcSettings {

    /**
     * @var class-string<\MvcSettings>
     */
    public $name;

    /**
     * @var string
     */
    public $title;
    /**
     * @var string
     */
    public $key;

    public array $settings = [];
    
    public function __construct() {
        $this->name = static::class;
        $this->title = MvcInflector::titleize($this->name);
        $this->key = 'mvc_'.MvcInflector::underscore($this->name);
        $this->init_settings();
    }
    
    /**
     * @return void
     */
    public function description() {
    }

    public function page(): void {
        echo '<div>';
        echo '<h2>'.$this->title.'</h2>';
        echo '<form action="options.php" method="post">';
        settings_fields($this->key);
        do_settings_sections($this->key);
        echo '<input name="Submit" type="submit" value="'.esc_attr('Save Changes').'" />';
        echo '</form>';
        echo '</div>';
    }
    
    /**
     * @param string $setting_key
     */
    public function display_field($setting_key): void {
        $setting = $this->settings[$setting_key];
        $options = get_option($this->key);
        $value = $options[$setting_key] ?? null;
        if (is_null($value)) {
            if (!empty($setting['default'])) {
                $value = $setting['default'];
            } elseif (!empty($setting['default_method'])) {
                $value = $this->{$setting['default_method']}();
            }
        }
        $input_value = $value;
        if ($setting['type'] == 'checkbox') {
            $input_value = '1';
        }
        $input_options = [
            'id' => $setting['key'],
            'name' => $this->key.'['.$setting['key'].']',
            'type' => $setting['type'],
            'value' => $input_value
        ];
        if ($setting['type'] == 'checkbox' && $value) {
            $input_options['checked'] = 'checked';
        }
        if ($setting['type'] == 'select') {
            if ($setting['options']) {
                $input_options['options'] = $setting['options'];
            } elseif ($setting['options_method']) {
                $input_options['options'] = $this->{$setting['options_method']}();
            }
        }
        $html = MvcFormTagsHelper::input($setting_key, $input_options);
        echo $html;
    }
    
    public function validate_fields(array $inputs): array {
        foreach ($inputs as $setting_key => $value) {
            if (method_exists($this, 'validate_field_'.$setting_key)) {
                $inputs[$setting_key] = $this->{'validate_field_'.$setting_key}($setting_key, $value);
            } else {
                $inputs[$setting_key] = $this->validate_field($setting_key, $value);
            }
        }
        return $inputs;
    }
    
    /**
     * @param (int|string) $_setting_key
     *
     * @param mixed $value
     */
    public function validate_field($_setting_key, $value) : mixed {
        return $value;
    }
    
    /**
     * @return void
     */
    protected function init_settings() {
        if ($this->settings === []) {
            $this->settings = [];
        }
        $settings = [];
        foreach ($this->settings as $key => $setting) {
            $defaults = [
                'key' => $key,
                'type' => 'text',
                'label' => MvcInflector::titleize($key),
                'value' => null,
                'value_method' => null,
                'default' => null,
                'default_method' => null,
                'options' => null,
                'options_method' => null
            ];
            $setting = array_merge($defaults, $setting);
            $settings[$key] = $setting;
        }
        $this->settings = $settings;
    }

    public function __call(string $method_name, array $arguments) {
        if (str_starts_with($method_name, 'display_field_')) {
            $setting_key = substr($method_name, 14);
            $this->display_field($setting_key);
            return true;
        }
        if (str_starts_with($method_name, 'validate_field_')) {
            $setting_key = substr($method_name, 15);
            $this->validate_field($setting_key, $arguments[0]);
            return true;
        }
        MvcError::fatal('Undefined method: '.static::class.'::'.$method_name.'.');
        return null;
    }

}

?>