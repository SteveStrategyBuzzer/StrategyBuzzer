<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GuideController extends Controller
{
    protected $modes = [
        'solo' => [
            'icon' => '🎮',
            'color' => '#4CAF50',
            'name' => 'SOLO',
        ],
        'duo' => [
            'icon' => '👥',
            'color' => '#2196F3',
            'name' => 'DUO',
        ],
        'ligue-individuelle' => [
            'icon' => '🏆',
            'color' => '#FF9800',
            'name' => 'LIGUE INDIVIDUELLE',
        ],
        'ligue-equipe' => [
            'icon' => '⚔️',
            'color' => '#9C27B0',
            'name' => 'LIGUE ÉQUIPE',
        ],
        'master' => [
            'icon' => '👑',
            'color' => '#F44336',
            'name' => 'MAÎTRE DU JEU',
        ],
        'avatars' => [
            'icon' => '🦸',
            'color' => '#00BCD4',
            'name' => 'AVATARS',
        ],
    ];

    public function index()
    {
        $user = Auth::user();
        $modes = $this->modes;
        
        return view('guide.index', compact('user', 'modes'));
    }

    public function show(string $mode)
    {
        $user = Auth::user();
        
        if (!array_key_exists($mode, $this->modes)) {
            abort(404);
        }
        
        $modeData = $this->modes[$mode];
        $allModes = $this->modes;
        
        return view('guide.show', compact('user', 'mode', 'modeData', 'allModes'));
    }
}
