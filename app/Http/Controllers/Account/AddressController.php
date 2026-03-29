<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\CustomerAddress;
use App\Models\State;
use App\Services\CustomerAddressService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AddressController extends Controller
{
    public function __construct(
        protected CustomerAddressService $addressService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CustomerAddress::class);

        return Inertia::render('Account/Addresses/Index', [
            'addresses' => $this->addressService->paginate($request->user(), 10),
            'countries' => Country::query()->select('id', 'name')->orderBy('name')->get(),
            'states' => State::query()->select('id', 'name', 'country_id')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', CustomerAddress::class);
        $this->addressService->store($request->user(), $this->validated($request));

        return back()->with('success', 'Address saved successfully.');
    }

    public function update(Request $request, CustomerAddress $customerAddress): RedirectResponse
    {
        $this->authorize('update', $customerAddress);
        $this->addressService->update($request->user(), $customerAddress, $this->validated($request));

        return back()->with('success', 'Address updated successfully.');
    }

    public function destroy(Request $request, CustomerAddress $customerAddress): RedirectResponse
    {
        $this->authorize('delete', $customerAddress);
        $this->addressService->delete($request->user(), $customerAddress);

        return back()->with('success', 'Address removed.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'label' => ['required', 'string', 'max:60'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'line1' => ['required', 'string', 'max:255'],
            'line2' => ['nullable', 'string', 'max:255'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'lga_id' => ['nullable', 'integer', 'exists:lgas,id'],
            'postal_code' => ['nullable', 'string', 'max:30'],
            'is_default' => ['nullable', 'boolean'],
        ]);
    }
}
