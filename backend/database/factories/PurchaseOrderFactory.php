<?php

namespace Database\Factories;

use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rnd_user_id' => User::factory()->fss(),
            'shopping_list_id' => null,
            'supplier_id' => Supplier::factory(),
            'po_number' => $this->faker->unique()->bothify('PO-#####'),
            'order_date' => $this->faker->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'total_amount' => $this->faker->randomFloat(2, 500, 5000),
            'status' => $this->faker->randomElement(['draft', 'ordered', 'received']),
            'lifecycle_status' => 'open_execution',
            'receipt_image' => null,
            'notes' => $this->faker->sentence(),
        ];
    }
}
