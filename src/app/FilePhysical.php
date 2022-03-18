<?php

namespace AnourValar\EloquentFile;

use AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\DirectAccessInterface;
use AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\VisibilityInterface;
use AnourValar\EloquentFile\Handlers\Models\FilePhysical\Type\TypeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

abstract class FilePhysical extends Model
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
        'mime_type',
    ];

    /**
     * '' => null convertation
     *
     * @var array
     */
    protected $nullable = [
        'mime_type',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'id', 'visibility', 'type', 'disk', 'path', 'path_generate', 'sha256',
        'counter', 'build', 'created_at', 'updated_at',
    ];

    /**
     * The model's attributes.
     *
     * @var array
     */
    protected $attributes = [
        'counter' => 0,
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'visibility' => 'string',
        'type' => 'string',
        'disk' => 'string',
        'path' => 'string',
        'path_generate' => 'json',
        'sha256' => 'string',
        'size' => 'integer',
        'mime_type' => 'string',
        'counter' => 'integer',
        'build' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Calculated columns
     *
     * @var array
     */
    protected $calculated = [
        'counter',
    ];

    /**
     * Immutable columns
     *
     * @var array
     */
    protected $unchangeable = [
        'visibility', 'type', 'sha256', 'size', 'mime_type',
    ];

    /**
     * Unique columns sets
     *
     * @var array
     */
    protected $unique = [

    ];

    /**
     * @see \AnourValar\EloquentValidation\ModelTrait::getAttributeNames
     *
     * @return array
     */
    public function getAttributeNames()
    {
        if (is_null(static::$attributeNames)) {
            $attributeNames = trans('eloquent-file::file_physical.attributes');
            if (! is_array($attributeNames)) {
                $attributeNames = [];
            }

            static::$attributeNames = &$attributeNames;
        }

        return static::$attributeNames;
    }

    /**
     * Bootstrap the model and its traits.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::observe(\AnourValar\EloquentFile\Observers\FilePhysicalObserver::class);
    }

    /**
     * Get the validation rules
     *
     * @return array
     */
    public function saveRules()
    {
        return [
            'visibility' => ['required', 'max:30', 'config:eloquent_file.file_physical.visibility'],
            'type' => ['required', 'max:30', 'config:eloquent_file.file_physical.type'],
            'disk' => ['required', 'max:30', 'config:filesystems.disks'],
            'path' => ['nullable', 'max:200'],
            'sha256' => ['required', 'min:64', 'max:64'],
            'size' => ['required', 'integer', 'min:0'],
            'mime_type' => ['nullable', 'max:100'],
            'build' => ['nullable', 'integer', 'min:1'],
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
        // ...
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
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function fileVirtuals()
    {
        return $this->hasMany(config('eloquent_file.models.file_virtual'));
    }

    /**
     * @return \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\VisibilityInterface
     */
    public function getVisibilityHandler(): VisibilityInterface
    {
        return \App::make(config('eloquent_file.file_physical.visibility')[$this->visibility]['bind']);
    }

    /**
     * @return \AnourValar\EloquentFile\Handlers\Models\FilePhysical\Type\TypeInterface
     */
    public function getTypeHandler(): TypeInterface
    {
        return \App::make(config('eloquent_file.file_physical.type')[$this->type]['bind']);
    }

    /**
     * Virtual attribute: visibility_details
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function visibilityDetails(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => config("eloquent_file.file_physical.visibility.{$this->visibility}"),
        );
    }

    /**
     * Virtual attribute: type_details
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function typeDetails(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => config("eloquent_file.file_physical.type.{$this->type}"),
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
            get: function ($value)
            {
                $handler = $this->getVisibilityHandler();
                if (! $handler instanceof DirectAccessInterface) {
                    throw new \LogicException('Direct access is not allowed for this file.');
                }

                if (! $this->path) {
                    throw new \LogicException('Original file is not exists.');
                }

                return $handler->directUrl($this);
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
                $handler = $this->getVisibilityHandler();
                if (! $handler instanceof DirectAccessInterface) {
                    throw new \LogicException('Direct access is not allowed for this file.');
                }

                $result = [];
                foreach (array_keys((array) $this->path_generate) as $generate) {
                    $result[$generate] = $handler->directUrl($this, $generate);
                }

                return $result;
            }
        );
    }
}
