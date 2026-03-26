<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\Support\VariantNameFormatter;
use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use App\Services\StockAdjustmentApprovalService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Throwable;

class StockAdjustmentController extends Controller
{
    public function __construct(
        protected StockAdjustmentApprovalService $approvalService,
        protected VariantNameFormatter $variantNameFormatter,
    ) {}

    public function index()
    {
        $adjustments = StockAdjustment::with([
            'variant:id,product_id,sku',
            'variant.product:id,name',
            'variant.values:id,variant_type_id,value',
            'variant.values.type:id,name',
            'warehouse:id,name',
            'employee:id,name',
            'approver:id,name',
            'rejector:id,name',
        ])
            ->latest('adjusted_at')
            ->paginate(15)
            ->through(fn ($item) => [
                'id' => $item->id,
                'variant_label' => $item->variant ? $this->variantNameFormatter->format($item->variant) : 'N/A',
                'variant_sku' => $item->variant?->sku,
                'warehouse' => $item->warehouse?->name,
                'employee' => $item->employee?->name,
                'previous_quantity' => $item->previous_quantity,
                'adjusted_quantity' => $item->adjusted_quantity,
                'new_quantity' => $item->new_quantity,
                'reason' => ucfirst(str_replace('_', ' ', $item->reason)),
                'adjusted_at' => optional($item->adjusted_at)?->toDateTimeString(),
                'status' => $item->status ?? StockAdjustment::STATUS_PENDING,
                'approved_by' => $item->approver?->name,
                'approved_at' => optional($item->approved_at)?->toDateTimeString(),
                'rejected_by' => $item->rejector?->name,
                'rejected_at' => optional($item->rejected_at)?->toDateTimeString(),
            ]);

        return Inertia::render('StockAdjustments/Index', [
            'adjustments' => $adjustments,
        ]);
    }

    public function create()
    {
        $variants = ProductVariant::with([
            'product:id,name',
            'values:id,variant_type_id,value',
            'values.type:id,name',
        ])
            ->select('id', 'product_id', 'sku', 'quantity')
            ->distinct()
            ->get()
            ->map(function ($variant) {
                return [
                    'id' => $variant->id,
                    'label' => $this->variantNameFormatter->format($variant),
                    'current_quantity' => $variant->quantity ?? 0,
                ];
            });

        return Inertia::render('StockAdjustments/Create', [
            'variants' => $variants,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'variant_id' => 'required|exists:product_variants,id',
            'adjusted_quantity' => 'required|integer|not_in:0',
            'reason' => 'required|in:damage,loss,count_discrepancy,manual_correction,other',
            'reference' => 'nullable|string|max:100',
            'note' => 'nullable|string',
            'adjusted_at' => 'nullable|date',
        ]);

        $validated['adjusted_at'] = $validated['adjusted_at'] ?? now();

        try {
            $this->approvalService->submit($validated, (int) auth()->id());

            return redirect()
                ->route('admin.stock-adjustments.index')
                ->with('success', 'Stock adjustment submitted for approval. No inventory impact yet.');
        } catch (Throwable $e) {
            report($e);

            return back()
                ->withInput()
                ->with('error', 'An error occurred while submitting the stock adjustment: ' . $e->getMessage());
        }
    }

    public function show(StockAdjustment $stockAdjustment)
    {
        $stockAdjustment->load([
            'variant:id,product_id,sku',
            'variant.product:id,name',
            'variant.values:id,variant_type_id,value',
            'variant.values.type:id,name',
            'warehouse:id,name',
            'employee:id,name',
            'approver:id,name',
            'rejector:id,name',
        ]);

        return Inertia::render('StockAdjustments/Show', [
            'adjustment' => [
                'id' => $stockAdjustment->id,
                'product_variant' => $stockAdjustment->variant ? $this->variantNameFormatter->format($stockAdjustment->variant) : 'N/A',
                'product_sku' => $stockAdjustment->variant?->sku ?? 'N/A',
                'previous_quantity' => $stockAdjustment->previous_quantity,
                'adjusted_quantity' => $stockAdjustment->adjusted_quantity,
                'new_quantity' => $stockAdjustment->new_quantity,
                'reason' => $stockAdjustment->reason,
                'note' => $stockAdjustment->note,
                'warehouse' => $stockAdjustment->warehouse?->name ?? 'N/A',
                'employee' => $stockAdjustment->employee?->name ?? 'N/A',
                'created_at' => optional($stockAdjustment->created_at)?->format('Y-m-d H:i'),
                'status' => $stockAdjustment->status ?? StockAdjustment::STATUS_PENDING,
                'approved_by' => $stockAdjustment->approver?->name,
                'approved_at' => optional($stockAdjustment->approved_at)?->format('Y-m-d H:i'),
                'rejected_by' => $stockAdjustment->rejector?->name,
                'rejected_at' => optional($stockAdjustment->rejected_at)?->format('Y-m-d H:i'),
                'approval_note' => $stockAdjustment->approval_note,
                'can_approve' => ($stockAdjustment->status ?? StockAdjustment::STATUS_PENDING) === StockAdjustment::STATUS_PENDING,
            ],
        ]);
    }

    public function approve(Request $request, StockAdjustment $stockAdjustment)
    {
        $validated = $request->validate([
            'approval_note' => 'nullable|string|max:1000',
        ]);

        try {
            $this->approvalService->approve(
                stockAdjustment: $stockAdjustment,
                approverId: (int) auth()->id(),
                approvalNote: $validated['approval_note'] ?? null,
            );

            return back()->with('success', 'Stock adjustment approved and applied successfully.');
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first() ?? 'Unable to approve adjustment.');
        } catch (Throwable $e) {
            report($e);
            return back()->with('error', 'An error occurred while approving the adjustment.');
        }
    }

    public function reject(Request $request, StockAdjustment $stockAdjustment)
    {
        $validated = $request->validate([
            'approval_note' => 'nullable|string|max:1000',
        ]);

        try {
            $this->approvalService->reject(
                stockAdjustment: $stockAdjustment,
                rejectedBy: (int) auth()->id(),
                approvalNote: $validated['approval_note'] ?? null,
            );

            return back()->with('success', 'Stock adjustment rejected. No inventory impact was made.');
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first() ?? 'Unable to reject adjustment.');
        } catch (Throwable $e) {
            report($e);
            return back()->with('error', 'An error occurred while rejecting the adjustment.');
        }
    }

    public function destroy(StockAdjustment $stockAdjustment)
    {
        $stockAdjustment->delete();

        return back()->with('success', 'Stock adjustment deleted successfully.');
    }
}
