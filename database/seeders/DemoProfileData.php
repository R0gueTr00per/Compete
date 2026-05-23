<?php

namespace Database\Seeders;

use Carbon\Carbon;

/**
 * Shared profile spec data for demo seeders.
 * 100 competitors distributed across all age bands, genders, ranks, and weights.
 * Covers every age band (8&under, 9-11, 12-14, 15+, 40+), both sexes,
 * mostly kyu ranks (9-1 kyu, ~82%) with a minority of dans, no experience entries,
 * and all weight classes (<25 kg through 80+).
 */
class DemoProfileData
{
    private static array $maleNames = [
        'James', 'Oliver', 'William', 'Ethan', 'Noah', 'Lucas', 'Mason', 'Logan', 'Aiden', 'Henry',
        'Jack', 'Daniel', 'Owen', 'Samuel', 'Ryan', 'Dylan', 'Tyler', 'Caleb', 'Nathan', 'Alex',
        'Marcus', 'Sean', 'Patrick', 'Jordan', 'Liam', 'Finn', 'Zac', 'Eli', 'Oscar', 'Leo',
        'Hunter', 'Jasper', 'Miles', 'Nolan', 'Reid', 'Soren', 'Theo', 'Wyatt', 'Xavier', 'Yusuf',
        'Zane', 'Aaron', 'Blake', 'Cole', 'Derek', 'Flynn', 'Grant', 'Hugo', 'Ivan', 'Joel',
    ];

    private static array $femaleNames = [
        'Emma', 'Olivia', 'Sophie', 'Isla', 'Charlotte', 'Amelia', 'Ava', 'Mia', 'Grace', 'Lily',
        'Zoe', 'Chloe', 'Emily', 'Hannah', 'Ella', 'Scarlett', 'Madison', 'Aria', 'Leah', 'Nora',
        'Ruby', 'Sienna', 'Abby', 'Jasmine', 'Claire', 'Freya', 'Gwen', 'Hazel', 'Iris', 'June',
        'Kaia', 'Luna', 'Mae', 'Nova', 'Piper', 'Quinn', 'Rosa', 'Sage', 'Tara', 'Uma',
        'Vera', 'Wren', 'Xena', 'Yara', 'Zara', 'Adele', 'Belle', 'Cara', 'Dawn', 'Faye',
    ];

    private static array $surnames = [
        'Smith', 'Jones', 'Williams', 'Brown', 'Wilson', 'Taylor', 'Johnson', 'White', 'Martin',
        'Anderson', 'Thompson', 'Garcia', 'Robinson', 'Clark', 'Rodriguez', 'Lewis', 'Lee', 'Walker',
        'Hall', 'Allen', 'Young', 'King', 'Wright', 'Scott', 'Torres', 'Nguyen', 'Hill', 'Flores',
        'Green', 'Adams', 'Nelson', 'Baker', 'Rivera', 'Campbell', 'Mitchell', 'Carter', 'Roberts',
        'Turner', 'Phillips', 'Evans', 'Parker', 'Edwards', 'Collins', 'Stewart', 'Morris', 'Sanchez',
        'Rogers', 'Reed', 'Cook', 'Morgan', 'Cooper', 'Patel', 'Chen', 'Kim', 'Tanaka', 'Okafor',
        'Ramos', 'Singh', 'Hassan', 'Wu', 'Sato', 'Gonzalez', 'Müller', 'Ferreira', 'Rossi', 'Dupont',
        'Andersen', 'Svensson', 'Kowalski', 'Petrov', 'Yilmaz', 'Nakamura', 'Abbas', 'Diallo', 'Lopes', 'Kovacs',
        'Johansson', 'Schmidt', 'Fischer', 'Weber', 'Meyer', 'Wagner', 'Becker', 'Schulz', 'Hoffmann', 'Koch',
        'Yamamoto', 'Park', 'Liu', 'Ahmed', 'Ali', 'Tan', 'Lim', 'Sharma', 'Gupta', 'Kumar',
    ];

