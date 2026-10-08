<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'HTML', 'slug' => 'html', 'sort_order' => 1],
            ['name' => 'CSS', 'slug' => 'css', 'sort_order' => 2],
            ['name' => 'JavaScript', 'slug' => 'javascript', 'sort_order' => 3],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['slug' => $category['slug']],
                $category
            );
        }
    }
}
