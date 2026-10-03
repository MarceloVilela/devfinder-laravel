<?php

declare(strict_types=1);

namespace App\Features\Channel\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * @property string $id
 * @property string $name
 * @property string $link
 * @property string|null $user_github
 * @property string|null $description
 * @property string $category
 * @property string|null $avatar
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property Collection<int, Tag> $tags
 */
final class Channel extends Model
{
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'channel_tag')->orderBy('tags.name');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }
}
