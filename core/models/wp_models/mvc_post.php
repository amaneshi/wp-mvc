<?php

class MvcPost extends MvcModel {

    public string $table = '{prefix}posts';

    public string $primary_key = 'ID';

    public array|string|null $order = 'post_date DESC';

    public ?string $display_field = 'post_title';

    public ?array $has_many = [
        'Comment' => [
            'class' => 'MvcComment',
            'foreign_key' => 'comment_post_ID'
        ],
        'Meta' => [
            'class' => 'MvcPostMeta',
            'foreign_key' => 'post_id'
        ]
    ];
    
}

?>