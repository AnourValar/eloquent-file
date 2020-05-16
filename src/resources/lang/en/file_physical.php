<?php

return [
    'attributes' => [
        'id' => 'ID',
        'visibility' => 'Visibility',
        'type' => 'Type',
        'disk' => 'Disk',
        'path' => 'Path',
        'sha256' => 'SHA256',
        'size' => 'Size',
        'mime_type' => 'MIME-type',
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
