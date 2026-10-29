<?php

class MvcHtmlHelper extends MvcHelper {

    /**
     * Creates an html link
     *
     * @param string $text - title of link
     * @param mixed $url - string path or array of URL parameters i.e. [controller => ..., action => ..., id => ...]
     * @param array $options - array of HTML attributes
     * @param string $confirm_message - javascript confirmation message
     * @return string - string <a /> element
     */
    static function link(string $text, $url, $options=[], $confirm_message=''): string {
        if (is_array($url)) {
            $url = MvcRouter::public_url($url);
        }
        $defaults = [
            'href' => $url,
            'title' => $text
        ];

        $options = array_merge($defaults, $options);

        if (!empty($options['confirm'])) {
            $confirm_message = $options['confirm'];
            unset($options['confirm']);
        }

        if ($confirm_message) {
            $confirm_message = str_replace("'", "\'", $confirm_message);
            $confirm_message = str_replace('"', '\"', $confirm_message);
            $options['onclick'] = "return confirm('{$confirm_message}');";
        }

        $attributes_html = self::attributes_html($options, 'a');
        return '<a'.$attributes_html.'>'.$text.'</a>';
    }

    /**
     * @param array $options
     *
     * @param object|null $object
     */
    static function object_url($object, $options=[]): string {
        $defaults = [
            'id' => $object?->__id,
            'action' => 'show',
            'object' => $object
        ];
        $options = array_merge($defaults, $options);
        return MvcRouter::public_url($options);
    }

    /**
     * @param object|null $object
     */
    static function object_link($object, array $options=[]): string {
        $url = self::object_url($object, $options);
        $text = (string) (empty($options['text']) ? $object?->__name : $options['text']);
        return self::link($text, $url, $options);
    }

    /**
     * @param array $options
     *
     * @param object|null $object
     */
    static function admin_object_url($object, $options=[]): string {
        $defaults = [
            'id' => $object?->__id,
            'object' => $object
        ];
        $options = array_merge($defaults, $options);
        return MvcRouter::admin_url($options);
    }

    /**
     * @param object|null $object
     */
    static function admin_object_link($object, array $options=[]): string {
        $url = self::admin_object_url($object, $options);
        $text = (string) (empty($options['text']) ? $object?->__name : $options['text']);
        return self::link($text, $url);
    }

    public function __call(string $method, array $args) {
        if (isset($this->$method) && is_callable($this->$method)) {
            return call_user_func_array($this->$method, $args);
        }
    }

}

?>
