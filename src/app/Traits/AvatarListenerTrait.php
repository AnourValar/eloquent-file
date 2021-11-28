<?php

namespace AnourValar\EloquentFile\Traits;

use AnourValar\EloquentFile\FileVirtual;

trait AvatarListenerTrait
{
    /**
     * Get schema with entity's avatar
     *
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @param string $generateKey
     * @return array|null
     */
    protected function avatarSchema(FileVirtual $fileVirtual, $generateKey = 'preview'): ?array
    {
        $model = \App\FileVirtual
            ::with('filePhysical')
            ->where('entity', '=', $fileVirtual['entity'])
            ->where('entity_id', '=', $fileVirtual['entity_id'])
            ->where('name', '=', $fileVirtual['name'])
            ->whereNull('archived_at')
            ->orderBy('weight', 'DESC')
            ->orderBy('id', 'DESC')
            ->first();

        if (! $model) {
            $avatar = null;
        } elseif (isset($model->url_generate[$generateKey])) {
            $avatar = ['generated' => true, 'url' => $model->url_generate[$generateKey]];
        } else {
            $avatar = ['generated' => false];
        }

        return $avatar;
    }
}
