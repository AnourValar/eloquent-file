<?php

namespace AnourValar\EloquentFile\Observers;

use AnourValar\EloquentFile\FileVirtual;

class FileVirtualObserver
{
    /**
     * Handle the "saving" event.
     *
     * @param  \AnourValar\EloquentFile\FileVirtual  $model
     * @return void
     */
    public function saving(FileVirtual $model)
    {
        // details
        if (! is_null($model->details) && $model->isDirty('details')) {
            $model->details = $model->getNameHandler()->canonizeDetails($model->details);
        }
    }

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
     * Handle the "updated" event.
     *
     * @param  \AnourValar\EloquentFile\FileVirtual  $model
     * @return void
     */
    public function updated(FileVirtual $model)
    {
        if ($model->isDirty('weight')) {
            \Atom::onCommit(function () use ($model) {
                event(new \AnourValar\EloquentFile\Events\FileVirtualChanged($model));
            }, $model->getConnectionName());
        }
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
        \App::make(\AnourValar\EloquentFile\Services\FileService::class)->lock($model->filePhysical);

        $class = config('eloquent_file.models.file_virtual');
        if ($model->exists) {
            $linked = true;
        } else {
            $linked = $class::where('file_physical_id', '=', $model->file_physical_id)->select(['id'])->first() ? true : false;
        }

        $class = config('eloquent_file.models.file_physical');
        $class::where('id', '=', $model->file_physical_id)->update(['linked' => $linked, 'updated_at' => now()]);

        \Atom::onCommit(function () use ($model) {
            event(new \AnourValar\EloquentFile\Events\FileVirtualChanged($model));
        }, $model->getConnectionName());
    }
}
