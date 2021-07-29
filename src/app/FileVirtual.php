<?php

namespace AnourValar\EloquentFile;

use AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\EntityInterface;
use AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\Policy\PolicyInterface;
use Illuminate\Database\Eloquent\Model;

abstract class FileVirtual extends Model
{
    use \AnourValar\EloquentValidation\ModelTrait;

    /**
     * The connection name for the model.
     *
     * @var string|null
     */
    protected $connection;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * Trim columns
     *
     * @var array
     */
    protected $trim = [
        'entity', 'name', 'filename', 'content_type', 'title',
    ];

    /**
     * '' => null convertation
     *
     * @var array
     */
    protected $nullable = [
        'content_type', 'title',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'file_physical_id', 'entity', 'entity_id', 'updated_at',
    ];

    /**
     * The model's attributes. (default)
     *
     * @var array
     */
    protected $attributes = [

    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'file_physical_id' => 'integer',
        'entity' => 'string',
        'entity_id' => 'integer',
        'name' => 'string',
        'filename' => 'string',
        'content_type' => 'string',
        'title' => 'string',
        'archived_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Calculated columns
     *
     * @var array
     */
    protected $calculated = [

    ];

    /**
     * Immutable columns
     *
     * @var array
     */
    protected $unchangeable = [
        'file_physical_id', 'content_type', 'entity', 'entity_id',
    ];

    /**
     * Unique columns sets
     *
     * @var array
     */
    protected $unique = [

    ];

    /**
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::observe(\AnourValar\EloquentFile\Observers\FileVirtualObserver::class);
    }

    /**
     * @see \AnourValar\EloquentValidation\ModelTrait::getAttributeNames
     *
     * @return array
     */
    public function getAttributeNames()
    {
        if (is_null(static::$attributeNames)) {
            $attributeNames = trans('eloquent-file::file_virtual.attributes');
            if (! is_array($attributeNames)) {
                $attributeNames = [];
            }

            static::$attributeNames = &$attributeNames;
        }

        return static::$attributeNames;
    }

    /**
     * Get the validation rules
     *
     * @return array
     */
    public function saveRules()
    {
        return [
            'file_physical_id' => ['required', 'integer'],
            'entity' => ['required', 'max:30', 'config:eloquent_file.file_virtual.entity'],
            'entity_id' => ['required', 'integer'],
            'name' => ['required', 'max:40'],
            'filename' => ['required', 'min:1', 'max:100'],
            'content_type' => ['nullable', 'max:100'],
            'title' => ['nullable', 'max:150'],
            'archived_at' => ['nullable', 'date'],
        ];
    }

    /**
     * "Save" after-validation
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function saveAfterValidation(\Illuminate\Validation\Validator $validator)
    {
        // name
        if (! $this->name_details) {
            $validator->errors()->add(
                'name',
                trans(
                    'eloquent-validation::validation.config',
                    ['attribute' => $validator->getDisplayableAttribute('name')]
                )
            );

            return;
        }

        // file_physical_id
        if ($this->isDirty('file_physical_id')) {
            $class = config('eloquent_file.models.file_physical');
            $filePhysical = $class::find($this->file_physical_id);

            if (! $filePhysical) {
                $validator->errors()->add(
                    'file_physical_id',
                    trans('eloquent-file::file_virtual.file_physical_id_not_exists')
                );

                return;
            }

            if ($filePhysical->visibility != $this->name_details['visibility']) {
                $validator->errors()->add(
                    'file_physical_id',
                    trans('eloquent-file::file_virtual.file_physical_id_incorrect_visibility')
                );

                return;
            }

            if ($filePhysical->type != $this->name_details['type']) {
                $validator->errors()->add(
                    'file_physical_id',
                    trans('eloquent-file::file_virtual.file_physical_id_incorrect_type')
                );

                return;
            }
        }

        // entity, entity_id, name
        if ($this->isDirty('entity', 'entity_id', 'name')) {
            $this->getEntityHandler()->validate($this, $validator);
        }
    }

    /**
     * "Delete" after-validation
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function deleteAfterValidation(\Illuminate\Validation\Validator $validator)
    {

    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function filePhysical()
    {
        return $this->belongsTo(config('eloquent_file.models.file_physical'));
    }

    /**
     * @return \AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\EntityInterface
     */
    public function getEntityHandler(): EntityInterface
    {
        return \App::make($this->entity_details['bind']);
    }

    /**
     * @return \AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\Policy\PolicyInterface
     */
    public function getEntityPolicyHandler(): PolicyInterface
    {
        return \App::make($this->name_details['policy']['bind']);
    }

    /**
     * Virtual attribute: entity_details
     *
     * @return array
     */
    public function getEntityDetailsAttribute()
    {
        return config("eloquent_file.file_virtual.entity.{$this->entity}");
    }

    /**
     * Virtual attribute: name_details
     *
     * @return array
     */
    public function getNameDetailsAttribute()
    {
        return config("eloquent_file.file_virtual.entity.{$this->entity}.name.{$this->name}");
    }

    /**
     * Virtual attribute: name_title
     *
     * @return string
     */
    public function getNameTitleAttribute()
    {
        return trans($this->name_details['title']);
    }
}
