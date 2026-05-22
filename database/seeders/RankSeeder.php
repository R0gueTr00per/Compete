<?php

namespace Database\Seeders;

use App\Models\Rank;
use Illuminate\Database\Seeder;

class RankSeeder extends Seeder
{
    public function run(): void
    {
        $ranks = [
            '9th Kyu', '8th Kyu', '7th Kyu', '6th Kyu', '5th Kyu',
            '4th Kyu', '3rd Kyu', '2nd Kyu', '1st Kyu',
            '1st Dan', '2nd Dan', '3rd Dan', '4th Dan', '5th Dan',
            '6th Dan', '7th Dan', '8th Dan', '9th Dan', '10th Dan',
        ];

        foreach ($ranks as $order => $name) {
            Rank::firstOrCreate(['name' => $name], ['sort_order' => $order + 1]);
        }
    }
}
