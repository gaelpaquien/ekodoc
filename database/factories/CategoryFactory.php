<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // words(3, true) draws from a much larger combinatorial space
            // than word() alone, which — with a small finite word list —
            // can exhaust fake()->unique() across a larger test run and
            // throw an OverflowException.
            'name' => ucfirst(fake()->unique()->words(3, true)),
        ];
    }
}
