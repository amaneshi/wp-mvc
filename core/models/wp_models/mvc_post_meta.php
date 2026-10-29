<?php

declare(strict_types=1);

/**
 * Public framework API or convention-loaded MVC/WordPress entrypoint.
 * @api
 */
class MvcPostMeta extends MvcModel {

    #[\Override]
    public string $table = '{prefix}postmeta';

    #[\Override]
    public string $primary_key = 'meta_id';

    #[\Override]
    public array|string|null $order = 'meta_key';

    #[\Override]
    public ?string $display_field = 'meta_key';

    #[\Override]
    public ?array $belongs_to = [
        'Post' => [
            'class' => 'MvcPost',
            'foreign_key' => 'post_id'
        ]
    ];
    
}
