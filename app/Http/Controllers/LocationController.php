<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\State;
use App\Models\Lga;
use Illuminate\Support\Facades\Cache;

class LocationController extends Controller
{
    public function countries()
    {
        $countries = Cache::remember(
            'locations:countries',
            now()->addDay(),
            fn () => Country::select('id','name')
                ->orderBy('name')
                ->get()
        );

        return response()->json($countries);
    }

    public function states($countryId)
    {
        $states = Cache::remember(
            "locations:states:$countryId",
            now()->addDay(),
            fn () => State::where('country_id',$countryId)
                ->select('id','name')
                ->orderBy('name')
                ->get()
        );

        return response()->json($states);
    }

    public function lgas($stateId)
    {
        $lgas = Cache::remember(
            "locations:lgas:$stateId",
            now()->addDay(),
            fn () => Lga::where('state_id',$stateId)
                ->select('id','name')
                ->orderBy('name')
                ->get()
        );

        return response()->json($lgas);
    }
}
