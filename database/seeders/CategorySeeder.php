<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Food',      'slug' => 'food',      'icon' => '🍔', 'sort_order' => 1],
            ['name' => 'Pharmacy',  'slug' => 'pharmacy',  'icon' => '💊', 'sort_order' => 2],
            ['name' => 'Market',    'slug' => 'market',    'icon' => '🛒', 'sort_order' => 3],
            ['name' => 'Smoking',   'slug' => 'smoking',   'icon' => '🚬', 'sort_order' => 4],
        ];

        foreach ($categories as $cat) {
            DB::table('categories')->updateOrInsert(
                ['slug' => $cat['slug']],
                array_merge($cat, ['is_active' => true, 'created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
