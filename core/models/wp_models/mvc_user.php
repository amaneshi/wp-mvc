<?php

declare(strict_types=1);

/**
 * Public framework API or convention-loaded MVC/WordPress entrypoint.
 * @api
 */
class MvcUser extends MvcModel {

    #[\Override]
    public string $table = '{prefix}users';

    #[\Override]
    public string $primary_key = 'ID';

    #[\Override]
    public array|string|null $order = 'user_login';

    #[\Override]
    public ?string $display_field = 'user_login';

    #[\Override]
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
