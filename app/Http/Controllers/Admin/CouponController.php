<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Discount\StoreCouponRequest;
use App\Http\Requests\Admin\Discount\UpdateCouponRequest;
use App\Models\Discount;
use App\Services\DiscountManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CouponController extends Controller
{
    public function __construct(
        protected DiscountManagementService $discountManagementService,
    ) {}

    public function index(Request $request): Response
    {
        $coupons = $this->discountManagementService->listCoupons($request->only(['search', 'status', 'scope']));

        return Inertia::render('Admin/Coupons/Index', [
            'filters' => $request->only(['search', 'status', 'scope']),
            'coupons' => $coupons->through(fn (Discount $discount) => $this->discountManagementService->toListPayload($discount)),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Coupons/Form', [
            'mode' => 'create',
            'coupon' => null,
            ...$this->discountManagementService->formOptions(),
        ]);
    }

    public function store(StoreCouponRequest $request): RedirectResponse
    {
        $coupon = $this->discountManagementService->createCoupon($request->validated());

        return redirect()
            ->route('admin.coupons.edit', $coupon)
            ->with('success', 'Coupon created successfully.');
    }

    public function edit(Discount $coupon): Response
    {
        abort_if(!$coupon->isCoupon(), 404);

        return Inertia::render('Admin/Coupons/Form', [
            'mode' => 'edit',
            'coupon' => $this->discountManagementService->toFormPayload($coupon),
            ...$this->discountManagementService->formOptions(),
        ]);
    }

    public function update(UpdateCouponRequest $request, Discount $coupon): RedirectResponse
    {
        abort_if(!$coupon->isCoupon(), 404);

        $this->discountManagementService->updateCoupon($coupon, $request->validated());

        return back()->with('success', 'Coupon updated successfully.');
    }

    public function toggleStatus(Discount $coupon): RedirectResponse
    {
        abort_if(!$coupon->isCoupon(), 404);

        $coupon->update(['is_active' => !$coupon->is_active]);

        return back()->with('success', 'Coupon status updated.');
    }
}
