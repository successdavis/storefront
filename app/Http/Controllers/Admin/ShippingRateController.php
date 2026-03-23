<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Shipping\StoreShippingRateRequest;
use App\Http\Requests\Admin\Shipping\UpdateShippingRateRequest;
use App\Models\ShippingRate;
use App\Services\ShippingManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShippingRateController extends Controller
{
    public function __construct(
        protected ShippingManagementService $shippingManagementService,
    ) {}

    public function index(Request $request): Response
    {
        $rates = $this->shippingManagementService->listRates($request->only(['search', 'status', 'method_id', 'scope']));

        return Inertia::render('Admin/ShippingRates/Index', [
            'filters' => $request->only(['search', 'status', 'method_id', 'scope']),
            'rates' => $rates->through(fn (ShippingRate $rate) => $this->shippingManagementService->toRateListPayload($rate)),
            'methods' => $this->shippingManagementService->formOptionsForRates()['methods'],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/ShippingRates/Form', [
            'mode' => 'create',
            'shippingRate' => null,
            ...$this->shippingManagementService->formOptionsForRates(),
        ]);
    }

    public function store(StoreShippingRateRequest $request): RedirectResponse
    {
        $rate = $this->shippingManagementService->createRate($request->validated());

        return redirect()
            ->route('admin.shipping-rates.edit', $rate)
            ->with('success', 'Shipping rate created successfully.');
    }

    public function edit(ShippingRate $shippingRate): Response
    {
        return Inertia::render('Admin/ShippingRates/Form', [
            'mode' => 'edit',
            'shippingRate' => $this->shippingManagementService->toRateFormPayload($shippingRate),
            ...$this->shippingManagementService->formOptionsForRates($shippingRate->state_id),
        ]);
    }

    public function update(UpdateShippingRateRequest $request, ShippingRate $shippingRate): RedirectResponse
    {
        $this->shippingManagementService->updateRate($shippingRate, $request->validated());

        return back()->with('success', 'Shipping rate updated successfully.');
    }

    public function toggleStatus(ShippingRate $shippingRate): RedirectResponse
    {
        $shippingRate->update(['is_active' => !$shippingRate->is_active]);

        return back()->with('success', 'Shipping rate status updated.');
    }
}
