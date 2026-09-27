<?php

namespace Database\Factories;

use App\Models\UsageEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsageEvent>
 */
class UsageEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event' => 'pdf_generated',
            'outcome' => 'success',
            'http_status' => 200,
            'source_type' => 'json',
            'feed_hash' => hash('sha256', $this->faker->unique()->url()),
            'feed_host' => 'example.org',
            'referrer_host' => null,
            'meeting_count' => 120,
            'region_count' => null,
            'upstream_status' => null,
            'chunked' => false,
            'duration_ms' => 800,
            'peak_memory_mb' => 64,
            'settings' => [
                'group_by' => 'day-region',
                'language' => 'en',
                'paper' => '4.25x11',
                'font' => 'serif',
                'font_size' => 12,
                'mode' => 'download',
                'numbering' => true,
                'regions_selected' => false,
                'front_cover' => false,
                'back_cover' => false,
                'options' => ['pagebreaks'],
            ],
        ];
    }
}
