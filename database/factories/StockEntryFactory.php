<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\ProductVariant;
use App\Models\StockEntry;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockEntryFactory extends Factory
{
    protected $model = StockEntry::class;

    public function definition(): array
    {
        // Ensure related records exist
        $variant   = ProductVariant::query()->inRandomOrder()->first()
            ?? ProductVariant::factory()->create();

        $employee  = User::query()->inRandomOrder()->first()
            ?? User::factory()->create();

        $warehouse = Warehouse::query()->inRandomOrder()->first()
            ?? Warehouse::factory()->create();

        $unitCost = $variant->cost_price ?? $this->faker->randomFloat(2, 10_000, 200_000);

        return [
            'warehouse_id' => $warehouse->id,
            'variant_id'   => $variant->id,
            'quantity'     => $this->faker->numberBetween(1, 100),
            'unit_cost'    => $unitCost,
            'type'         => $this->faker->randomElement(['stock_in', 'stock_out']),
            'employee_id'  => $employee->id,
            'reason'       => $this->faker->optional()->sentence(3),
            // ✅ explicitly set effective_at so tests/seeds can vary it
            'effective_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'note'         => $this->faker->optional()->sentence(),
        ];
    }

    /** Convenience states */
    public function stockIn(): self
    {
        return $this->state(fn () => ['type' => 'stock_in']);
    }

    public function stockOut(): self
    {
        return $this->state(fn () => ['type' => 'stock_out']);
    }

    public function forVariant(ProductVariant $variant): self
    {
        return $this->state(fn () => [
            'variant_id'  => $variant->id,
            'unit_cost'   => $variant->cost_price ?? $this->faker->randomFloat(2, 10_000, 200_000),
        ]);
    }

    public function forEmployee(Employee $employee): self
    {
        return $this->state(fn () => ['employee_id' => $employee->id]);
    }

    public function forWarehouse(Warehouse $warehouse): self
    {
        return $this->state(fn () => ['warehouse_id' => $warehouse->id]);
    }

    /**
     * Create an entry with a specific effective date.
     */
    public function effectiveAt(\DateTimeInterface $date): self
    {
        return $this->state(fn () => ['effective_at' => $date]);
    }
}
