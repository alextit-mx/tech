<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->words(3, true);

        return ['sku' => fake()->unique()->bothify('SKU-#####'), 'name' => $name, 'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'), 'category_id' => Category::factory(), 'brand_id' => Brand::factory(), 'cost' => fake()->randomFloat(2, 100, 10000), 'cost_currency' => 'MXN', 'price' => fake()->randomFloat(2, 200, 15000), 'price_mode' => 'show', 'stock' => fake()->numberBetween(0, 20), 'specs' => []];
    }
}
