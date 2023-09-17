<?php

namespace AnourValar\EloquentFile\Traits;

use AnourValar\EloquentFile\FileVirtual;

trait ListenerTrait
{
    /**
     * Get schema with entity's avatar
     *
     * @param \AnourValar\EloquentFile\FileVirtual $fileVirtual
     * @param string|array $generateKeys
     * @return array|null
     */
    protected function avatarSchema(FileVirtual $fileVirtual, $generateKeys = 'preview'): ?array
    {
        $model = \App\FileVirtual::query()
            ->with('filePhysical')
            ->where('entity', '=', $fileVirtual['entity'])
            ->where('entity_id', '=', $fileVirtual['entity_id'])
            ->where('name', '=', $fileVirtual['name'])
            ->whereNull('archived_at')
            ->orderBy('weight', 'DESC')
            ->orderBy('id', 'ASC')
            ->first();

        if (! $model) {
            return null;
        }

        $urlGenerate = $model->url_generate;
        foreach ((array) $generateKeys as $key) {
            if (isset($urlGenerate[$key])) {
                return ['generated' => true, 'url' => $urlGenerate[$key]];
            }
        }

        return ['generated' => false];
    }
}
