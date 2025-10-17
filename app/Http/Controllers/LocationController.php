<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\State;
use App\Models\Lga;
use App\Models\City;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * Return all countries.
     */
    public function countries()
    {
        $countries = Country::select('id', 'name')->orderBy('name')->get();
        return response()->json($countries);
    }

    /**
     * Return states by country ID.
     */
    public function states($countryId)
    {
        $states = State::where('country_id', $countryId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json($states);
    }

    /**
     * Return LGAs by state ID.
     */
    public function lgas($stateId)
    {
        $lgas = Lga::where('state_id', $stateId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json($lgas);
    }

    /**
     * Return cities by LGA ID.
     */
    public function cities($lgaId)
    {
        $cities = City::where('lga_id', $lgaId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return response()->json($cities);
    }
}
