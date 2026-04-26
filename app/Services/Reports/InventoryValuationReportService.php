<?php

namespace App\Services\Reports;

use App\Models\Category;
use App\Models\ProductVariant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class InventoryValuationReportService
{
    public function categoryOptions(): array
    {
        return Category::query()
            ->select('categories.id', 'categories.name')
            ->whereHas('products', fn (Builder $query) => $query->where('is_active', true))
            ->withCount([
                'products as active_products_count' => fn (Builder $query) => $query->where('is_active', true),
            ])
            ->orderBy('categories.name')
            ->get()
            ->map(fn (Category $category): array => [
                'id' => (int) $category->id,
                'name' => $category->name,
                'active_products_count' => (int) ($category->active_products_count ?? 0),
            ])
            ->values()
            ->all();
    }

    public function report(array $filters = []): array
    {
        $asOf = Carbon::parse($filters['as_of'] ?? now()->toDateString())->endOfDay();
        $categoryId = isset($filters['category_id']) ? (int) $filters['category_id'] : null;

        $variants = ProductVariant::query()
            ->with([
                'product:id,name,is_active',
                'product.categories:id,name',
                'values:id,variant_type_id,value',
                'values.type:id,name',
            ])
            ->whereHas('product', fn (Builder $query) => $query->where('is_active', true))
            ->when($categoryId, function (Builder $query) use ($categoryId) {
                $query->whereHas('product.categories', fn (Builder $categoryQuery) => $categoryQuery->where('categories.id', $categoryId));
            })
            ->orderBy('product_id')
            ->orderBy('id')
            ->get();

        $quantities = $this->quantitiesAsOf($variants, $asOf);

        $rows = $variants
            ->map(function (ProductVariant $variant) use ($quantities): ?array {
                $quantity = (int) ($quantities[$variant->id] ?? 0);

                if ($quantity <= 0) {
                    return null;
                }

                $averageCost = round((float) ($variant->average_cost ?? 0), 2);
                $salePrice = round((float) ($variant->regular_price ?? 0), 2);
                $assetValue = round($quantity * $averageCost, 2);
                $retailValue = round($quantity * $salePrice, 2);
                $categoryName = $variant->product?->categories
                    ?->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                    ->first()
                    ?->name ?? 'Uncategorized';

                return [
                    'variant_id' => (int) $variant->id,
                    'category_name' => $categoryName,
                    'variant_name' => $variant->display_name,
                    'sku' => $variant->sku,
                    'on_hand' => $quantity,
                    'average_cost' => $averageCost,
                    'asset_value' => $assetValue,
                    'sale_price' => $salePrice,
                    'retail_value' => $retailValue,
                ];
            })
            ->filter()
            ->values();

        $totalOnHand = (int) $rows->sum('on_hand');
        $totalAssetValue = round((float) $rows->sum('asset_value'), 2);
        $totalRetailValue = round((float) $rows->sum('retail_value'), 2);

        $groups = $rows
            ->groupBy('category_name')
            ->map(function (Collection $items, string $categoryName) use ($totalAssetValue, $totalRetailValue) {
                $sortedItems = $items
                    ->sortBy([
                        ['asset_value', 'desc'],
                        ['variant_name', 'asc'],
                    ])
                    ->values()
                    ->map(function (array $row) use ($totalAssetValue, $totalRetailValue) {
                        $row['asset_percent'] = $totalAssetValue > 0
                            ? round(($row['asset_value'] / $totalAssetValue) * 100, 2)
                            : 0.0;
                        $row['retail_percent'] = $totalRetailValue > 0
                            ? round(($row['retail_value'] / $totalRetailValue) * 100, 2)
                            : 0.0;

                        return $row;
                    })
                    ->values();

                $groupAssetValue = round((float) $sortedItems->sum('asset_value'), 2);
                $groupRetailValue = round((float) $sortedItems->sum('retail_value'), 2);

                return [
                    'category_name' => $categoryName,
                    'row_count' => $sortedItems->count(),
                    'totals' => [
                        'on_hand' => (int) $sortedItems->sum('on_hand'),
                        'asset_value' => $groupAssetValue,
                        'asset_percent' => $totalAssetValue > 0 ? round(($groupAssetValue / $totalAssetValue) * 100, 2) : 0.0,
                        'retail_value' => $groupRetailValue,
                        'retail_percent' => $totalRetailValue > 0 ? round(($groupRetailValue / $totalRetailValue) * 100, 2) : 0.0,
                    ],
                    'rows' => $sortedItems->all(),
                ];
            })
            ->sortKeys(SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        return [
            'filters' => [
                'as_of' => $asOf->toDateString(),
                'category_id' => $categoryId,
            ],
            'summary' => [
                'as_of_label' => $asOf->format('F j, Y'),
                'total_on_hand' => $totalOnHand,
                'total_asset_value' => $totalAssetValue,
                'total_retail_value' => $totalRetailValue,
                'category_count' => count($groups),
                'variant_count' => $rows->count(),
            ],
            'groups' => $groups,
        ];
    }

    public function exportPayload(array $filters = [], ?User $generatedBy = null): array
    {
        $report = $this->report($filters);
        $category = !empty($report['filters']['category_id'])
            ? Category::query()->select('id', 'name')->find((int) $report['filters']['category_id'])
            : null;

        return [
            'filters' => $report['filters'],
            'summary' => $report['summary'],
            'groups' => $report['groups'],
            'selected_category' => $category ? [
                'id' => (int) $category->id,
                'name' => $category->name,
            ] : null,
            'generated_at' => now(),
            'generated_by' => $generatedBy?->name,
        ];
    }

    protected function quantitiesAsOf(Collection $variants, Carbon $asOf): array
    {
        $variantIds = $variants->pluck('id')->filter()->values();

        if ($variantIds->isEmpty()) {
            return [];
        }

        $isCurrentCutoff = $asOf->copy()->endOfDay()->greaterThanOrEqualTo(now()->endOfDay());

        if ($isCurrentCutoff) {
            return $variants
                ->mapWithKeys(fn (ProductVariant $variant) => [
                    $variant->id => max((int) ($variant->quantity ?? 0), 0),
                ])
                ->all();
        }

        $ledgerQuantities = DB::table('stock_entries')
            ->whereIn('variant_id', $variantIds->all())
            ->where('effective_at', '<=', $asOf->toDateTimeString())
            ->select('variant_id')
            ->selectRaw("SUM(CASE WHEN type = 'stock_in' THEN quantity ELSE -quantity END) as quantity_on_hand")
            ->groupBy('variant_id')
            ->pluck('quantity_on_hand', 'variant_id');

        return $variants
            ->mapWithKeys(function (ProductVariant $variant) use ($ledgerQuantities, $asOf) {
                $ledgerQuantity = $ledgerQuantities->get($variant->id);

                if ($ledgerQuantity !== null) {
                    return [$variant->id => max((int) round((float) $ledgerQuantity), 0)];
                }

                if (optional($variant->created_at)->lessThanOrEqualTo($asOf)) {
                    return [$variant->id => max((int) ($variant->quantity ?? 0), 0)];
                }

                return [$variant->id => 0];
            })
            ->all();
    }
}
