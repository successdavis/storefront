<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontAnalyticsTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracking_endpoint_records_storefront_page_views(): void
    {
        $visitorKey = 'visitor1234567890abcdef1234567890';

        $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)')
            ->postJson(route('analytics.storefront.page-views'), [
                'events' => [
                    [
                        'visitor_key' => $visitorKey,
                        'page_path' => '/store',
                        'page_title' => 'Store',
                        'component' => 'Storefront/Home',
                        'occurred_at' => now()->toIso8601String(),
                        'location' => [
                            'country_code' => 'NG',
                            'country_name' => 'Nigeria',
                            'state_name' => 'Lagos',
                        ],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJson(['tracked' => 1]);

        $this->assertDatabaseHas('storefront_analytics_visitors', [
            'visitor_key' => $visitorKey,
            'last_page_path' => '/store',
            'last_country_code' => 'NG',
            'last_region_name' => 'Lagos',
            'last_device_type' => 'desktop',
        ]);

        $this->assertDatabaseHas('storefront_analytics_page_views', [
            'visitor_key' => $visitorKey,
            'page_path' => '/store',
            'component' => 'Storefront/Home',
            'country_code' => 'NG',
            'region_name' => 'Lagos',
        ]);
    }

    public function test_tracking_endpoint_ignores_non_storefront_pages(): void
    {
        $visitorKey = 'visitor1234567890abcdef1234567890';

        $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)')
            ->postJson(route('analytics.storefront.page-views'), [
                'events' => [
                    [
                        'visitor_key' => $visitorKey,
                        'page_path' => '/admin/orders',
                        'page_title' => 'Orders',
                        'component' => 'Admin/Orders/Index',
                        'occurred_at' => now()->toIso8601String(),
                    ],
                ],
            ])
            ->assertOk()
            ->assertJson(['tracked' => 0]);

        $this->assertDatabaseCount('storefront_analytics_page_views', 0);
        $this->assertDatabaseCount('storefront_analytics_visitors', 0);
    }
}
