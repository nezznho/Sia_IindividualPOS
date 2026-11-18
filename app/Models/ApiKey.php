<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    protected $fillable = ['name', 'key_hash', 'key_preview', 'last_used_at', 'revoked_at'];

    protected $casts = [
        'last_used_at' => 'datetime',
        'revoked_at'   => 'datetime',
    ];

    /**
     * Create a new key. Returns [ApiKey $model, string $plainKey].
     * The plain key is never stored and cannot be recovered later.
     */
    public static function generate(string $name): array
    {
        $plain = 'gc_' . Str::random(40);

        $model = static::create([
            'name'        => $name,
            'key_hash'    => hash('sha256', $plain),
            'key_preview' => substr($plain, 0, 7) . '...' . substr($plain, -4),
        ]);

        return [$model, $plain];
    }

    public static function findActive(string $plain): ?self
    {
        return static::where('key_hash', hash('sha256', $plain))
            ->whereNull('revoked_at')
            ->first();
    }
}
