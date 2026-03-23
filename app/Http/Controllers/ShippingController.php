<?php
namespace App\Http\Controllers;

use App\Exceptions\ShippingRateNotFoundException;
use App\Models\ShippingZone;
use App\Models\PickupLocation;
use App\Models\Shipment;
use App\Models\Pickup;
use App\Services\Shipping\ShippingCostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ShippingController extends Controller
{
    public function __construct(ShippingCostService $shipService)
    {
        $this->shipService = $shipService;
    }

    public function methods()
    {
        $methods = $this->shipService->listActiveMethods()
            ->map(fn ($method) => [
                'id' => (int) $method->id,
                'name' => $method->name,
                'description' => $method->description,
                'method_type' => $method->method_type,
                'sort_order' => (int) $method->sort_order,
            ])
            ->values();

        return response()->json($methods);
    }

    public function zones()
    {
        $zones = ShippingZone::select('id','name')->get();
        return response()->json($zones);
    }

    public function pickupLocations()
    {
        $locations = PickupLocation::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        return response()->json($locations);
    }

    public function pickupLocationsByState($stateId)
    {
        $cacheKey = "pickup_locations_state_{$stateId}";

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($stateId) {
            return $this->shipService
                ->listPickupLocationsForState((int) $stateId)
                ->map(fn (PickupLocation $location) => [
                    'id' => (int) $location->id,
                    'name' => $location->name,
                    'address_line1' => $location->address_line1,
                    'address_line2' => $location->address_line2,
                    'state_id' => $location->state_id ? (int) $location->state_id : null,
                    'lga_id' => $location->lga_id ? (int) $location->lga_id : null,
                    'latitude' => $location->latitude,
                    'longitude' => $location->longitude,
                    'phone' => $location->phone,
                ])
                ->values();
        });
    }

    public function calculate(Request $request)
    {
        $payload = $request->validate([
            'shipping_method_id' => 'required|integer|exists:shipping_methods,id',
            'shipping_zone_id' => 'nullable|integer|exists:shipping_zones,id',
            'state_id' => 'nullable|integer|exists:states,id',
            'lga_id' => 'nullable|integer|exists:lgas,id',
            'pickup_location_id' => 'nullable|integer|exists:pickup_locations,id',
            'weight_kg' => 'nullable|numeric|min:0',
            'subtotal' => 'nullable|numeric|min:0',
            'items' => 'nullable|array',
        ]);

        try {
            $result = $this->shipService->calculate($payload);
            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (ShippingRateNotFoundException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            // log unexpected errors
            \Log::error('Shipping calculation failed: '.$e->getMessage(), ['payload' => $payload]);
            return response()->json(['success' => false, 'message' => 'Unable to calculate shipping cost.'], 500);
        }
    }
    /**
     * Create a shipment record (and pickup record if needed).
     * This is intentionally straightforward: it creates a Shipment and optionally a Pickup entry.
     */
    public function createShipment(Request $request)
    {
        $data = $request->validate([
            'shipping_method_id' => 'required|exists:shipping_methods,id',
            'shipping_zone_id' => 'nullable|exists:shipping_zones,id',
            'pickup_location_id' => 'nullable|exists:pickup_locations,id',
            'weight' => 'nullable|numeric|min:0',
            'subtotal' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'address' => 'nullable|array',
            'address.line1' => 'required_if:type,delivery|string',
            'address.phone' => 'nullable|string',
        ]);

        // calculate cost to store canonical cost at creation (re-use calculate logic)
        $calcReq = new Request([
            'type' => $data['type'],
            'shipping_method_id' => $data['shipping_method_id'],
            'shipping_zone_id' => $data['shipping_zone_id'] ?? null,
            'pickup_location_id' => $data['pickup_location_id'] ?? null,
            'weight' => $data['weight'] ?? 0,
            'subtotal' => $data['subtotal'] ?? 0,
            'currency' => $data['currency'] ?? 'NGN',
        ]);
        $calcResp = $this->calculate($calcReq);
        if ($calcResp->status() !== 200) {
            throw ValidationException::withMessages(['shipping' => 'Unable to calculate shipping for given parameters.']);
        }
        $calcData = $calcResp->getData();

        $shipment = Shipment::create([
            'shipping_method_id' => $data['shipping_method_id'],
            'type' => $data['type'],
            'weight' => $data['weight'] ?? 0,
            'cost' => $calcData->cost ?? 0,
            'currency' => $calcData->currency ?? ($data['currency'] ?? 'NGN'),
            'status' => 'pending',
            'shipping_zone_id' => $data['shipping_zone_id'] ?? null,
        ]);

        if ($data['type'] === 'pickup' && !empty($data['pickup_location_id'])) {
            $pickup = Pickup::create([
                'shipment_id' => $shipment->id,
                'pickup_location_id' => $data['pickup_location_id'],
                'reference' => Str::upper(Str::random(8)),
            ]);
            $shipment->load('pickup');
        }

        // Optionally associate address records to shipment (addresses table exist)
        if (!empty($data['address'])) {
            // create address record and morph to shipment (addressable)
            $addressData = $data['address'];
            $shipment->addresses()->create(array_merge($addressData, ['type' => 'shipping']));
        }

        return response()->json(['success' => true, 'shipment' => $shipment], 201);
    }

    public function zoneByState($stateId)
    {
        $zoneId = $this->shipService->resolveZoneForState((int) $stateId);

        if (!$zoneId) {
            return response()->json(['message' => 'No zone found for this state'], 404);
        }

        return response()->json(['zone_id' => $zoneId]);
    }

}
