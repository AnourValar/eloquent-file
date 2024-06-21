<?php

return [
    'attributes' => [
        'id' => 'ID',
        'file_physical_id' => 'Файл',
        'entity' => 'Сущность',
        'entity_id' => 'ID сущности',
        'name' => 'Техн. название',
        'filename' => 'Имя',
        'content_type' => 'Тип контента',
        'title' => 'Описание',
        'weight' => 'Вес',
        'details' => 'Детали',
        'archived_at' => 'Дата архивации',
        'created_at' => 'Дата создания',
        'updated_at' => 'Дата изменения',
    ],

    'file_physical_id_not_exists' => 'Несуществующий файл.',
    'file_physical_id_incorrect_visibility' => 'Физический файл имеет недопустимую область.',
    'file_physical_id_incorrect_type' => 'Физический файл имеет недопустимый тип.',

    'entity' => [
        'user' => [
            'name' => [
                'avatar' => 'Аватар',
            ],
        ],
    ],

    'entity_handler' => [
        'over_limit_qty' => 'Кол-во файлов ":name" не может превышать :limit.',
        'over_limit_size' => 'Размер файлов ":name" не может превышать :limit кб.',

        'user' => [
            'entity_id_not_exists' => 'Несуществующий пользователь.',
        ],
    ],
];
