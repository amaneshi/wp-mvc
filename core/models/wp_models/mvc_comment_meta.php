<?php

declare(strict_types=1);

/**
 * Public framework API or convention-loaded MVC/WordPress entrypoint.
 * @api
 */
class MvcCommentMeta extends MvcModel {

    #[\Override]
    public string $table = '{prefix}commentmeta';

    #[\Override]
    public string $primary_key = 'meta_id';

    #[\Override]
    public array|string|null $order = 'meta_key';

    #[\Override]
    public ?string $display_field = 'meta_key';

    #[\Override]
    public ?array $belongs_to = [
        'Comment' => [
            'class' => 'MvcComment',
            'foreign_key' => 'comment_id'
        ]
    ];
    
}
