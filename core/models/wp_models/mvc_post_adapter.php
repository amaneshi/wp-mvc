<?php

class MvcPostAdapter {

    /**
     * @param MvcModel $model
     */
    public function verify_settings($model): bool {
        if (empty($model->schema)) {
            MvcError::warning('The schema for '.$model->name.' is empty. It\'s likely that the plugin generating this table isn\'t working, or you may need to deactivate and activate it.  Please make sure that the table "'.$model->table.'" exists.');
        } elseif (!isset($model->schema['post_id'])) {
            MvcError::fatal('To associate posts with '.$model->name.', its table needs to have a column named "post_id" of type BIGINT(20).  Please run the following SQL to add and index the column:
<pre>
ALTER TABLE '.$model->table.' ADD COLUMN post_id BIGINT(20);
ALTER TABLE '.$model->table.' ADD INDEX (post_id);
</pre>');
        }
        if (isset($model->wp_post['post_type']) && $model->wp_post['post_type']) {
            $this->register_post_type($model);
        }
        return true;
    }

    /**
     * @param MvcModel $model
     * @param object|null|array $object
     */
    public function save_post($model, $object): void {
        $default_fields = [
            'post_type' => $this->get_post_type_key($model),
            'post_status' => 'publish',
            'post_title' => '$'.$model->display_field
        ];
        if (is_array($object)) {
            $array = $object;
            $object = new stdClass();
            foreach ($array as $key => $value) {
                $object->$key = $value;
            }
        }
        $post_id = empty($object->post_id) ? null : $object->post_id;
        if ($post_id) {
            // Check to make sure that the post exists
            $post = get_post($post_id);
            if (!$post) {
                $post_id = null;
            }
        }
        $fields = $model->wp_post['post_type']['fields'] ?? null;
        if (!is_array($fields)) {
            $fields = [];
        }
        $fields = array_merge($default_fields, $fields);
        $post_data = [];
        foreach ($fields as $key => $value) {
            if (str_starts_with((string) $value, '$')) {
                $attribute = substr((string) $value, 1);
                if (!isset($object->$attribute)) {
                    MvcError::fatal('The attribute "'.$attribute.'" was not present when trying to save the post for a '.$model->name.' object.');
                }
                $post_data[$key] = $object->$attribute;
            } elseif (str_ends_with((string) $value, '()')) {
                $method = substr((string) $value, 0, strlen((string) $value) - 2);
                if (!method_exists($model, $method)) {
                    MvcError::fatal('The method "'.$method.'" was not present when trying to save the post for a '.$model->name.' object.');
                }
                $post_data[$key] = $model->$method($object);
            } else {
                $post_data[$key] = $value;
            }
        }
        if ($post_id) {
            $post_data['ID'] = (int) $post_id;
            wp_update_post($post_data);
        } else {
            $post_id = wp_insert_post($post_data, true);
            if (is_wp_error($post_id)) {
                MvcError::fatal('The post for '.$model->name.' could not be saved ('.$post_id->get_error_message().').');
            } else {
                $model->update($object->{$model->primary_key}, ['post_id' => $post_id], ['bypass_save_post' => true]);
            }
        }
    }
    
    /**
     * @param MvcModel $model
     */
    public function register_post_type($model): void {
        $default_settings = [
            'post_type' => [],
        ];
        $settings = array_merge($default_settings, is_array($model->wp_post) ? $model->wp_post : []);
        $default_post_type_settings = [
            'args' => [],
            'fields' => []
        ];
        if (!is_array($settings['post_type'])) {
            $settings['post_type'] = [];
        }
        $settings['post_type'] = array_merge($default_post_type_settings, $settings['post_type']);
        if (!empty($settings['post_type']['key'])) {
            $settings['post_type']['key'] = $this->format_post_type_key($settings['post_type']['key']);
        } else {
            $settings['post_type']['key'] = $this->get_post_type_key($model);
        }
        if (!post_type_exists($settings['post_type']['key'])) {
            $title = MvcInflector::titleize($model->name);
            $title_plural = MvcInflector::pluralize($title);
            $title_lowercase = strtolower($title);
            $title_lowercase_plural = strtolower($title_plural);
            $default_labels = [
                'name' => _x($title_plural, 'post type general name'),
                'singular_name' => _x($title, 'post type singular name'),
                'add_new' => _x('Add New', $title_lowercase),
                'add_new_item' => __('Add New '.$title),
                'edit_item' => __('Edit '.$title),
                'new_item' => __('New '.$title),
                'all_items' => __('All '.$title_plural),
                'view_item' => __('View '.$title),
                'search_items' => __('Search '.$title_plural),
                'not_found' =>  __('No '.$title_lowercase_plural.' found'),
                'not_found_in_trash' => __('No '.$title_lowercase_plural.' found in Trash'), 
                'parent_item_colon' => '',
                'menu_name' => $title_plural
            ];
            $labels = empty($settings['post_type']['args']['labels']) ? [] : $settings['post_type']['args']['labels'];
            $labels = array_merge($default_labels, $labels);
            $default_args = [
                'labels' => $labels,
                'publicly_queryable' => false,
                'exclude_from_search' => true,
                'show_ui' => false,
                'show_in_menu' => false,
                'show_in_nav_menus' => true,
                'hierarchical' => false,
                'supports' => ['title', 'editor'],
                'menu_icon' => 'dashicons-admin-generic'
            ];
            $args = is_array($settings['post_type']['args']) ? $settings['post_type']['args'] : [];
            $args = array_merge($default_args, $args);
            register_post_type($settings['post_type']['key'], $args);
        }
    }
    
    /**
     * @param int|string $key
     */
    private function format_post_type_key($key): string {
        $key = (string) $key;
        if (!str_starts_with($key, 'mvc_')) {
            $key = 'mvc_'.$key;
        }
        return $key;
    }
    
    /**
     * @param MvcModel $model
     */
    public function get_post_type_key($model): string {
        $key = empty($model->wp_post['post_type']['key']) ? MvcInflector::underscore($model->name) : $model->wp_post['post_type']['key'];
        return $this->format_post_type_key($key);
    }

}

?>
