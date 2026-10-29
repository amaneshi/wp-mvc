<?php

class MvcUser extends MvcModel {

    public string $table = '{prefix}users';

    public string $primary_key = 'ID';

    public array|string|null $order = 'user_login';

    public ?string $display_field = 'user_login';

    public ?array $has_many = [
        'Comment' => [
            'class' => 'MvcComment',
            'foreign_key' => 'user_id'
        ],
        'Post' => [
            'class' => 'MvcPost',
            'foreign_key' => 'post_author'
        ]
    ];
    
}

?>