<?php

/**
 * LFP Martial Arts Invitational Tournament — Entry Form Reference Data
 * Source: "LFP Entry Form New - Yakusuko.pdf"  (Mar-26 Round 1 - Yakusuko)
 *
 * Entry fees: 1st event $38, additional events $12.
 * Sumo & Semi Contact require weight confirmation on entry.
 * Karate mitts & mouth guard compulsory in all opponent events.
 *
 * This file is used by seeders; see LFPCompetitionSeeder for the full setup.
 */

return [

    // -------------------------------------------------------------------------
    // Kata (KA) — judges_total, once_off, age_rank, mixed
    // -------------------------------------------------------------------------
    'kata' => [
        ['code' => 'KA01', 'age' => '8 & Under',   'rank' => 'Open',        'sex' => 'Mixed'],
        ['code' => 'KA02', 'age' => '9–11 Years',  'rank' => '10–6 Kyu',    'sex' => 'Mixed'],
        ['code' => 'KA03', 'age' => '9–11 Years',  'rank' => '5 Kyu–Black', 'sex' => 'Mixed'],
        ['code' => 'KA04', 'age' => '12–14 Years', 'rank' => '10–6 Kyu',    'sex' => 'Mixed'],
        ['code' => 'KA05', 'age' => '12–14 Years', 'rank' => '5th–Black',   'sex' => 'Mixed'],
        ['code' => 'KA06', 'age' => '15+ Years',   'rank' => '10–6 Kyu',    'sex' => 'Mixed'],
        ['code' => 'KA07', 'age' => '15+ Years',   'rank' => '5–1 Kyu',     'sex' => 'Mixed'],
        ['code' => 'KA08', 'age' => '15+ Years',   'rank' => 'Black',       'sex' => 'Mixed'],
        ['code' => 'KA09', 'age' => '40+ Years',   'rank' => '10–6 Kyu',    'sex' => 'Mixed'],
        ['code' => 'KA10', 'age' => '40+ Years',   'rank' => '5th–Black',   'sex' => 'Mixed'],
    ],

    // -------------------------------------------------------------------------
    // Tile Breaking (TB) — judges_total, once_off, age_rank, mixed
    // Same age/rank bands as Kata KA01–KA08
    // -------------------------------------------------------------------------
    'tile_breaking' => [
        ['code' => 'TB01', 'age' => '8 & Under',   'rank' => 'Open',        'sex' => 'Mixed'],
        ['code' => 'TB02', 'age' => '9–11 Years',  'rank' => '10–6 Kyu',    'sex' => 'Mixed'],
        ['code' => 'TB03', 'age' => '9–11 Years',  'rank' => '5 Kyu–Black', 'sex' => 'Mixed'],
        ['code' => 'TB04', 'age' => '12–14 Years', 'rank' => '10–6 Kyu',    'sex' => 'Mixed'],
        ['code' => 'TB05', 'age' => '12–14 Years', 'rank' => '5th–Black',   'sex' => 'Mixed'],
        ['code' => 'TB06', 'age' => '15+ Years',   'rank' => '10–6 Kyu',    'sex' => 'Mixed'],
        ['code' => 'TB07', 'age' => '15+ Years',   'rank' => '5–1 Kyu',     'sex' => 'Mixed'],
        ['code' => 'TB08', 'age' => '15+ Years',   'rank' => 'Black',       'sex' => 'Mixed'],
    ],

    // -------------------------------------------------------------------------
    // Yakusuko (YA) — judges_total, once_off, age_only, mixed, requires_partner
    // -------------------------------------------------------------------------
    'yakusuko' => [
        ['code' => 'YA01', 'age' => '8 & Under',   'rank' => 'Open', 'sex' => 'Mixed'],
        ['code' => 'YA02', 'age' => '9–11 Years',  'rank' => 'Open', 'sex' => 'Mixed'],
        ['code' => 'YA03', 'age' => '12–14 Years', 'rank' => 'Open', 'sex' => 'Mixed'],
        ['code' => 'YA04', 'age' => '15+ Years',   'rank' => 'Open', 'sex' => 'Mixed'],
    ],

    // -------------------------------------------------------------------------
    // Semi Contact (SC) — win_loss, single_elimination, custom age/sex groups
    // Weight confirmed on entry. Custom age bands (not standard).
    // -------------------------------------------------------------------------
    'semi_contact' => [
        ['code' => 'SC01', 'age' => 'Under 11',  'sex' => 'Mixed',  'note' => 'Matched Opponent'],
        ['code' => 'SC02', 'age' => 'Under 15',  'sex' => 'Mixed',  'note' => 'Matched Opponent'],
        ['code' => 'SC03', 'age' => '15+ Years', 'sex' => 'Female', 'note' => 'Matched Opponent'],
        ['code' => 'SC04', 'age' => '15+ Years', 'sex' => 'Male',   'note' => 'Matched Opponent'],
    ],

    // -------------------------------------------------------------------------
    // Point Sparring (PS) — win_loss, single_elimination, age_rank_sex
    // -------------------------------------------------------------------------
    'point_sparring' => [
        ['code' => 'PS01', 'age' => '8 & Under',   'rank' => 'Open',        'sex' => 'Mixed'],
        ['code' => 'PS02', 'age' => '9–11 Years',  'rank' => '10–6 Kyu',    'sex' => 'Mixed'],
        ['code' => 'PS03', 'age' => '9–11 Years',  'rank' => '5 Kyu–Black', 'sex' => 'Mixed'],
        ['code' => 'PS04', 'age' => '12–14 Years', 'rank' => '10–6 Kyu',    'sex' => 'Mixed'],
        ['code' => 'PS05', 'age' => '12–14 Years', 'rank' => '5th–Black',   'sex' => 'Mixed'],
        ['code' => 'PS06', 'age' => '15+',          'rank' => '10–6 Kyu',    'sex' => 'Female'],
        ['code' => 'PS07', 'age' => '15+',          'rank' => '5–1 Kyu',     'sex' => 'Female'],
        ['code' => 'PS08', 'age' => '15+',          'rank' => 'Black',       'sex' => 'Female'],
        ['code' => 'PS09', 'age' => '15+',          'rank' => '10–6 Kyu',    'sex' => 'Male'],
        ['code' => 'PS10', 'age' => '15+',          'rank' => '5–1 Kyu',     'sex' => 'Male'],
        ['code' => 'PS11', 'age' => '15+',          'rank' => 'Black',       'sex' => 'Male'],
        ['code' => 'PS12', 'age' => '40+',          'rank' => '10–6 Kyu',    'sex' => 'Female'],
        ['code' => 'PS13', 'age' => '40+',          'rank' => '5th–Black',   'sex' => 'Female'],
        ['code' => 'PS14', 'age' => '40+',          'rank' => '10–6 Kyu',    'sex' => 'Male'],
        ['code' => 'PS15', 'age' => '40+',          'rank' => '5th–Black',   'sex' => 'Male'],
    ],

    // -------------------------------------------------------------------------
    // Continuous Sparring (CS) — same divisions as Point Sparring
    // -------------------------------------------------------------------------
    'continuous_sparring' => [
        ['code' => 'CS01', 'age' => '8 & Under',   'rank' => 'Open',        'sex' => 'Mixed'],
        ['code' => 'CS02', 'age' => '9–11 Years',  'rank' => '10–6 Kyu',    'sex' => 'Mixed'],
        ['code' => 'CS03', 'age' => '9–11 Years',  'rank' => '5 Kyu–Black', 'sex' => 'Mixed'],
        ['code' => 'CS04', 'age' => '12–14 Years', 'rank' => '10–6 Kyu',    'sex' => 'Mixed'],
        ['code' => 'CS05', 'age' => '12–14 Years', 'rank' => '5th–Black',   'sex' => 'Mixed'],
        ['code' => 'CS06', 'age' => '15+',          'rank' => '10–6 Kyu',    'sex' => 'Female'],
        ['code' => 'CS07', 'age' => '15+',          'rank' => '5–1 Kyu',     'sex' => 'Female'],
        ['code' => 'CS08', 'age' => '15+',          'rank' => 'Black',       'sex' => 'Female'],
        ['code' => 'CS09', 'age' => '15+',          'rank' => '10–6 Kyu',    'sex' => 'Male'],
        ['code' => 'CS10', 'age' => '15+',          'rank' => '5–1 Kyu',     'sex' => 'Male'],
        ['code' => 'CS11', 'age' => '15+',          'rank' => 'Black',       'sex' => 'Male'],
        ['code' => 'CS12', 'age' => '40+',          'rank' => '10–6 Kyu',    'sex' => 'Female'],
        ['code' => 'CS13', 'age' => '40+',          'rank' => '5th–Black',   'sex' => 'Female'],
        ['code' => 'CS14', 'age' => '40+',          'rank' => '10–6 Kyu',    'sex' => 'Male'],
        ['code' => 'CS15', 'age' => '40+',          'rank' => '5th–Black',   'sex' => 'Male'],
    ],

    // -------------------------------------------------------------------------
    // Sumo (SW) — win_loss, single_elimination, weight_sex
    // Weight confirmed on entry.
    // -------------------------------------------------------------------------
    'sumo' => [
        ['code' => 'SW01', 'weight' => 'Under 30 kg',  'division' => 'Flyweight',    'sex' => 'Female'],
        ['code' => 'SW02', 'weight' => 'Under 30 kg',  'division' => 'Flyweight',    'sex' => 'Male'],
        ['code' => 'SW03', 'weight' => 'Under 37 kg',  'division' => 'Featherweight','sex' => 'Female'],
        ['code' => 'SW04', 'weight' => 'Under 37 kg',  'division' => 'Featherweight','sex' => 'Male'],
        ['code' => 'SW05', 'weight' => 'Under 45 kg',  'division' => 'Bantamweight', 'sex' => 'Female'],
        ['code' => 'SW06', 'weight' => 'Under 45 kg',  'division' => 'Bantamweight', 'sex' => 'Male'],
        ['code' => 'SW07', 'weight' => 'Under 53 kg',  'division' => 'Lightweight',  'sex' => 'Female'],
        ['code' => 'SW08', 'weight' => 'Under 53 kg',  'division' => 'Lightweight',  'sex' => 'Male'],
        ['code' => 'SW09', 'weight' => 'Under 60 kg',  'division' => 'Welterweight', 'sex' => 'Female'],
        ['code' => 'SW10', 'weight' => 'Under 60 kg',  'division' => 'Welterweight', 'sex' => 'Male'],
        ['code' => 'SW11', 'weight' => 'Under 70 kg',  'division' => 'Middleweight', 'sex' => 'Female'],
        ['code' => 'SW12', 'weight' => 'Under 70 kg',  'division' => 'Middleweight', 'sex' => 'Male'],
        ['code' => 'SW13', 'weight' => 'Under 80 kg',  'division' => 'Cruiserweight','sex' => 'Female'],
        ['code' => 'SW14', 'weight' => 'Under 80 kg',  'division' => 'Cruiserweight','sex' => 'Male'],
        ['code' => 'SW15', 'weight' => '80+ kg',       'division' => 'Heavyweight',  'sex' => 'Female'],
        ['code' => 'SW16', 'weight' => '80+ kg',       'division' => 'Heavyweight',  'sex' => 'Male'],
    ],

    // -------------------------------------------------------------------------
    // Fees
    // -------------------------------------------------------------------------
    'fees' => [
        'first_event'       => 38,
        'additional_events' => 12,
    ],
];
