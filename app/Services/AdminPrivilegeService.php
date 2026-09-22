<?php

namespace App\Services;

use App\Models\AdminPrivilegeAuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class AdminPrivilegeService
{
    public function grant(string $email): bool
    {
        return $this->setAdmin($email, true);
    }

    public function revoke(string $email): bool
    {
        return $this->setAdmin($email, false);
    }

    private function setAdmin(string $email, bool $isAdmin): bool
    {
        return DB::transaction(function () use ($email, $isAdmin): bool {
            $user = User::query()->where('email', $email)->lockForUpdate()->firstOrFail();

            if ($user->isAdmin() === $isAdmin) {
                return false;
            }

            $previous = $user->isAdmin();
            User::query()
                ->whereKey($user->getKey())
                ->update(['is_admin' => $isAdmin]);

            AdminPrivilegeAuditLog::create([
                'action' => $isAdmin ? 'ADMIN_GRANTED' : 'ADMIN_REVOKED',
                'target_user_id' => $user->getKey(),
                'origin' => 'SYSTEM_CLI',
                'metadata' => [
                    'previous_is_admin' => $previous,
                    'new_is_admin' => $isAdmin,
                ],
            ]);

            return true;
        });
    }
}