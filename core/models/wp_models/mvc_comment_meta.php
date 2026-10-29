<?php

class MvcCommentMeta extends MvcModel {

    public string $table = '{prefix}commentmeta';

    public string $primary_key = 'meta_id';

    public array|string|null $order = 'meta_key';

    public ?string $display_field = 'meta_key';

    public ?array $belongs_to = [
        'Comment' => [
            'class' => 'MvcComment',
            'foreign_key' => 'comment_id'
        ]
    ];
    
}

?>