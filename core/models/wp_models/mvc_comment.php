<?php

declare(strict_types=1);

/**
 * Public framework API or convention-loaded MVC/WordPress entrypoint.
 * @api
 */
class MvcComment extends MvcModel {

    #[\Override]
    public string $table = '{prefix}comments';

    #[\Override]
    public string $primary_key = 'comment_ID';

    #[\Override]
    public array|string|null $order = 'comment_date DESC';

    #[\Override]
    public ?string $display_field = 'comment_content';

    #[\Override]
    public ?array $belongs_to = [
        'Post' => [
            'class' => 'MvcPost',
            'foreign_key' => 'comment_post_ID'
        ]
    ];

    #[\Override]
    public ?array $has_many = [
        'Meta' => [
            'class' => 'MvcCommentMeta',
            'foreign_key' => 'comment_id'
        ]
    ];
    
}
