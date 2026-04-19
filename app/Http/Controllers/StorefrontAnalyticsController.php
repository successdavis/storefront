<?php

namespace App\Http\Controllers;

use App\Services\Analytics\StorefrontAnalyticsTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorefrontAnalyticsController extends Controller
{
    public function __construct(
        protected StorefrontAnalyticsTracker $tracker,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'events' => ['required', 'array', 'min:1', 'max:20'],
            'events.*.visitor_key' => ['required', 'string', 'min:16', 'max:64'],
            'events.*.page_path' => ['required', 'string', 'max:255'],
            'events.*.page_title' => ['nullable', 'string', 'max:255'],
            'events.*.component' => ['required', 'string', 'max:160'],
            'events.*.occurred_at' => ['nullable', 'date'],
            'events.*.referrer' => ['nullable', 'string', 'max:255'],
            'events.*.location' => ['nullable', 'array'],
            'events.*.location.country_code' => ['nullable', 'string', 'max:8'],
            'events.*.location.country_name' => ['nullable', 'string', 'max:120'],
            'events.*.location.state_name' => ['nullable', 'string', 'max:120'],
            'events.*.location.region_name' => ['nullable', 'string', 'max:120'],
        ]);

        $tracked = $this->tracker->trackBatch($request, $validated['events']);

        return response()->json([
            'tracked' => $tracked,
        ]);
    }
}
