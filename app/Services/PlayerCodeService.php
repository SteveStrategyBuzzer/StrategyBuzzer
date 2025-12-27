<?php

namespace App\Services;

use App\Models\User;

class PlayerCodeService
{
    /**
     * Générer un code joueur unique au format SB-XXXX
     * Utilise 4 caractères alphanumériques (0-9, A-Z)
     * Capacité: 36^4 = ~1.6 million de codes possibles
     */
    public static function generateUniqueCode(): string
    {
        $maxAttempts = 100;
        $attempt = 0;
        
        do {
            $code = 'SB-' . self::generateRandomString(4);
            $exists = User::where('player_code', $code)->exists();
            $attempt++;
            
            if (!$exists) {
                return $code;
            }
            
            if ($attempt >= $maxAttempts) {
                throw new \Exception('Impossible de générer un code unique après ' . $maxAttempts . ' tentatives');
            }
        } while (true);
    }
    
    /**
     * Générer une chaîne aléatoire alphanumérique
     */
    private static function generateRandomString(int $length): string
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        
        return $randomString;
    }
    
    /**
     * Trouver un utilisateur par son code joueur
     */
    public static function findByCode(string $code): ?User
    {
        // Nettoyer le code (enlever espaces, mettre en majuscules)
        $cleanCode = strtoupper(trim($code));
        
        // Ajouter le préfixe SB- s'il n'est pas présent
        // Use strpos for PHP 7.x compatibility (str_starts_with is PHP 8+)
        if (strpos($cleanCode, 'SB-') !== 0) {
            $cleanCode = 'SB-' . $cleanCode;
        }
        
        return User::where('player_code', $cleanCode)->first();
    }
}
