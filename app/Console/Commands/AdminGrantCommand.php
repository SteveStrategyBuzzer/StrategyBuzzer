<?php

namespace App\Console\Commands;

use App\Services\AdminPrivilegeService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class AdminGrantCommand extends Command
{
    protected $signature = 'admin:grant {email : Exact email of an existing user}';

    protected $description = 'Grant the persistent Admin privilege to an existing user';

    public function handle(AdminPrivilegeService $privileges): int
    {
        $email = $this->normalizedEmail();
        if ($email === null) {
            $this->error('A valid email is required.');
            return self::INVALID;
        }

        try {
            $changed = $privileges->grant($email);
        } catch (ModelNotFoundException) {
            $this->error('No matching user exists.');
            return self::FAILURE;
        }

        $this->info($changed ? 'Admin privilege granted.' : 'Admin privilege already granted.');
        return self::SUCCESS;
    }

    private function normalizedEmail(): ?string
    {
        $email = trim((string) $this->argument('email'));
        return filter_var($email, FILTER_VALIDATE_EMAIL) === false ? null : $email;
    }
}