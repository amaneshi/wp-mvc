<?php

class MvcPostMeta extends MvcModel {

    public string $table = '{prefix}postmeta';

    public string $primary_key = 'meta_id';

    public array|string|null $order = 'meta_key';

    public ?string $display_field = 'meta_key';

    public ?array $belongs_to = [
        'Post' => [
            'class' => 'MvcPost',
            'foreign_key' => 'post_id'
        ]
    ];
    
}

?>