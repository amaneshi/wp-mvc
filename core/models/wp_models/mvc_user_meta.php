<?php

declare(strict_types=1);

/**
 * Public framework API or convention-loaded MVC/WordPress entrypoint.
 * @api
 */
class MvcUserMeta extends MvcModel {

    #[\Override]
    public string $table = '{prefix}usermeta';

    #[\Override]
    public string $primary_key = 'umeta_id';

    #[\Override]
    public array|string|null $order = 'meta_key';

    #[\Override]
    public ?string $display_field = 'meta_key';

    #[\Override]
    public ?array $belongs_to = [
        'User' => [
            'class' => 'MvcUser',
            'foreign_key' => 'user_id'
        ]
    ];
    
}
