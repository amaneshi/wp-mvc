<?php

declare(strict_types=1);

/**
 * Public framework API or convention-loaded MVC/WordPress entrypoint.
 * @api
 */
class MvcPost extends MvcModel {

    #[\Override]
    public string $table = '{prefix}posts';

    #[\Override]
    public string $primary_key = 'ID';

    #[\Override]
    public array|string|null $order = 'post_date DESC';

    #[\Override]
    public ?string $display_field = 'post_title';

    #[\Override]
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
