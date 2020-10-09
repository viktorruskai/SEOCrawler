<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    public const CREATED_AT = 'createdAt';
    public const UPDATED_AT = null;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'Pages';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['code', 'baseUrl', 'hasRobots', 'hasSitemap', 'createdAt'];

    /**
     * The storage format of the model's date columns.
     *
     * @var string
     */
    protected $dateFormat = 'U';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = ['createdAt'];
}