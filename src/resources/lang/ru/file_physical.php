<?php

return [
    'attributes' => [
        'id' => 'ID',
        'visibility' => 'Область',
        'type' => 'Тип',
        'disk' => 'Диск',
        'path' => 'Имя файла',
        'path_generate' => 'Имена сгенерированных файлов',
        'sha256' => 'SHA256',
        'size' => 'Размер',
        'mime_type' => 'MIME-тип',
        'counter' => 'Счётчик',
        'build' => 'Билд',
        'created_at' => 'Дата создания',
        'updated_at' => 'Дата изменения',
    ],

    'type_handlers' => [
        'image' => [
            'incorrect' => 'Файл не является изображением.',
        ],
    ],
];
