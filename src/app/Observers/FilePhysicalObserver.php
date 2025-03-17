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
        if ($model->linked) {
            $class = config('eloquent_file.models.file_virtual');
            foreach ($class::where('file_physical_id', '=', $model->id)->get() as $item) {
                $item->delete();
            }
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
        \Atom::onCommit(function () use ($model) {
            foreach (array_merge([['disk' => $model->disk, 'path' => $model->path]], (array) $model->path_generate) as $item) {
                if (! mb_strlen((string) $item['path'])) {
                    continue;
                }

                \Storage::disk($item['disk'])->delete($item['path']); // alt: file_deletes [in db] + cron = eventual consistency
            }
        }, $model->getConnectionName());
    }
}
