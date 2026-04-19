<?php

namespace Tests\Feature;

use App\Models\StorefrontAnalyticsPageView;
use App\Models\User;
use App\Services\Analytics\StorefrontAnalyticsAggregationService;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminStorefrontAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_analytics_dashboard_renders_aggregated_report(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        StorefrontAnalyticsPageView::query()->insert([
            [
                'visitor_key' => 'visitor1111111111111111111111111111',
                'user_id' => null,
                'occurred_on' => now()->toDateString(),
                'occurred_at' => now()->subHour(),
                'page_path' => '/store',
                'page_title' => 'Store',
                'component' => 'Storefront/Home',
                'country_code' => 'NG',
                'country_name' => 'Nigeria',
                'region_name' => 'Lagos',
                'device_type' => 'mobile',
                'referrer_domain' => 'google.com',
                'is_authenticated' => false,
                'is_new_visitor' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'visitor_key' => 'visitor2222222222222222222222222222',
                'occurred_on' => now()->toDateString(),
                'occurred_at' => now()->subMinutes(30),
                'page_path' => '/store/product/demo-product',
                'page_title' => 'Demo Product',
                'component' => 'Storefront/Product',
                'country_code' => 'NG',
                'country_name' => 'Nigeria',
                'region_name' => 'Abuja',
                'device_type' => 'desktop',
                'referrer_domain' => 'instagram.com',
                'is_authenticated' => true,
                'is_new_visitor' => false,
                'user_id' => $director->id,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        app(StorefrontAnalyticsAggregationService::class)->refreshRecentWindow(7);

        $this->actingAs($director)
            ->get(route('admin.analytics.index', ['range' => '7_days']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Analytics/Index')
                ->where('permissions.can_manage', true)
                ->where('report.filters.range', '7_days')
                ->where('report.summary_cards.0.value', 2)
                ->has('report.top_pages', 2)
                ->where('report.countries.0.label', 'Nigeria')
            );
    }

    public function test_non_admin_cannot_access_admin_analytics_dashboard(): void
    {
        $customer = User::factory()->create();
        $customer->syncRoles([RoleNames::CUSTOMER]);

        $this->actingAs($customer)
            ->get(route('admin.analytics.index'))
            ->assertForbidden();
    }

    public function test_admin_analytics_settings_page_renders_for_manager(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        $this->actingAs($director)
            ->get(route('admin.analytics.settings'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Analytics/Settings')
                ->where('permissions.can_manage', true)
                ->where('settings.enabled', true)
            );
    }

    public function test_admin_analytics_export_streams_csv(): void
    {
        $director = User::factory()->create();
        $director->syncRoles([RoleNames::DIRECTOR]);

        StorefrontAnalyticsPageView::query()->insert([
            [
                'visitor_key' => 'visitor3333333333333333333333333333',
                'user_id' => null,
                'occurred_on' => now()->toDateString(),
                'occurred_at' => now()->subHour(),
                'page_path' => '/store',
                'page_title' => 'Store',
                'component' => 'Storefront/Home',
                'country_code' => 'NG',
                'country_name' => 'Nigeria',
                'region_name' => 'Lagos',
                'device_type' => 'mobile',
                'referrer_domain' => 'google.com',
                'is_authenticated' => false,
                'is_new_visitor' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        app(StorefrontAnalyticsAggregationService::class)->refreshRecentWindow(7);

        $response = $this->actingAs($director)
            ->get(route('admin.analytics.export', ['type' => 'summary', 'range' => '7_days']));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertHeader('content-disposition');

        $content = $response->streamedContent();

        $this->assertStringContainsString('Metric,Value', $content);
        $this->assertStringContainsString('Page views', $content);
    }
}
