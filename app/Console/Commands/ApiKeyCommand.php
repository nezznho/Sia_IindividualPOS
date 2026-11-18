<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use Illuminate\Console\Command;

class ApiKeyCommand extends Command
{
    protected $signature = 'api-key
                            {action : generate | list | revoke}
                            {value? : key name (generate) or key ID (revoke)}';

    protected $description = 'Manage API keys for the /api/v1 endpoints';

    public function handle(): int
    {
        switch ($this->argument('action')) {
            case 'generate':
                $name = $this->argument('value') ?: 'default';
                [$key, $plain] = ApiKey::generate($name);
                $this->info("API key created (ID {$key->id}, name \"{$name}\").");
                $this->line('');
                $this->line("  {$plain}");
                $this->line('');
                $this->warn('Copy it now - it is stored hashed and cannot be shown again.');
                return self::SUCCESS;

            case 'list':
                $this->table(
                    ['ID', 'Name', 'Key', 'Last used', 'Status'],
                    ApiKey::orderBy('id')->get()->map(fn ($k) => [
                        $k->id,
                        $k->name,
                        $k->key_preview,
                        $k->last_used_at?->toDateTimeString() ?? 'never',
                        $k->revoked_at ? 'revoked' : 'active',
                    ])
                );
                return self::SUCCESS;

            case 'revoke':
                $key = ApiKey::find($this->argument('value'));
                if (! $key) {
                    $this->error('No API key with that ID. Run: php artisan api-key list');
                    return self::FAILURE;
                }
                $key->forceFill(['revoked_at' => now()])->save();
                $this->info("API key {$key->id} ({$key->name}) revoked.");
                return self::SUCCESS;
        }

        $this->error('Unknown action. Use: generate | list | revoke');
        return self::FAILURE;
    }
}
