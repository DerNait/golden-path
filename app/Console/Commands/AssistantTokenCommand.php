<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class AssistantTokenCommand extends Command
{
    protected $signature = 'assistant:token
        {name=assistant : Label for the token}
        {--email= : Owner email (defaults to PERSONAL_USER_EMAIL or the first user)}
        {--abilities=training:read,recommendations:write : Comma-separated abilities}
        {--expires=90 : Days until the token expires (0 = never)}
        {--list : List the user active assistant tokens}
        {--revoke= : Revoke a token by id}';

    protected $description = 'Create, list or revoke personal access tokens for the AI assistant API.';

    public function handle(): int
    {
        $email = $this->option('email') ?: env('PERSONAL_USER_EMAIL', 'owner@example.com');
        $user = User::where('email', $email)->first() ?? User::orderBy('id')->first();

        if (! $user) {
            $this->error('No user found.');

            return self::FAILURE;
        }

        if ($this->option('list')) {
            $rows = $user->tokens()->get(['id', 'name', 'abilities', 'last_used_at', 'expires_at'])
                ->map(fn ($t) => [$t->id, $t->name, implode(',', (array) $t->abilities), (string) $t->last_used_at, (string) $t->expires_at]);
            $this->table(['ID', 'Name', 'Abilities', 'Last used', 'Expires'], $rows);

            return self::SUCCESS;
        }

        if ($revoke = $this->option('revoke')) {
            $deleted = $user->tokens()->whereKey($revoke)->delete();
            $this->info($deleted ? "Revoked token {$revoke}." : "Token {$revoke} not found for {$user->email}.");

            return $deleted ? self::SUCCESS : self::FAILURE;
        }

        $abilities = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('abilities')))));
        $days = (int) $this->option('expires');
        $expiresAt = $days > 0 ? now()->addDays($days) : null;

        $token = $user->createToken($this->argument('name'), $abilities, $expiresAt);

        $this->info("Token created for {$user->email}.");
        $this->line('Abilities: '.implode(', ', $abilities));
        $this->line('Expires: '.($expiresAt ? $expiresAt->toDateTimeString() : 'never'));
        $this->newLine();
        $this->warn('Copy this token now; it will not be shown again:');
        $this->line($token->plainTextToken);

        return self::SUCCESS;
    }
}
