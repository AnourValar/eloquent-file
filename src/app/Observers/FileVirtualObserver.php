<?php

namespace AnourValar\EloquentFile\Observers;

use AnourValar\EloquentFile\FileVirtual;

class FileVirtualObserver
{
    /**
     * Handle the "created" event.
     *
     * @param  \AnourValar\EloquentFile\FileVirtual  $model
     * @return void
     */
    public function created(FileVirtual $model)
    {
        $this->recalc($model);

        $model->getEntityPolicyHandler()->onCreated($model);
    }

    /**
     * Handle the "deleted" event.
     *
     * @param  \AnourValar\EloquentFile\FileVirtual  $model
     * @return void
     */
    public function deleted(FileVirtual $model)
    {
        $this->recalc($model);
    }

    /**
     * @param \AnourValar\EloquentFile\FileVirtual $model
     * @return void
     */
    private function recalc(FileVirtual $model): void
    {
        $class = config('eloquent_file.models.file_virtual');
        $qty = $class::where('file_physical_id', '=', $model->file_physical_id)->count();

        $class = config('eloquent_file.models.file_physical');
        $class::where('id', '=', $model->file_physical_id)->update(['counter' => $qty]);
        if (! $qty) {
            $filePhysical = $class::find($model->file_physical_id);
            $filePhysical->getTypeHandler()->onZero($filePhysical);
        }

        \Atom::onCommit(function () use ($model)
        {
            event(new \AnourValar\EloquentFile\Events\FileVirtualChanged($model));
        }, $model->getConnectionName());
    }
}
