<?php

declare(strict_types=1);

namespace App\Features\Auth\Queries;

use App\Shared\Auth\AuthenticatedDev;
use App\Shared\Auth\DevRole;
use App\Shared\Support\NormText;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class DevLookup
{
    /** 1 query. Dev apagado não autentica. */
    public function byUsername(string $username): ?AuthenticatedDev
    {
        $row = DB::table('devs')
            ->whereNull('deleted_at')
            ->whereRaw(NormText::equals('username'), [$username])
            ->first(['id', 'username', 'name', 'bio', 'avatar', 'role', 'created_at', 'updated_at']);

        if ($row === null) {
            return null;
        }

        return new AuthenticatedDev(
            id: self::str($row->id),
            username: self::str($row->username),
            name: self::str($row->name),
            bio: self::str($row->bio),
            avatar: self::str($row->avatar),
            createdAt: CarbonImmutable::parse(self::str($row->created_at))->utc(),
            updatedAt: CarbonImmutable::parse(self::str($row->updated_at))->utc(),
            role: DevRole::tryFrom(self::str($row->role)) ?? DevRole::User,
        );
    }

    private static function str(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
