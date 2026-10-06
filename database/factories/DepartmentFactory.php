<?php

namespace Database\Factories;

use App\Knowledge\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Engineering', 'Marketing', 'Sales', 'Support', 'HR']),
        ];
    }
}
