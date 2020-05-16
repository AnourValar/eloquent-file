<?php

return [
    'models' => [
        'file_physical' => App\FilePhysical::class,
        'file_virtual' => App\FileVirtual::class,
    ],

    'file_physical' => [
        'visibility' => [
            'private' => [
                'bind' => AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\PrivateVisibility::class,
                'disks' => ['private'],

                'download_route' => 'file.download',
            ],

            'public' => [
                'bind' => AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\PublicVisibility::class,
                'disks' => ['public'],
            ],
        ],

        'type' => [
            'simple' => [
                'bind' => AnourValar\EloquentFile\Handlers\Models\FilePhysical\Type\SimpleType::class,
                'rules' => ['max:10240', 'file_not_ext:php,cgi,pl,fcgi,fpl,phtml,shtml,php2,php3,php4,php5,php6,php7,php8,asp,jsp,phar,exe,phps'],
            ],

            'image' => [
                'bind' => AnourValar\EloquentFile\Handlers\Models\FilePhysical\Type\ImageType::class,
                'rules' => ['max:10240', 'image', 'file_ext:jpg,jpeg,png,bmp,gif', 'dimensions:min_width=100,min_height=100'],

                'build' => 1,
                'preview' => [
                    'max_height' => 500,
                    'max_width' => 500,
                    'format' => 'jpg',
                    'quality' => 85,
                ]
            ],
        ],
    ],

    'file_virtual' => [
        'entity' => [
            'user' => [
                'bind' => AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\UserEntity::class,
                'name' => [
                    'avatar' => [
                        'policy' => ['bind' => AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\Policy\UniquePolicy::class],
                        'visibility' => 'public',
                        'type' => 'image',
                    ],
                ],
            ],
        ],
    ],
];
