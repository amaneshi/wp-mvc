<?php

class MvcUserMeta extends MvcModel {

    public string $table = '{prefix}usermeta';

    public string $primary_key = 'umeta_id';

    public array|string|null $order = 'meta_key';

    public ?string $display_field = 'meta_key';

    public ?array $belongs_to = [
        'User' => [
            'class' => 'MvcUser',
            'foreign_key' => 'user_id'
        ]
    ];
    
}

?>