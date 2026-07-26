<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Shipping\StorePickupLocationRequest;
use App\Http\Requests\Admin\Shipping\UpdatePickupLocationRequest;
use App\Models\PickupLocation;
use App\Models\ShippingMethod;
use App\Models\State;
use App\Services\ShippingManagementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class PickupLocationController extends Controller
{
    public function __construct(
        protected ShippingManagementService $shippingManagementService,
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->get('search', ''));
        $status = trim((string) $request->get('status', ''));

        $locations = PickupLocation::query()
            ->with(['method:id,name,method_type', 'state:id,name', 'lga:id,name'])
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $nested) use ($search) {
                    $nested->where('name', 'like', "%{$search}%")
                        ->orWhere('address_line1', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', fn (Builder $query) => $query->where('is_active', $status === 'active'))
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (PickupLocation $location) => [
                'id' => (int) $location->id,
                'name' => $location->name,
                'method' => $location->method?->name,
                'state' => $location->state?->name,
                'lga' => $location->lga?->name,
                'address_line1' => $location->address_line1,
                'phone' => $location->phone,
                'is_active' => (bool) $location->is_active,
            ]);

        return Inertia::render('Admin/PickupLocations/Index', [
            'filters' => $request->only(['search', 'status']),
            'locations' => $locations,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/PickupLocations/Form', [
            'mode' => 'create',
            'pickupLocation' => null,
            ...$this->formOptions(),
        ]);
    }

    public function store(StorePickupLocationRequest $request): RedirectResponse
    {
        $location = $this->persist(new PickupLocation(), $request->validated());

        return redirect()
            ->route('admin.pickup-locations.edit', $location)
            ->with('success', 'Pickup location created successfully.');
    }

    public function edit(PickupLocation $pickupLocation): Response
    {
        return Inertia::render('Admin/PickupLocations/Form', [
            'mode' => 'edit',
            'pickupLocation' => [
                'id' => (int) $pickupLocation->id,
                'name' => $pickupLocation->name,
                'shipping_method_id' => (int) $pickupLocation->shipping_method_id,
                'state_id' => $pickupLocation->state_id ? (int) $pickupLocation->state_id : null,
                'lga_id' => $pickupLocation->lga_id ? (int) $pickupLocation->lga_id : null,
                'address_line1' => $pickupLocation->address_line1,
                'address_line2' => $pickupLocation->address_line2,
                'phone' => $pickupLocation->phone,
                'email' => $pickupLocation->email,
                'lead_time_hours' => (int) $pickupLocation->lead_time_hours,
                'is_active' => (bool) $pickupLocation->is_active,
            ],
            ...$this->formOptions($pickupLocation->state_id ? (int) $pickupLocation->state_id : null),
        ]);
    }

    public function update(UpdatePickupLocationRequest $request, PickupLocation $pickupLocation): RedirectResponse
    {
        $this->persist($pickupLocation, $request->validated());

        return back()->with('success', 'Pickup location updated successfully.');
    }

    public function toggleStatus(PickupLocation $pickupLocation): RedirectResponse
    {
        $pickupLocation->update(['is_active' => !$pickupLocation->is_active]);
        $this->clearPickupLocationCaches();

        return back()->with('success', 'Pickup location status updated.');
    }

    protected function persist(PickupLocation $location, array $data): PickupLocation
    {
        $stateId = (int) $data['state_id'];

        $location->fill([
            'name' => trim((string) $data['name']),
            'shipping_method_id' => (int) $data['shipping_method_id'],
            'state_id' => $stateId,
            'lga_id' => !empty($data['lga_id']) ? (int) $data['lga_id'] : null,
            // Zone is derived from the state so zone-scoped pickup rates and
            // the checkout's state/zone matching stay consistent.
            'shipping_zone_id' => State::query()->find($stateId)?->shippingZone()->first()?->id,
            'address_line1' => trim((string) $data['address_line1']),
            'address_line2' => isset($data['address_line2']) && trim((string) $data['address_line2']) !== '' ? trim((string) $data['address_line2']) : null,
            'phone' => isset($data['phone']) && trim((string) $data['phone']) !== '' ? trim((string) $data['phone']) : null,
            'email' => isset($data['email']) && trim((string) $data['email']) !== '' ? trim((string) $data['email']) : null,
            'lead_time_hours' => isset($data['lead_time_hours']) && $data['lead_time_hours'] !== '' && $data['lead_time_hours'] !== null ? (int) $data['lead_time_hours'] : 0,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        $location->save();
        $this->clearPickupLocationCaches();

        return $location->refresh();
    }

    protected function formOptions(?int $stateId = null): array
    {
        return [
            'methods' => ShippingMethod::query()
                ->select(['id', 'name', 'method_type', 'is_active'])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->filter(fn (ShippingMethod $method) => $method->isPickup())
                ->map(fn (ShippingMethod $method) => [
                    'id' => (int) $method->id,
                    'name' => $method->name,
                    'is_active' => (bool) $method->is_active,
                ])
                ->values()
                ->all(),
            'states' => State::query()
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get()
                ->map(fn (State $state) => [
                    'id' => (int) $state->id,
                    'name' => $state->name,
                ])
                ->values()
                ->all(),
            'lgas' => $this->shippingManagementService->lgasForState($stateId),
        ];
    }

    protected function clearPickupLocationCaches(): void
    {
        // The checkout caches pickup locations per state (including zone
        // matches), so bust every state's entry.
        State::query()->pluck('id')->each(
            fn ($stateId) => Cache::forget("pickup_locations_state_{$stateId}")
        );
    }
}
