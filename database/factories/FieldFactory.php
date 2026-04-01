<?php

namespace Database\Factories;

use App\Models\Field;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Field>
 */
class FieldFactory extends Factory
{
  protected $model = Field::class;

  public function definition(): array
  {
    return [
      'name' => $this->faker->unique()->word(),
      'label' => $this->faker->words(2, true),
      'validation_rules' => null,
      'possible_values' => null,
      'user_id' => null,
      'updated_user_id' => null,
    ];
  }
}
