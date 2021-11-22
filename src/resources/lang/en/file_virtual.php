<?php

return [
    'attributes' => [
        'id' => 'ID',
        'file_physical_id' => 'File',
        'entity' => 'Entity',
        'entity_id' => 'Entity ID',
        'name' => 'Name',
        'filename' => 'Filename',
        'content_type' => 'Content type',
        'title' => 'Title',
        'weight' => 'Weight',
        'details' => 'Details',
        'archived_at' => 'Archived at',
        'created_at' => 'Created at',
        'updated_at' => 'Updated at',
    ],

    'file_physical_id_not_exists' => 'File not exists.',
    'file_physical_id_incorrect_visibility' => 'The physical file has an invalid visibility.',
    'file_physical_id_incorrect_type' => 'The physical file has an invalid type.',
    'details_not_supported' => 'Details are not available for a file.',

    'entity' => [
        'user' => [
            'name' => [
                'avatar' => 'Avatar',
            ],
        ],
    ],

    'entity_handlers' => [
        'over_limit' => 'Number of files ":name" cannot exceed :limit.',

        'user' => [
            'entity_id_not_exists' => 'User not exists.',
        ],
    ],
];
