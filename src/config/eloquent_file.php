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
                'disks' => explode(',', env('ELOQUENT_FILE_PUBLIC', 'public')),
            ],

            'private' => [
                'bind' => AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\PrivateVisibility::class,
                'disks' => explode(',', env('ELOQUENT_FILE_PRIVATE', 'private')),
                'disks_generated' => explode(',', env('ELOQUENT_FILE_PUBLIC', 'public')),

                'proxy_route' => 'file.download',
                'proxy_route_method' => AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\PrivateVisibility::METHOD_URL_SIGNED,
            ],
        ],

        'type' => [
            'simple' => [
                'bind' => AnourValar\EloquentFile\Handlers\Models\FilePhysical\Type\SimpleType::class,
                'rules' => ['max:10240', 'extensions:zip,rar,pdf,gz,jpg,jpeg,png,gif,xls,xlsx,doc,docx,ppt,pptx'],
            ],

            'image' => [
                'bind' => AnourValar\EloquentFile\Handlers\Models\FilePhysical\Type\ImageType::class,
                'rules' => ['max:10240', 'extensions:jpg,jpeg,png,gif', 'image', 'dimensions:min_width=100,min_height=100'],

                'build' => 1,
                'keep_original' => true,
                'generate' => [
                    'preview' => [
                        'max_height' => 500,
                        'max_width' => 500,
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