    /**
     * 100 raw specs: [age, gender, rank_type, rank_value, weight_kg]
     * rank_value: int for kyu/dan; ['years' => N, 'months' => N] for experience.
     */
    /**
     * 100 raw specs: [age, gender, rank_type, rank_value, weight_kg]
     *
     * Rank brackets covered per age band (≥4 each):
     *   8&Under  : 10-6 Kyu only (10)
     *   9-11     : 10-6 Kyu (12), 5-1 Kyu (8)
     *   12-14    : 10-6 Kyu (6), 5-1 Kyu (10), Black Belt (4)
     *   15-39    : 10-6 Kyu (8), 5-1 Kyu (14), Black Belt (8)
     *   40+      : 10-6 Kyu (6), 5-1 Kyu (8), Black Belt (6)
     *
     * No experience entries — every competitor has a kyu or dan rank.
     * Overall: 82 kyu, 18 dan.
     */
    public static function rawSpecs(): array
    {
        return [
            // ── 8 & Under (10) ── all 10-6 Kyu, weights 19-28 kg ───────────────────
            [ 5, 'M', 'kyu', 9, 19.0],
            [ 6, 'F', 'kyu', 9, 21.0],
            [ 7, 'M', 'kyu', 9, 23.0],
            [ 8, 'F', 'kyu', 9, 26.0],
            [ 6, 'M', 'kyu', 9, 22.0],
            [ 7, 'F', 'kyu', 8, 24.0],
            [ 8, 'M', 'kyu', 8, 28.0],
            [ 5, 'F', 'kyu', 9, 20.0],
            [ 8, 'M', 'kyu', 8, 27.0],
            [ 7, 'F', 'kyu', 9, 25.0],

            // ── 9-11 Years (20) ── 10-6 Kyu (12) + 5-1 Kyu (8), weights 29-45 kg ──
            [ 9, 'M', 'kyu', 9, 31.0],
            [ 9, 'F', 'kyu', 9, 29.0],
            [10, 'M', 'kyu', 8, 36.0],
            [10, 'F', 'kyu', 8, 33.0],
            [11, 'M', 'kyu', 7, 42.0],
            [11, 'F', 'kyu', 7, 38.0],
            [ 9, 'M', 'kyu', 8, 32.0],
            [10, 'F', 'kyu', 9, 34.0],
            [11, 'M', 'kyu', 8, 40.0],
            [ 9, 'F', 'kyu', 8, 30.0],
            [10, 'M', 'kyu', 7, 37.0],
            [11, 'F', 'kyu', 9, 39.0],
            // 5-1 Kyu
            [ 9, 'M', 'kyu', 5, 33.0],
            [10, 'F', 'kyu', 5, 35.0],
            [11, 'M', 'kyu', 5, 41.0],
            [ 9, 'F', 'kyu', 5, 31.0],
            [10, 'M', 'kyu', 4, 37.0],
            [11, 'F', 'kyu', 4, 40.0],
            [10, 'M', 'kyu', 5, 38.0],
            [11, 'F', 'kyu', 5, 44.0],

            // ── 12-14 Years (20) ── 10-6 Kyu (6) + 5-1 Kyu (10) + Black Belt (4) ──
            // 10-6 Kyu
            [12, 'M', 'kyu', 8, 45.0],
            [12, 'F', 'kyu', 8, 42.0],
            [13, 'M', 'kyu', 7, 52.0],
            [13, 'F', 'kyu', 7, 48.0],
            [14, 'M', 'kyu', 9, 55.0],
            [14, 'F', 'kyu', 9, 50.0],
            // 5-1 Kyu
            [12, 'M', 'kyu', 5, 47.0],
            [12, 'F', 'kyu', 5, 44.0],
            [13, 'M', 'kyu', 4, 54.0],
            [13, 'F', 'kyu', 4, 50.0],
            [14, 'M', 'kyu', 3, 60.0],
            [14, 'F', 'kyu', 3, 56.0],
            [12, 'M', 'kyu', 5, 49.0],
            [12, 'F', 'kyu', 4, 46.0],
            [13, 'M', 'kyu', 2, 56.0],
            [13, 'F', 'kyu', 2, 52.0],
            // Black Belt
            [14, 'M', 'dan', 1, 62.0],
            [14, 'F', 'dan', 1, 58.0],
            [13, 'M', 'dan', 1, 57.0],
            [14, 'F', 'dan', 1, 61.0],

            // ── 15-39 Years (30) ── 10-6 Kyu (8) + 5-1 Kyu (14) + Black Belt (8) ──
            // 10-6 Kyu
            [15, 'M', 'kyu', 9, 65.0],
            [16, 'F', 'kyu', 9, 57.0],
            [18, 'M', 'kyu', 8, 70.0],
            [19, 'F', 'kyu', 8, 60.0],
            [22, 'M', 'kyu', 7, 75.0],
            [23, 'F', 'kyu', 7, 63.0],
            [35, 'M', 'kyu', 9, 78.0],
            [38, 'F', 'kyu', 8, 65.0],
            // 5-1 Kyu
            [17, 'M', 'kyu', 5, 72.0],
            [17, 'F', 'kyu', 5, 61.0],
            [20, 'M', 'kyu', 4, 80.0],
            [21, 'F', 'kyu', 4, 65.0],
            [24, 'M', 'kyu', 3, 84.0],
            [25, 'F', 'kyu', 3, 68.0],
            [26, 'M', 'kyu', 2, 87.0],
            [27, 'F', 'kyu', 2, 71.0],
            [28, 'M', 'kyu', 1, 90.0],
            [29, 'F', 'kyu', 1, 74.0],
            [30, 'M', 'kyu', 5, 82.0],
            [31, 'F', 'kyu', 4, 67.0],
            [33, 'M', 'kyu', 2, 88.0],
            [36, 'F', 'kyu', 1, 72.0],
            // Black Belt
            [32, 'M', 'dan', 1, 85.0],
            [34, 'F', 'dan', 1, 70.0],
            [37, 'M', 'dan', 1, 92.0],
            [39, 'F', 'dan', 1, 76.0],
            [25, 'M', 'dan', 2, 88.0],
            [28, 'F', 'dan', 2, 73.0],
            [15, 'M', 'dan', 1, 67.0],
            [16, 'F', 'dan', 1, 59.0],

            // ── 40+ Years (20) ── 10-6 Kyu (6) + 5-1 Kyu (8) + Black Belt (6) ──────
            // 10-6 Kyu
            [40, 'M', 'kyu', 9, 85.0],
            [41, 'F', 'kyu', 9, 70.0],
            [45, 'M', 'kyu', 8, 89.0],
            [47, 'F', 'kyu', 8, 73.0],
            [55, 'M', 'kyu', 7, 91.0],
            [60, 'F', 'kyu', 7, 75.0],
            // 5-1 Kyu
            [42, 'M', 'kyu', 5, 92.0],
            [43, 'F', 'kyu', 5, 72.0],
            [46, 'M', 'kyu', 4, 94.0],
            [49, 'F', 'kyu', 3, 76.0],
            [51, 'M', 'kyu', 2, 96.0],
            [53, 'F', 'kyu', 1, 71.0],
            [48, 'M', 'kyu', 4, 93.0],
            [50, 'F', 'kyu', 2, 74.0],
            // Black Belt
            [56, 'M', 'dan', 1, 97.0],
            [57, 'F', 'dan', 1, 76.0],
            [59, 'M', 'dan', 2, 93.0],
            [61, 'F', 'dan', 2, 78.0],
            [63, 'M', 'dan', 3, 88.0],
            [65, 'F', 'dan', 3, 76.0],
        ];
    }

