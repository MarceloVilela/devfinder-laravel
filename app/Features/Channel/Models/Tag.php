<?php

declare(strict_types=1);

namespace App\Features\Channel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @property string $name */
final class Tag extends Model
{
    use SoftDeletes;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $guarded = [];
}
