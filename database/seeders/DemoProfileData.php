<?php

namespace Database\Seeders;

use Carbon\Carbon;

/**
 * Shared profile spec data for demo seeders.
 * 100 competitors distributed across all age bands, genders, ranks, and weights.
 * Covers every age band (8&under, 9-11, 12-14, 15+, 40+), both sexes,
 * all rank types (experience, kyu 9-1, dan 1-5), and all weight classes (<25 kg through 80+).
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
    public static function rawSpecs(): array
    {
        return [
            // ── Under 8 (10 entries) ── ages 5-8, weights 19-28 kg ──────────────────
            [ 5, 'M', 'experience', ['years' => 0, 'months' => 6],  19.0],
            [ 6, 'F', 'experience', ['years' => 0, 'months' => 8],  21.0],
            [ 7, 'M', 'experience', ['years' => 1, 'months' => 0],  23.0],
            [ 8, 'F', 'experience', ['years' => 1, 'months' => 6],  25.0],
            [ 6, 'M', 'kyu',        9,                              22.0],
            [ 7, 'F', 'kyu',        9,                              24.0],
            [ 8, 'M', 'kyu',        8,                              27.0],
            [ 5, 'F', 'experience', ['years' => 0, 'months' => 4],  20.0],
            [ 8, 'M', 'kyu',        9,                              26.0],
            [ 7, 'F', 'kyu',        8,                              28.0],

            // ── 9-11 Years (20 entries) ── weights 29-45 kg ──────────────────────────
            [ 9, 'M', 'kyu',        9,                              31.0],
            [ 9, 'F', 'kyu',        9,                              29.0],
            [10, 'M', 'kyu',        8,                              36.0],
            [10, 'F', 'kyu',        8,                              33.0],
            [11, 'M', 'kyu',        7,                              42.0],
            [11, 'F', 'kyu',        7,                              38.0],
            [ 9, 'M', 'experience', ['years' => 2, 'months' => 0],  32.0],
            [10, 'F', 'kyu',        9,                              34.0],
            [11, 'M', 'kyu',        8,                              40.0],
            [ 9, 'F', 'experience', ['years' => 1, 'months' => 6],  30.0],
            [10, 'M', 'kyu',        7,                              37.0],
            [11, 'F', 'kyu',        8,                              39.0],
            [ 9, 'M', 'kyu',        8,                              33.0],
            [10, 'F', 'kyu',        7,                              35.0],
            [11, 'M', 'kyu',        9,                              41.0],
            [ 9, 'F', 'kyu',        8,                              31.0],
            [10, 'M', 'kyu',        9,                              35.0],
            [11, 'F', 'kyu',        9,                              38.0],
            [ 9, 'M', 'kyu',        7,                              34.0],
            [10, 'F', 'kyu',        7,                              36.0],

            // ── 12-14 Years (20 entries) ── weights 42-65 kg ─────────────────────────
            [12, 'M', 'kyu',        8,                              45.0],
            [12, 'F', 'kyu',        8,                              42.0],
            [13, 'M', 'kyu',        7,                              52.0],
            [13, 'F', 'kyu',        7,                              48.0],
            [14, 'M', 'kyu',        6,                              60.0],
            [14, 'F', 'kyu',        6,                              55.0],
            [12, 'M', 'kyu',        7,                              47.0],
            [12, 'F', 'kyu',        7,                              44.0],
            [13, 'M', 'kyu',        6,                              54.0],
            [13, 'F', 'kyu',        6,                              50.0],
            [14, 'M', 'kyu',        5,                              63.0],
            [14, 'F', 'kyu',        5,                              58.0],
            [12, 'M', 'kyu',        6,                              49.0],
            [12, 'F', 'kyu',        6,                              46.0],
            [13, 'M', 'kyu',        5,                              56.0],
            [13, 'F', 'kyu',        5,                              52.0],
            [14, 'M', 'kyu',        4,                              65.0],
            [14, 'F', 'kyu',        4,                              61.0],
            [12, 'M', 'kyu',        5,                              50.0],
            [12, 'F', 'kyu',        5,                              48.0],

            // ── 15-39 Years (30 entries) ── weights 58-100 kg ────────────────────────
            [15, 'M', 'kyu',        5,                              68.0],
            [15, 'F', 'kyu',        5,                              58.0],
            [17, 'M', 'kyu',        4,                              74.0],
            [17, 'F', 'kyu',        4,                              62.0],
            [20, 'M', 'kyu',        3,                              80.0],
            [20, 'F', 'kyu',        3,                              65.0],
            [22, 'M', 'kyu',        2,                              85.0],
            [22, 'F', 'kyu',        2,                              68.0],
            [25, 'M', 'kyu',        1,                              90.0],
            [25, 'F', 'kyu',        1,                              72.0],
            [28, 'M', 'dan',        1,                              88.0],
            [28, 'F', 'dan',        1,                              70.0],
            [30, 'M', 'dan',        1,                              92.0],
            [30, 'F', 'dan',        1,                              74.0],
            [33, 'M', 'dan',        2,                              95.0],
            [33, 'F', 'dan',        2,                              76.0],
            [35, 'M', 'dan',        2,                              98.0],
            [35, 'F', 'dan',        2,                              78.0],
            [18, 'M', 'kyu',        3,                              77.0],
            [18, 'F', 'kyu',        3,                              63.0],
            [23, 'M', 'dan',        1,                              83.0],
            [23, 'F', 'dan',        1,                              67.0],
            [27, 'M', 'dan',        2,                              91.0],
            [27, 'F', 'kyu',        1,                              71.0],
            [31, 'M', 'dan',        3,                              97.0],
            [32, 'F', 'dan',        2,                              75.0],
            [36, 'M', 'dan',        3,                             100.0],
            [37, 'F', 'dan',        1,                              73.0],
            [38, 'M', 'kyu',        1,                              87.0],
            [39, 'F', 'dan',        2,                              77.0],

            // ── 40+ Years (20 entries) ── weights 70-102 kg ──────────────────────────
            [40, 'M', 'dan',        2,                              95.0],
            [40, 'F', 'dan',        1,                              72.0],
            [42, 'M', 'dan',        3,                              98.0],
            [43, 'F', 'dan',        2,                              76.0],
            [45, 'M', 'dan',        2,                              93.0],
            [46, 'F', 'kyu',        1,                              70.0],
            [48, 'M', 'dan',        3,                              97.0],
            [50, 'F', 'dan',        1,                              74.0],
            [52, 'M', 'dan',        4,                             102.0],
            [53, 'F', 'dan',        2,                              78.0],
            [55, 'M', 'dan',        3,                              95.0],
            [56, 'F', 'dan',        2,                              76.0],
            [58, 'M', 'dan',        4,                              90.0],
            [59, 'F', 'dan',        3,                              80.0],
            [60, 'M', 'dan',        5,                              88.0],
            [62, 'F', 'dan',        3,                              74.0],
            [63, 'M', 'dan',        3,                              92.0],
            [65, 'F', 'dan',        4,                              78.0],
            [41, 'M', 'kyu',        1,                              89.0],
            [44, 'F', 'dan',        1,                              71.0],
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
                'rank_kyu'          => $rankType === 'kyu'        ? $rankVal           : null,
                'rank_dan'          => $rankType === 'dan'        ? $rankVal           : null,
                'experience_years'  => $rankType === 'experience' ? ($rankVal['years']  ?? 0) : null,
                'experience_months' => $rankType === 'experience' ? ($rankVal['months'] ?? 0) : null,
            ];
        }

        return $profiles;
    }
}
