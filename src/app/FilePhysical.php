<?php

namespace AnourValar\EloquentFile;

use AnourValar\EloquentFile\Handlers\Models\FilePhysical\Type\TypeInterface;
use AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\AdapterInterface;
use AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\DirectAccessInterface;
use AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\VisibilityInterface;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

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
     * @var array<string>
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
     * Mutators for nested JSON.
     * jsonb - sort an array by key
     * nullable - '',[] => null convertation
     * types - set the type of a value (nested)
     * sorts - sort an array (nested)
     * lists - drop array keys (nested)
     * purges - remove null elements (nested)
     *
     * @var array
     */
    protected $jsonNested = [

    ];

    /**
     * Calculated columns
     *
     * @var array
     */
    protected $computed = [
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
     * @see \AnourValar\EloquentValidation\ModelTrait::getAttributeNamesFromModelLang()
     *
     * @return array
     */
    protected function getAttributeNamesFromModelLang(): array
    {
        $attributeNames = trans('eloquent-file::file_physical.attributes');

        return is_array($attributeNames) ? $attributeNames : [];
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
    public function saveAfterValidation(\Illuminate\Validation\Validator $validator): void
    {
        // ...
    }

    /**
     * "Delete" after-validation
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     */
    public function deleteAfterValidation(\Illuminate\Validation\Validator $validator): void
    {

    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function fileVirtuals(): \Illuminate\Database\Eloquent\Relations\HasMany
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
     * Virtual attribute: file_data
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function fileData(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                $handler = $this->getVisibilityHandler();
                if (! $handler instanceof AdapterInterface) {
                    return \Storage::disk($this->disk)->get($this->path);
                }

                return $handler->getFile($this);
            }
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
            get: function ($value) {
                $handler = $this->getVisibilityHandler();
                if (! $handler instanceof DirectAccessInterface) {
                    return null;
                }

                if (! $this->path) {
                    return null;
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
            get: function ($query) {
                $handler = $this->getVisibilityHandler();
                if (! $handler instanceof DirectAccessInterface) {
                    return null;
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
