<?php

namespace AnourValar\EloquentFile;

use AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\EntityInterface;
use AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\Policy\PolicyInterface;
use AnourValar\EloquentFile\Handlers\Models\FileVirtual\Name\NameInterface;
use AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\ProxyAccessInterface;
use AnourValar\EloquentValidation\ValidatorHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

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
        'entity', 'name', 'filename', 'content_type', 'title', 'details',
    ];

    /**
     * '' => null convertation
     *
     * @var array
     */
    protected $nullable = [
        'content_type', 'title', 'details',
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
     * The model's attributes.
     *
     * @var array
     */
    protected $attributes = [
        'weight' => 0,
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
        'weight' => 'integer',
        'details' => 'json',
        'archived_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Calculated columns
     *
     * @var array
     */
    protected $computed = [

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
            'title' => ['nullable', 'string', 'max:150'],
            'weight' => ['required', 'integer', 'min:0', 'max:32767'],
            'details' => ['nullable'],
            'archived_at' => ['nullable', 'date'],
        ];
    }

    /**
     * "Save" after-validation
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function saveAfterValidation(\Illuminate\Validation\Validator $validator): void
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

            if (! in_array($filePhysical->type, $this->name_details['types'], true)) {
                $validator->errors()->add(
                    'file_physical_id',
                    trans('eloquent-file::file_virtual.file_physical_id_incorrect_type')
                );

                return;
            }
        }

        // entity, entity_id, name
        if ($this->isDirty('entity', 'entity_id', 'name')) {
            $this->getEntityPolicyHandler()->validate($this, $validator);
            $this->getEntityHandler()->validate($this, $validator);
        }

        // entity, entity_id, name, title, details
        if ($this->isDirty('entity', 'entity_id', 'name', 'title', 'details')) {

            $this->getNameHandler()->validate($this, $validator);
        }
    }

    /**
     * "Delete" after-validation
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function deleteAfterValidation(\Illuminate\Validation\Validator $validator): void
    {
        $this->getEntityHandler()->validateDelete($this, $validator);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function filePhysical(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(config('eloquent_file.models.file_physical'));
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function entitable(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo('entity', 'entity');
    }

    /**
     * @return \AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\EntityInterface
     */
    public function getEntityHandler(): EntityInterface
    {
        return \App::make($this->entity_details['bind']);
    }

    /**
     * @return \AnourValar\EloquentFile\Handlers\Models\FileVirtual\Name\NameInterface
     */
    public function getNameHandler(): NameInterface
    {
        return \App::make($this->name_details['bind']);
    }

    /**
     * @return \AnourValar\EloquentFile\Handlers\Models\FileVirtual\Entity\Policy\PolicyInterface
     */
    public function getEntityPolicyHandler(): PolicyInterface
    {
        return \App::make($this->name_details['policy']['bind']);
    }

    /**
     * Mutator: details
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function details(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => (new ValidatorHelper())->mutateJsonb($value),
        );
    }

    /**
     * Virtual attribute: entity_details
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function entityDetails(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => config("eloquent_file.file_virtual.entity.{$this->entity}"),
        );
    }

    /**
     * Virtual attribute: name_details
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function nameDetails(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => config("eloquent_file.file_virtual.entity.{$this->entity}.name.{$this->name}"),
        );
    }

    /**
     * Virtual attribute: name_title
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function nameTitle(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => trans($this->name_details['title']),
        );
    }

    /**
     * Virtual attribute: url
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function url(): Attribute
    {
        return Attribute::make(
            get: function ($query)
            {
                if (! $this->relationLoaded('filePhysical')) {
                    throw new \LogicException('The filePhysical relation must be eager loaded.');
                }

                return $this->filePhysical->url;
            }
        );
    }

    /**
     * Virtual attribute: url_generate
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function urlGenerate(): Attribute
    {
        return Attribute::make(
            get: function ($query)
            {
                if (! $this->relationLoaded('filePhysical')) {
                    throw new \LogicException('The filePhysical relation must be eager loaded.');
                }

                return $this->filePhysical->url_generate;
            }
        );
    }

    /**
     * Virtual attribute: url_proxy
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function urlProxy(): Attribute
    {
        return Attribute::make(
            get: function ($query)
            {
                if (! $this->relationLoaded('filePhysical')) {
                    throw new \LogicException('The filePhysical relation must be eager loaded.');
                }

                $handler = $this->filePhysical->getVisibilityHandler();
                if (! $handler instanceof ProxyAccessInterface) {
                    return null;
                }

                if (! $this->filePhysical->path) {
                    return null;
                }

                return $handler->proxyUrl($this);
            }
        );
    }
}
