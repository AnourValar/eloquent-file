<?php

return [
    'attributes' => [
        'id' => 'ID',
        'visibility' => 'Visibility',
        'type' => 'Type',
        'disk' => 'Disk',
        'path' => 'Path',
        'path_generate' => 'Path (generated)',
        'sha256' => 'SHA256',
        'size' => 'Size (bytes)',
        'mime_type' => 'MIME-type',
        'linked' => 'Linked',
        'build' => 'Build',
        'created_at' => 'Created at',
        'updated_at' => 'Updated at',
    ],

    'type_handlers' => [
        'image' => [
            'incorrect' => 'The file is not an image.',
        ],
    ],
];
