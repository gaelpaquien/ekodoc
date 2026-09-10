<?php

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // words(3, true) draws from a much larger combinatorial space than
        // word() alone, which — with a small finite word list — can
        // exhaust fake()->unique() across a larger test run and throw an
        // OverflowException (same reasoning as the CategoryFactory it
        // replaces).
        return [
            'name' => ucfirst(fake()->unique()->words(3, true)),
        ];
    }
}
