<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryAlert;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockAuditSession;
use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InventoryDiscrepancyDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $director;

    protected function setUp(): void
    {
        parent::setUp();

        $this->director = User::factory()->create();
        $this->director->syncRoles([RoleNames::DIRECTOR]);
    }

    public function test_session_filter_lists_only_submitted_sessions_that_still_have_open_alerts(): void
    {
        $printers = Category::factory()->create(['name' => 'Printers']);
        $variant = $this->variant('Canon Laser Printer', 'CANON-LASER');

        $fullAudit = $this->auditSession(['submitted_at' => now()->subDays(2)]);
        $printerAudit = $this->auditSession([
            'status' => StockAuditSession::STATUS_REVIEWED,
            'scope_type' => StockAuditSession::SCOPE_CATEGORY,
            'category_id' => $printers->id,
            'submitted_at' => now()->subDay(),
        ]);
        $allResolved = $this->auditSession(['submitted_at' => now()]);
        $this->auditSession(['status' => StockAuditSession::STATUS_IN_PROGRESS, 'submitted_at' => null]);

        $this->alert($variant, ['meta' => $this->mismatchMeta($fullAudit, 4, 2)]);
        $this->alert($this->variant('Canon Toner', 'CANON-TONER'), ['meta' => $this->missingMeta($fullAudit)]);
        // A reviewed session can still own "not scanned" alerts.
        $this->alert($this->variant('HP Ink', 'HP-INK'), ['meta' => $this->missingMeta($printerAudit)]);
        $this->alert($this->variant('Old Item', 'OLD-ITEM'), [
            'status' => 'resolved',
            'meta' => $this->missingMeta($allResolved),
        ]);

        $this->actingAs($this->director)
            ->get(route('admin.inventory.discrepancies'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('InventoryDiscrepancies')
                ->has('sessionOptions', 2)
                // Newest submission first, not highest id.
                ->where('sessionOptions.0.id', $printerAudit->id)
                ->where('sessionOptions.0.reference', $printerAudit->reference())
                ->where('sessionOptions.0.scope_label', 'Printers')
                ->where('sessionOptions.0.open_count', 1)
                ->where('sessionOptions.1.id', $fullAudit->id)
                ->where('sessionOptions.1.scope_label', 'Full inventory')
                ->where('sessionOptions.1.open_count', 2)
            );

        // Deep links from audit history keep their label even with nothing open.
        $this->actingAs($this->director)
            ->get(route('admin.inventory.discrepancies', ['session_id' => $allResolved->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('sessionOptions', 3)
                ->where('sessionOptions.0.id', $allResolved->id)
                ->where('sessionOptions.0.open_count', 0)
                ->has('alerts.data', 0)
            );
    }

    public function test_issue_source_and_session_filters_classify_alerts_correctly(): void
    {
        $session = $this->auditSession();
        $otherSession = $this->auditSession();

        $mismatch = $this->alert($this->variant('Mismatch Item', 'MISMATCH-1'), [
            'meta' => $this->mismatchMeta($session, 10, 7, 'mobile'),
        ]);
        $missing = $this->alert($this->variant('Unscanned Item', 'MISSING-1'), [
            'meta' => $this->missingMeta($session),
        ]);
        $this->alert($this->variant('Other Session Item', 'OTHER-1'), [
            'meta' => $this->missingMeta($otherSession),
        ]);
        $this->alert($this->variant('Negative Item', 'NEGATIVE-1'), [
            'type' => 'negative_stock',
            'severity' => 'critical',
            'meta' => [],
        ]);
        $this->alert($this->variant('Ledger Item', 'LEDGER-1'), [
            'meta' => ['system_quantity' => 9, 'ledger_quantity' => 6, 'variance' => 3],
        ]);

        $this->assertDashboard([], 5, fn (Assert $page) => $page
            ->where('summary.mismatch', 1)
            ->where('summary.missing', 2)
            ->where('summary.negative', 1)
            ->where('summary.ledger', 1)
            ->where('summary.sessions', 2));

        $this->assertDashboard(['issue' => 'missing'], 2);
        $this->assertDashboard(['issue' => 'negative'], 1);
        $this->assertDashboard(['issue' => 'ledger'], 1);
        $this->assertDashboard(['issue' => 'mismatch'], 1, fn (Assert $page) => $page
            ->where('alerts.data.0.id', $mismatch->id)
            ->where('alerts.data.0.kind', 'mismatch')
            ->where('alerts.data.0.variance', -3)
            ->where('alerts.data.0.source', 'audit')
            ->where('alerts.data.0.source_label', 'Mobile audit')
            ->where('alerts.data.0.session.reference', $session->reference()));

        // Mobile/manual audit alerts used to be labelled "system" by the old page.
        $this->assertDashboard(['source' => 'audit'], 3);
        $this->assertDashboard(['source' => 'system'], 2);
        $this->assertDashboard(['session_id' => $session->id], 2);
        $this->assertDashboard(['session_id' => $session->id, 'issue' => 'missing'], 1, fn (Assert $page) => $page
            ->where('alerts.data.0.id', $missing->id)
            ->where('alerts.data.0.variance', null)
            // Summary cards stay usable as quick filters within the session.
            ->where('summary.total', 2)
            ->where('summary.mismatch', 1)
            ->where('summary.missing', 1));
        $this->assertDashboard(['severity' => 'critical'], 1);
    }

    public function test_search_category_and_detected_date_filters(): void
    {
        $laptops = Category::factory()->create(['name' => 'Laptops']);
        $powerBanks = Category::factory()->create(['name' => 'PowerBanks']);
        $session = $this->auditSession();

        $dell = $this->variant('Dell Latitude 5480', 'DELL-LAT-5480', $laptops);
        $anker = $this->variant('Anker PowerCore', 'ANKER-PC-10K', $powerBanks);

        // 23:30 UTC on the 29th is already 00:30 on the 30th in Lagos.
        $this->alert($dell, [
            'meta' => $this->mismatchMeta($session, 3, 2),
            'first_detected_at' => Carbon::parse('2026-09-29 23:30:00', 'UTC'),
        ]);
        $this->alert($anker, [
            'meta' => $this->missingMeta($session),
            'first_detected_at' => Carbon::parse('2026-09-28 10:00:00', 'UTC'),
        ]);

        $this->assertDashboard(['search' => 'dell 5480'], 1, fn (Assert $page) => $page
            ->where('alerts.data.0.sku', 'DELL-LAT-5480'));
        $this->assertDashboard(['search' => 'anker-pc'], 1);
        $this->assertDashboard(['category_id' => $powerBanks->id], 1, fn (Assert $page) => $page
            ->where('alerts.data.0.sku', 'ANKER-PC-10K')
            ->has('categoryOptions', 2)
            ->where('categoryOptions.0.name', 'Laptops')
            ->where('categoryOptions.0.open_count', 1));

        $this->assertDashboard(['from' => '2026-09-30', 'to' => '2026-09-30', 'tz' => 'Africa/Lagos'], 1, fn (Assert $page) => $page
            ->where('alerts.data.0.sku', 'DELL-LAT-5480'));
        $this->assertDashboard(['from' => '2026-09-30', 'to' => '2026-09-30'], 0);
        // A reversed range is swapped rather than returning nothing.
        $this->assertDashboard(['from' => '2026-09-30', 'to' => '2026-09-27'], 2);
    }

    public function test_summary_totals_shortage_and_overage_units_from_audit_counts(): void
    {
        $session = $this->auditSession();

        $this->alert($this->variant('Short Item', 'SHORT-1'), ['meta' => $this->mismatchMeta($session, 10, 6)]);
        $this->alert($this->variant('Over Item', 'OVER-1'), ['meta' => $this->mismatchMeta($session, 2, 5)]);
        $this->alert($this->variant('Unscanned Item', 'MISSING-2'), ['meta' => $this->missingMeta($session)]);
        $this->alert($this->variant('Ledger Item', 'LEDGER-2'), [
            'meta' => ['system_quantity' => 20, 'ledger_quantity' => 5, 'variance' => 15],
        ]);

        $this->assertDashboard([], 4, fn (Assert $page) => $page
            ->where('summary.shortage_units', 4)
            ->where('summary.overage_units', 3));

        $this->assertDashboard(['sort' => 'variance'], 4, fn (Assert $page) => $page
            ->where('alerts.data.0.sku', 'LEDGER-2')
            ->where('alerts.data.1.sku', 'SHORT-1')
            ->where('alerts.data.2.sku', 'OVER-1')
            ->where('alerts.data.3.sku', 'MISSING-2'));
    }

    public function test_resolving_all_matching_alerts_respects_filters_and_the_rendered_snapshot(): void
    {
        $session = $this->auditSession();
        $otherSession = $this->auditSession();

        $unscanned = collect(range(1, 3))->map(fn (int $index) => $this->alert(
            $this->variant("Unscanned {$index}", "UNSCANNED-{$index}"),
            ['meta' => $this->missingMeta($session)],
        ));
        $mismatch = $this->alert($this->variant('Counted Item', 'COUNTED-1'), [
            'meta' => $this->mismatchMeta($session, 5, 4),
        ]);
        $otherSessionAlert = $this->alert($this->variant('Other Session', 'OTHER-2'), [
            'meta' => $this->missingMeta($otherSession),
        ]);

        $filters = ['session_id' => $session->id, 'issue' => 'missing'];
        $untilId = $unscanned->max('id');

        // Raised after the admin loaded the page: must not be resolved unseen.
        $lateArrival = $this->alert($this->variant('Late Arrival', 'LATE-1'), [
            'meta' => $this->missingMeta($session),
        ]);

        $this->actingAs($this->director)
            ->from(route('admin.inventory.discrepancies', $filters))
            ->post(route('admin.inventory.discrepancies.resolve'), [
                'all_matching' => true,
                'until_id' => $untilId,
                'filters' => $filters,
            ])
            ->assertRedirect(route('admin.inventory.discrepancies', $filters))
            ->assertSessionHas('success', 'Resolved 3 alerts.');

        foreach ($unscanned as $alert) {
            $this->assertDatabaseHas('inventory_alerts', [
                'id' => $alert->id,
                'status' => 'resolved',
                'resolved_by' => $this->director->id,
                'resolved_reason' => 'Resolved from discrepancy dashboard.',
            ]);
        }

        foreach ([$mismatch, $otherSessionAlert, $lateArrival] as $alert) {
            $this->assertDatabaseHas('inventory_alerts', ['id' => $alert->id, 'status' => 'open']);
        }
    }

    public function test_resolve_all_matching_requires_a_snapshot_bound(): void
    {
        $this->actingAs($this->director)
            ->post(route('admin.inventory.discrepancies.resolve'), [
                'all_matching' => true,
                'filters' => ['issue' => 'missing'],
            ])
            ->assertSessionHasErrors('until_id');
    }

    protected function assertDashboard(array $query, int $expectedCount, ?callable $extra = null): void
    {
        $this->actingAs($this->director)
            ->get(route('admin.inventory.discrepancies', $query))
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($expectedCount, $extra) {
                $page->component('InventoryDiscrepancies')
                    ->has('alerts.data', $expectedCount)
                    ->where('alerts.total', $expectedCount);

                if ($extra) {
                    $extra($page);
                }
            });
    }

    protected function variant(string $productName, string $sku, ?Category $category = null): ProductVariant
    {
        $product = Product::factory()->create(['name' => $productName]);

        if ($category) {
            $product->categories()->attach($category->id);
        }

        return ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => $sku,
            'quantity' => 5,
        ]);
    }

    protected function auditSession(array $attributes = []): StockAuditSession
    {
        return StockAuditSession::query()->create(array_merge([
            'scope_type' => StockAuditSession::SCOPE_FULL,
            'status' => StockAuditSession::STATUS_SUBMITTED,
            'source' => StockAuditSession::SOURCE_MOBILE,
            'total_expected_items' => 10,
            'total_scanned_items' => 4,
            'coverage_percentage' => 40,
            'is_partial' => true,
            'started_at' => now()->subDays(3),
            'submitted_at' => now()->subDay(),
        ], $attributes));
    }

    protected function alert(ProductVariant $variant, array $attributes = []): InventoryAlert
    {
        return InventoryAlert::query()->create(array_merge([
            'type' => 'discrepancy',
            'severity' => 'medium',
            'variant_id' => $variant->id,
            'message' => 'Inventory discrepancy',
            'status' => 'open',
            'first_detected_at' => now(),
            'last_seen_at' => now(),
        ], $attributes));
    }

    protected function mismatchMeta(StockAuditSession $session, int $system, int $physical, string $channel = 'manual'): array
    {
        return [
            'system_quantity' => $system,
            'physical_quantity' => $physical,
            'variance' => $physical - $system,
            'audit_session_id' => $session->id,
            'source' => $channel,
        ];
    }

    protected function missingMeta(StockAuditSession $session): array
    {
        return [
            'audit_session_id' => $session->id,
            'source' => 'mobile',
            'unknown_state' => true,
            'missing_item' => true,
        ];
    }
}
