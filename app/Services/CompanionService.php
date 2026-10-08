<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserCompanion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanionService
{
    private const DECAY_INTERVAL_HOURS = 6;
    private const MIN_STAT = 20;
    private const MAX_LEVEL = 100;

    private const ROOMS = [
        'studio' => ['name' => 'Tech stúdió', 'premium' => false],
        'play' => ['name' => 'Játékszoba', 'premium' => false],
        'night' => ['name' => 'Éjszakai mód', 'premium' => false],
        'aurora' => ['name' => 'Aurora Lounge', 'premium' => true],
        'cyber' => ['name' => 'Cyber Deck', 'premium' => true],
    ];

private const SKINS = [
    'getingo-mouse' => [
        'name' => 'Getingo Egér',
        'premium' => false,
        'species' => 'mouse',
        'image' => '/mascots/getingo-mouse.png',
        'model_url' => '/models/getingo-buddies/getingo-mouse.glb',
        'description' => 'A Getingo alap Buddyja: kíváncsi, lelkes és minden felhasználó számára elérhető.',
        'personality' => 'Kíváncsi',
        'signature' => 'Tanulási szikra',
        'accent' => '#f59e0b',
    ],

    'getingo-sloth' => [
        'name' => 'Getingo Lajhár',
        'premium' => true,
        'species' => 'sloth',
        'image' => '/mascots/getingo-sloth.png',
        'model_url' => '/models/getingo-buddies/getingo-sloth.glb',
        'description' => 'Nyugodt Premium Buddy, aki a fókuszált, kiegyensúlyozott tanulást képviseli.',
        'personality' => 'Nyugodt',
        'signature' => 'Deep Focus',
        'accent' => '#a16207',
    ],

    'getingo-reindeer' => [
        'name' => 'Noel Rénszarvas',
        'premium' => true,
        'species' => 'reindeer',
        'image' => '/mascots/getingo-reindeer.png',
        'model_url' => '/models/getingo-buddies/getingo-reindeer.glb',
        'description' => 'Energikus Premium Buddy különleges Evolution effektekkel és ünnepi személyiséggel.',
        'personality' => 'Lelkes',
        'signature' => 'Aurora Dash',
        'accent' => '#ef4444',
    ],

    'getingo-shark' => [
        'name' => 'Getingo Cápa',
        'premium' => true,
        'species' => 'shark',
        'image' => '/mascots/getingo-shark.png',
        'model_url' => '/models/getingo-buddies/getingo-shark.glb',
        'description' => 'Határozott Premium társ azoknak, akik szeretnek lendületben maradni.',
        'personality' => 'Bátor',
        'signature' => 'Cyber Surge',
        'accent' => '#0891b2',
    ],

    'getingo-dragon' => [
        'name' => 'Kis Sárkány',
        'premium' => true,
        'species' => 'dragon',
        'image' => '/mascots/getingo-dragon.png',
        'model_url' => '/models/getingo-buddies/getingo-dragon.glb',
        'description' => 'Játékos Premium Buddy látványos aurával és különleges Signature reakcióval.',
        'personality' => 'Tüzes',
        'signature' => 'Dragon Burst',
        'accent' => '#8b5cf6',
    ],
];

private const LEGACY_SKIN_MAP = [
    'code-kitten' => 'getingo-mouse',
    'code-kitten-3d' => 'getingo-mouse',
    'arctic-byte' => 'getingo-mouse',

    'neon-orbit' => 'getingo-dragon',

    'getingo-puppy' => 'getingo-sloth',
    'royal-circuit' => 'getingo-sloth',
];

    private const ACTIONS = [
        'water' => [
            'field' => 'water',
            'cost' => 20,
            'boost' => 30,
            'growth' => 12,
            'label' => 'Itatás',
            'message' => 'Pixel ivott egyet, és újra energikusabb lett.',
        ],
        'feed' => [
            'field' => 'hunger',
            'cost' => 30,
            'boost' => 30,
            'growth' => 15,
            'label' => 'Falatozás',
            'message' => 'Pixel jóllakott, elégedetten folytatja a kalandot és fejlődik tovább.',
        ],
        'play' => [
            'field' => 'happiness',
            'cost' => 25,
            'boost' => 26,
            'growth' => 10,
            'label' => 'Játék',
            'message' => 'A közös játék feldobta Pixel kedvét, és még ügyesebb lett.',
        ],
    ];

    private const ERAS = [
        1 => 'Apró társ',
        2 => 'Kíváncsi felfedező',
        3 => 'Tanuló buddy',
        4 => 'Fejlődő társ',
        5 => 'Okos segítő',
        6 => 'Haladó buddy',
        7 => 'Elit társ',
        8 => 'Mester buddy',
        9 => 'Legendás társ',
        10 => 'Ultimate Getingo Buddy',
    ];

    public function getOrCreate(User $user): UserCompanion
    {
        return UserCompanion::firstOrCreate(
            ['user_id' => $user->id],
            [
                'name' => 'Pixel',
                'care_points' => max(0, (int) $user->xp_points),
                'growth_points' => 0,
                'water' => 74,
                'hunger' => 72,
                'happiness' => 78,
                'selected_skin' => 'getingo-mouse',
                'selected_room' => 'studio',
                'last_decay_at' => now(),
            ]
        );
    }

    public function awardLearningPoints(User $user, int $points): void
    {
        if ($points <= 0) {
            return;
        }

        $companion = $this->getOrCreate($user);
        $companion->increment('care_points', $points);
        $user->increment('xp_points', $points);
    }

    public function performAction(User $user, string $action): array
    {
        if (!array_key_exists($action, self::ACTIONS)) {
            throw ValidationException::withMessages([
                'action' => 'Ismeretlen gondozási művelet.',
            ]);
        }

        return DB::transaction(function () use ($user, $action): array {
            $config = self::ACTIONS[$action];
            $companion = $this->getOrCreate($user);
            $companion = UserCompanion::whereKey($companion->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->applyDecay($companion);

            $field = $config['field'];
            $currentValue = (int) $companion->{$field};
            $cost = (int) $config['cost'];

            if ($currentValue >= 100) {
                throw ValidationException::withMessages([
                    'action' => 'Ez az érték már maximumon van. Később újra gondozhatod.',
                ]);
            }

            if ($companion->care_points < $cost) {
                throw ValidationException::withMessages([
                    'action' => 'Nincs elég gondozási pontod ehhez.',
                ]);
            }

            $companion->care_points -= $cost;
            $companion->{$field} = min(100, $currentValue + (int) $config['boost']);
            $companion->growth_points += (int) $config['growth'];
            $companion->last_interaction_at = now();
            $companion->last_decay_at = now();
            $companion->save();

            return [
                'message' => $config['message'],
                'state' => $this->state($user->fresh(), $companion->fresh()),
            ];
        });
    }

public function state(User $user, ?UserCompanion $companion = null): array
{
    $companion ??= $this->getOrCreate($user);

    // Régi Buddy azonosítók átvezetése az új rendszerbe.
    $normalizedSkin = self::LEGACY_SKIN_MAP[$companion->selected_skin]
        ?? $companion->selected_skin;

    // Ha olyan skin van az adatbázisban, amit már nem ismerünk,
    // visszaállítjuk az ingyenes egérre.
    if (! array_key_exists($normalizedSkin, self::SKINS)) {
        $normalizedSkin = 'getingo-mouse';
    }

    if ($normalizedSkin !== $companion->selected_skin) {
        $companion->selected_skin = $normalizedSkin;
        $companion->save();
    }

    // Nem Premium felhasználó nem használhat Premium Buddyt.
    if (
        ! $user->is_premium
        && isset(self::SKINS[$companion->selected_skin])
        && self::SKINS[$companion->selected_skin]['premium']
    ) {
        $companion->selected_skin = 'getingo-mouse';
        $companion->save();
    }

    // Nem Premium felhasználó nem használhat Premium szobát.
    if (
        ! $user->is_premium
        && isset(self::ROOMS[$companion->selected_room])
        && self::ROOMS[$companion->selected_room]['premium']
    ) {
        $companion->selected_room = 'studio';
        $companion->save();
    }

    $this->applyDecay($companion);
    $companion->refresh();

    $xp = (int) $user->xp_points;
    $knowledgeGrowth = intdiv($xp, 2);
    $careGrowth = (int) $companion->growth_points;
    $totalGrowth = $knowledgeGrowth + $careGrowth;

    $growth = $this->growthForPoints(
        $totalGrowth,
        $knowledgeGrowth,
        $careGrowth
    );

    $mood = $this->moodForCompanion($companion);
    $behavior = $this->behaviorForCompanion($companion);

    return [
        'companion' => [
            'id' => $companion->id,
            'name' => $companion->name,
            'care_points' => $companion->care_points,
            'growth_points' => $companion->growth_points,
            'water' => $companion->water,
            'hunger' => $companion->hunger,
            'happiness' => $companion->happiness,
            'selected_skin' => $companion->selected_skin,
            'selected_room' => $companion->selected_room ?? 'studio',
            'last_interaction_at' => $companion->last_interaction_at,
        ],

        'growth' => $growth,
        'mood' => $mood,
        'behavior' => $behavior,
        'xp_points' => $xp,

        'available_skins' => collect(self::SKINS)
            ->map(fn (array $skin, string $key) => [
                'key' => $key,
                'name' => $skin['name'],
                'premium' => $skin['premium'],
                'species' => $skin['species'],
                'image' => $skin['image'],
                'model_url' => $skin['model_url'],
                'description' => $skin['description'],
                'personality' => $skin['personality'],
                'signature' => $skin['signature'],
                'accent' => $skin['accent'],
                'unlocked' => ! $skin['premium'] || $user->is_premium,
            ])
            ->values(),

        'available_rooms' => collect(self::ROOMS)
            ->map(fn (array $room, string $key) => [
                'key' => $key,
                'name' => $room['name'],
                'premium' => $room['premium'],
                'unlocked' => ! $room['premium'] || $user->is_premium,
            ])
            ->values(),

        'actions' => collect(self::ACTIONS)
            ->map(fn (array $config, string $key) => [
                'key' => $key,
                'label' => $config['label'],
                'cost' => $config['cost'],
                'boost' => $config['boost'],
                'growth' => $config['growth'],
            ])
            ->values(),
    ];
}

    public function updatePreferences(User $user, ?string $room, ?string $skin): array
    {
        $companion = $this->getOrCreate($user);

        if ($room !== null) {
            if (! array_key_exists($room, self::ROOMS)) {
                throw ValidationException::withMessages(['room' => 'Ismeretlen Buddy szoba.']);
            }
            if (self::ROOMS[$room]['premium'] && ! $user->is_premium) {
                throw ValidationException::withMessages(['room' => 'Ez a szoba Premium előfizetéshez tartozik.']);
            }
            $companion->selected_room = $room;
        }

        if ($skin !== null) {
            if (! array_key_exists($skin, self::SKINS)) {
                throw ValidationException::withMessages(['skin' => 'Ismeretlen Buddy skin.']);
            }
            if (self::SKINS[$skin]['premium'] && ! $user->is_premium) {
                throw ValidationException::withMessages(['skin' => 'Ez a skin Premium előfizetéshez tartozik.']);
            }
            $companion->selected_skin = $skin;
        }

        $companion->save();

        return $this->state($user->fresh(), $companion->fresh());
    }

    private function applyDecay(UserCompanion $companion): void
    {
        if (!$companion->last_decay_at) {
            $companion->last_decay_at = now();
            $companion->save();
            return;
        }

        $minutesPassed = (int) $companion->last_decay_at->diffInMinutes(now());
        $periods = intdiv($minutesPassed, self::DECAY_INTERVAL_HOURS * 60);

        if ($periods <= 0) {
            return;
        }

        $periods = min($periods, 40);
        $companion->water = max(self::MIN_STAT, (int) $companion->water - ($periods * 4));
        $companion->hunger = max(self::MIN_STAT, (int) $companion->hunger - ($periods * 3));
        $companion->happiness = max(self::MIN_STAT, (int) $companion->happiness - ($periods * 2));
        $companion->last_decay_at = now();
        $companion->save();
    }

    private function growthForPoints(int $points, int $knowledgeGrowth, int $careGrowth): array
    {
        $level = 1;

        for ($candidate = 2; $candidate <= self::MAX_LEVEL; $candidate++) {
            if ($points < $this->pointsRequiredForLevel($candidate)) {
                break;
            }

            $level = $candidate;
        }

        $era = min(10, intdiv($level - 1, 10) + 1);
        $currentLevelPoints = $this->pointsRequiredForLevel($level);
        $nextLevelPoints = $level >= self::MAX_LEVEL
            ? null
            : $this->pointsRequiredForLevel($level + 1);

        if ($nextLevelPoints === null) {
            $progress = 100;
            $pointsToNext = 0;
        } else {
            $range = max(1, $nextLevelPoints - $currentLevelPoints);
            $progress = (int) floor((($points - $currentLevelPoints) / $range) * 100);
            $progress = max(0, min(100, $progress));
            $pointsToNext = max(0, $nextLevelPoints - $points);
        }

        $sizePercentage = (int) round(70 + (($level - 1) / 99) * 55);

        return [
            'key' => 'era-'.$era,
            'level' => $level,
            'max_level' => self::MAX_LEVEL,
            'era' => $era,
            'name' => self::ERAS[$era],
            'progress_percentage' => $progress,
            'current_level_points' => $currentLevelPoints,
            'next_level_points' => $nextLevelPoints,
            'points_to_next_level' => $pointsToNext,
            // Backwards-compatible fields for the current Angular client.
            'next_stage_points' => $nextLevelPoints,
            'points_to_next_stage' => $pointsToNext,
            'knowledge_growth_points' => $knowledgeGrowth,
            'care_growth_points' => $careGrowth,
            'total_growth_points' => $points,
            'size_percentage' => $sizePercentage,
        ];
    }

    private function pointsRequiredForLevel(int $level): int
    {
        if ($level <= 1) {
            return 0;
        }

        $step = $level - 1;

        // 1 -> 100 között fokozatosan lassuló, de elérhető fejlődési görbe.
        return (25 * $step) + intdiv($step * $step, 6);
    }
    private function behaviorForCompanion(UserCompanion $companion): array
{
    if ((int) $companion->hunger <= 35) {
        return [
            'key' => 'hungry',
            'name' => 'Éhes',
            'message' => 'A Buddy enne valamit.',
            'animation' => 'hungry',
        ];
    }

    if ((int) $companion->water <= 35) {
        return [
            'key' => 'thirsty',
            'name' => 'Szomjas',
            'message' => 'A Buddy megszomjazott.',
            'animation' => 'drink',
        ];
    }

    if ((int) $companion->happiness <= 35) {
        return [
            'key' => 'lonely',
            'name' => 'Magányos',
            'message' => 'A Buddy szeretne egy kis figyelmet.',
            'animation' => 'sad',
        ];
    }

    if ((int) $companion->happiness >= 85) {
        return [
            'key' => 'happy',
            'name' => 'Boldog',
            'message' => 'A Buddy remekül érzi magát.',
            'animation' => 'happy',
        ];
    }

    return [
        'key' => 'idle',
        'name' => 'Nyugodt',
        'message' => 'Minden rendben. A Buddy készen áll.',
        'animation' => 'idle',
    ];
}


    private function moodForCompanion(UserCompanion $companion): array
    {
        $score = (int) round(
            ((int) $companion->water + (int) $companion->hunger + (int) $companion->happiness) / 3
        );

        if ($score >= 85) {
            return ['key' => 'radiant', 'name' => 'Ragyogó', 'score' => $score];
        }

        if ($score >= 65) {
            return ['key' => 'happy', 'name' => 'Vidám', 'score' => $score];
        }

        if ($score >= 45) {
            return ['key' => 'calm', 'name' => 'Nyugodt', 'score' => $score];
        }

        return ['key' => 'wilted', 'name' => 'Álmos', 'score' => $score];
    }
}
