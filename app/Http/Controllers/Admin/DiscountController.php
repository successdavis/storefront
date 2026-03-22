<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Discount\StoreDiscountRequest;
use App\Http\Requests\Admin\Discount\UpdateDiscountRequest;
use App\Models\Discount;
use App\Services\DiscountManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DiscountController extends Controller
{
    public function __construct(
        protected DiscountManagementService $discountManagementService,
    ) {}

    public function index(Request $request): Response
    {
        $discounts = $this->discountManagementService->listAutomaticDiscounts($request->only(['search', 'status', 'scope']));

        return Inertia::render('Admin/Discounts/Index', [
            'filters' => $request->only(['search', 'status', 'scope']),
            'discounts' => $discounts->through(fn (Discount $discount) => $this->discountManagementService->toListPayload($discount)),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Discounts/Form', [
            'mode' => 'create',
            'discount' => null,
            ...$this->discountManagementService->formOptions(),
        ]);
    }

    public function store(StoreDiscountRequest $request): RedirectResponse
    {
        $discount = $this->discountManagementService->createAutomaticDiscount($request->validated());

        return redirect()
            ->route('admin.discounts.edit', $discount)
            ->with('success', 'Discount created successfully.');
    }

    public function edit(Discount $discount): Response
    {
        abort_if($discount->isCoupon(), 404);

        return Inertia::render('Admin/Discounts/Form', [
            'mode' => 'edit',
            'discount' => $this->discountManagementService->toFormPayload($discount),
            ...$this->discountManagementService->formOptions(),
        ]);
    }

    public function update(UpdateDiscountRequest $request, Discount $discount): RedirectResponse
    {
        abort_if($discount->isCoupon(), 404);

        $this->discountManagementService->updateAutomaticDiscount($discount, $request->validated());

        return back()->with('success', 'Discount updated successfully.');
    }

    public function toggleStatus(Discount $discount): RedirectResponse
    {
        abort_if($discount->isCoupon(), 404);

        $discount->update(['is_active' => !$discount->is_active]);

        return back()->with('success', 'Discount status updated.');
    }
}
