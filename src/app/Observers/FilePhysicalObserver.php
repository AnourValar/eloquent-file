<?php

namespace AnourValar\EloquentFile\Observers;

class FilePhysicalObserver
{
    /**
     * Handle the "deleting" event.
     *
     * @param  \AnourValar\EloquentFile\FilePhysical  $model
     * @return void
     */
    public function deleting(\AnourValar\EloquentFile\FilePhysical $model)
    {
        $class = config('eloquent_file.models.file_virtual');

        foreach ($class::where('file_physical_id', '=', $model->id)->get() as $item) {
            $item->validateDelete()->delete();
        }
    }

    /**
     * Handle the "deleted" event.
     *
     * @param  \AnourValar\EloquentFile\FilePhysical  $model
     * @return void
     */
    public function deleted(\AnourValar\EloquentFile\FilePhysical $model)
    {
        foreach (array_merge([$model->path], (array)$model->path_generate) as $item) {
            if (! mb_strlen($item)) {
                continue;
            }

            \Atom::onCommit(
                function () use ($model, $item)
                {
                    if (\Storage::disk($model->disk)->exists($item)) {
                        \Storage::disk($model->disk)->delete($item);
                    }
                },
                $model->getConnectionName()
            );
        }
    }
}
