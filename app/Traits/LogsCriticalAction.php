<?php

namespace App\Traits;

use App\Models\CriticalActionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * LogsCriticalAction
 *
 * Mixin pour contrôleurs et services qui doivent journaliser des actions critiques
 * dans la table `critical_actions_log`.
 *
 * Usage dans un contrôleur :
 *   use App\Traits\LogsCriticalAction;
 *   ...
 *   $this->logAction('boutique_purchase', ['item_slug' => $slug, 'price' => $price]);
 *
 * Usage statique (depuis un service sans accès $request) :
 *   LogsCriticalAction::writeLog('match_finalized', $userId, ['match_id' => $id]);
 */
trait LogsCriticalAction
{
    /**
     * Journalise une action critique depuis un contrôleur (accès à $request via IoC).
     *
     * @param  string  $action  Code court de l'action (ex: 'boutique_purchase', 'avatar_select')
     * @param  array   $payload Données contextuelles sans données sensibles
     */
    protected function logAction(string $action, array $payload = []): void
    {
        try {
            $request = request();
            static::writeLog(
                $action,
                Auth::id(),
                $payload,
                $request?->ip(),
                $request?->userAgent(),
            );
        } catch (\Throwable $e) {
            Log::warning("critical_actions_log write failed: {$e->getMessage()}", [
                'action'  => $action,
                'payload' => $payload,
            ]);
        }
    }

    /**
     * Écriture directe dans le log, utilisable depuis n'importe où (services, jobs, etc.)
     *
     * @param  string       $action
     * @param  int|null     $userId
     * @param  array        $payload
     * @param  string|null  $ip
     * @param  string|null  $userAgent
     */
    public static function writeLog(
        string $action,
        ?int $userId = null,
        array $payload = [],
        ?string $ip = null,
        ?string $userAgent = null,
    ): void {
        try {
            CriticalActionLog::create([
                'user_id'    => $userId,
                'action'     => $action,
                'payload'    => empty($payload) ? null : $payload,
                'ip_address' => $ip,
                'user_agent' => $userAgent ? mb_substr($userAgent, 0, 512) : null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning("critical_actions_log write failed: {$e->getMessage()}", [
                'action' => $action,
                'user_id' => $userId,
            ]);
        }
    }
}
