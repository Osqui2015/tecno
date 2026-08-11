<?php

namespace Database\Factories;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'action'       => 'test.action',
            'description'  => fake()->sentence(),
            'subject_type' => 'App\\Models\\Product',
            'subject_id'   => 1,
            'meta'         => ['foo' => 'bar'],
        ];
    }
}
