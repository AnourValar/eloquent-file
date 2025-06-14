<?php

return [
    'models' => [
        'file_physical' => App\FilePhysical::class,
        'file_virtual' => App\FileVirtual::class,
    ],

    'file_physical' => [
        'visibility' => [
            'public' => [
                'bind' => AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\PublicVisibility::class,
                'disks' => explode(',', env('ELOQUENT_FILE_PUBLIC', 's3_public')),
            ],

            'private' => [
                'bind' => AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\PrivateVisibility::class,
                'disks' => explode(',', env('ELOQUENT_FILE_PRIVATE', 's3_private')),
                'disks_generated' => explode(',', env('ELOQUENT_FILE_PUBLIC', 's3_public')),

                'proxy_route' => 'file.download',
                'proxy_route_method' => AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\PrivateVisibility::METHOD_URL_SIGNED,
            ],
        ],

        'type' => [
            'simple' => [
                'bind' => AnourValar\EloquentFile\Handlers\Models\FilePhysical\Type\SimpleType::class,
                'rules' => ['max:10240', 'extensions:zip,rar,pdf,gz,jpg,jpeg,png,gif,webp,avif,heic,svg,ico,xls,xlsx,doc,docx,ppt,pptx,xml,mp3,mp4,mov'],
                'rules_validate_mime_by_extension' => true, // "mimes" rule
            ],

            'image' => [
                'bind' => AnourValar\EloquentFile\Handlers\Models\FilePhysical\Type\ImageType::class,
                'rules' => ['max:10240', 'extensions:jpg,jpeg,png,gif,webp,avif', 'dimensions:min_width=100,min_height=100'], // heic is not supported by "dimensions"
                'rules_validate_mime_by_extension' => true,

                'keep_original' => true,
                'generate' => [
                    'preview' => [
                        'max_height' => 1000,
                        'max_width' => 1000,
                        'format' => 'jpg',
                        'quality' => 82,
                    ],
                ],
            ],
        ],
    ],

    'file_virtual' => [
        'entity' => [
            'user' => [
                'bind' => AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\UserEntity::class,
                'name' => [
                    'avatar' => [
                        'bind' => AnourValar\EloquentFile\Handlers\Models\FileVirtual\Name\SimpleName::class,
                        'title' => 'eloquent-file::file_virtual.entity.user.name.avatar',
                        'policy' => [
                            'bind' => AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\Policy\UniquePolicy::class,
                            'limit_qty' => 0,
                            'limit_size' => 0,
                        ],
                        'visibility' => 'public',
                        'types' => ['*' => 'image'],
                    ],
                ],
            ],
        ],
    ],
];
