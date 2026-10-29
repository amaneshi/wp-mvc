<?php

class MvcComment extends MvcModel {

    public string $table = '{prefix}comments';

    public string $primary_key = 'comment_ID';

    public array|string|null $order = 'comment_date DESC';

    public ?string $display_field = 'comment_content';

    public ?array $belongs_to = [
        'Post' => [
            'class' => 'MvcPost',
            'foreign_key' => 'comment_post_ID'
        ]
    ];

    public ?array $has_many = [
        'Meta' => [
            'class' => 'MvcCommentMeta',
            'foreign_key' => 'comment_id'
        ]
    ];
    
}

?>