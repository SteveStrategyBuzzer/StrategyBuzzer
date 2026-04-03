<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;

class BotUserSeeder extends Seeder
{
    public function run(): void
    {
        User::withoutEvents(function () {
            User::firstOrCreate(
                ['player_code' => 'BT-0001'],
                [
                    'name' => 'Bot Duo',
                    'email' => 'bot@strategybuzzer.local',
                    'password' => Hash::make(Str::random(32)),
                    'is_bot' => true,
                    'email_verified_at' => now(),
                    'competence_coins' => 0,
                    'intelligence_pieces' => 0,
                ]
            );
        });
    }
}
