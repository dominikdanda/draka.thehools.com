<?php

return [
    'lobby_url' => env('LOBBY_URL', 'https://thehools.com/lobby.php'),

    'main_menu' => [
        ['key' => 'captains', 'label' => 'Kapitanowie', 'icon' => '★', 'route' => 'captains.index', 'available' => true],
        ['key' => 'start',        'label' => 'Pulpit',       'icon' => '🏠', 'route' => 'start', 'available' => true],
        ['key' => 'career',       'label' => 'Kariera',      'icon' => '⚡', 'route' => 'career.index', 'available' => true],
        ['key' => 'duel',         'label' => 'Pojedynki',    'icon' => '🥊', 'route' => 'duel.index', 'available' => false],
        ['key' => 'team',         'label' => 'Ekipa',        'icon' => '👥', 'route' => 'team.index', 'available' => false],
        ['key' => 'investments',  'label' => 'Inwestycje',   'icon' => '🏚️', 'route' => 'investments.index', 'available' => false],
        ['key' => 'drugs',        'label' => 'Narkotyki',    'icon' => '💊', 'route' => 'drugs.index', 'available' => false],
        ['key' => 'arsenal',      'label' => 'Zbrojownia',   'icon' => '🔫', 'route' => 'arsenal.index', 'available' => false],
        ['key' => 'quest',        'label' => 'Zlecenia',     'icon' => '📋', 'route' => 'quest.index', 'available' => false],
        ['key' => 'safe',         'label' => 'Sejf',         'icon' => '🏦', 'route' => 'safe.index', 'available' => false],
        ['key' => 'ranking',      'label' => 'Ranking',      'icon' => '🏆', 'route' => 'ranking.index', 'available' => false],
        ['key' => 'achievements', 'label' => 'Osiągnięcia',  'icon' => '🎖️', 'route' => 'achievements.index', 'available' => false],
        ['key' => 'profile',      'label' => 'Profil',       'icon' => '👤', 'route' => 'profile.index', 'available' => true],
    ],

    'world' => [
        'server_id' => env('THEHOOLS_SERVER_ID'),
        'name' => env('THEHOOLS_WORLD_NAME', 'Draka'),
    ],

    'speed' => [
        'economy' => 1.0,
        'construction' => 1.0,
        'research' => 1.0,
        'movement' => 1.0,
    ],

    'captains' => [
        'starting_limit' => 3,
        'relocation_hours' => 3,
        'recruitment_cooldown_hours' => 24,
    ],

    'formulas' => [
        'player' => [
            'starting_level' => 1,
            'starting_skill_points' => 4,
            'skill_points_per_level' => 4,
            'attack_per_skill_point' => 10,
            'defense_per_skill_point' => 10,
            'energy_per_skill_point' => 10,
            'base_energy' => 40,
            'base_health' => 100,
            'max_health' => 100,
            'starting_money' => 0,
        ],

        'experience' => [
            'curve' => 's3',
            'multiplier' => 1.0,
        ],

        'energy' => [
            'regen_seconds' => 60,
            'premium_regen_seconds' => 45,
        ],

        'health' => [
            'regen_seconds' => 120,
            'premium_regen_seconds' => 90,
            'regen_amount' => 1,
        ],

        'career' => [
            'stage_mastery_cap' => 500,
            'chapter_mastery_levels' => 5,
            'chapter_mastery_thresholds' => [100, 200, 300, 400, 500],
            'mastery_reward_at_level' => 2,
        ],
    ],
];
