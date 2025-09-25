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
        event(new \AnourValar\EloquentFile\Events\FileVirtualChanged($model));

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
        if ($model->isDirty('name', 'weight')) {
            event(new \AnourValar\EloquentFile\Events\FileVirtualChanged($model));
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
        $class = config('eloquent_file.models.file_physical');
        $class::where('id', '=', $model->file_physical_id)->update(['linked' => false, 'updated_at' => now()]);

        event(new \AnourValar\EloquentFile\Events\FileVirtualChanged($model));
    }
}
