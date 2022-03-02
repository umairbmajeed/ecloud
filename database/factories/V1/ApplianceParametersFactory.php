<?php
namespace Database\Factories\V1;

use App\Models\V1\ApplianceParameter;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ApplianceParametersFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ApplianceParameter::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'appliance_script_parameters_uuid' => Str::uuid(),
            'appliance_script_parameters_name' => $this->faker->sentence(2),
            'appliance_script_parameters_key' => str_replace(' ', '_', $this->faker->words(2)),
            'appliance_script_parameters_type' => 'String',
            'appliance_script_parameters_description' => $this->faker->sentence(8),
            'appliance_script_parameters_required' => 'Yes',
        ];
    }
}
