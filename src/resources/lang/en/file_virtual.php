<?php

return [
    'attributes' => [
        'id' => 'ID',
        'file_physical_id' => 'File',
        'entity' => 'Entity',
        'entity_id' => 'Entity ID',
        'name' => 'Name',
        'filename' => 'Filename',
        'title' => 'Title',
        'weight' => 'Weight',
        'details' => 'Details',
        'archived_at' => 'Archived at',
        'created_at' => 'Created at',
        'updated_at' => 'Updated at',
    ],

    'file_physical_id_not_exists' => 'The physical file does not exist.',
    'file_physical_id_incorrect_visibility' => 'The physical file has an invalid visibility.',
    'file_physical_id_incorrect_type' => 'The physical file has an invalid type.',

    'entity' => [
        'user' => [
            'name' => [
                'avatar' => 'Avatar',
            ],
        ],
    ],

    'entity_handler' => [
        'over_limit_qty' => 'Number of files ":name" cannot exceed :limit.',
        'over_limit_size' => 'Size of files ":name" cannot exceed :limit kb.',

        'user' => [
            'entity_id_not_exists' => 'The user does not exist.',
        ],
    ],
];