    /**
     * Build the full 100-profile array with names and DOBs resolved.
     */
    public static function buildProfiles(): array
    {
        $maleNames   = self::$maleNames;
        $femaleNames = self::$femaleNames;
        $surnames    = self::$surnames;
        $mIdx = $fIdx = $sIdx = 0;
        $profiles = [];

        foreach (self::rawSpecs() as $i => [$age, $gender, $rankType, $rankVal, $weight]) {
            $firstName = $gender === 'M'
                ? $maleNames[$mIdx++ % count($maleNames)]
                : $femaleNames[$fIdx++ % count($femaleNames)];

            $surname = $surnames[$sIdx++ % count($surnames)];

            // Deterministic DOB: put birthday a fixed number of months before now so age() is stable
            $dob = Carbon::now()->subYears($age)->subMonths(($i % 11) + 1);

            $profiles[] = [
                'first_name'        => $firstName,
                'surname'           => $surname,
                'gender'            => $gender,
                'dob'               => $dob->format('Y-m-d'),
                'age'               => $age,
                'weight_kg'         => $weight,
                'rank_type'         => $rankType,
                'rank_kyu'          => $rankType === 'kyu' ? $rankVal : null,
                'rank_dan'          => $rankType === 'dan' ? $rankVal : null,
            ];
        }

        return $profiles;
    }
}
