<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function list()
    {
        return response()->json(User::doesntHave('roles')->select('id', 'name')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'nullable|email|unique:users,email',
            'phone' => 'required|string|max:20',
            'country_id' => 'required|exists:countries,id',
            'state_id' => 'required|exists:states,id',
            'lga_id' => 'required|exists:lgas,id',
            'city_id' => 'nullable|exists:cities,id',
            'address' => 'nullable|string|max:255',
        ]);


        $customer = User::create([
            ...$validated,
            'role' => 'customer',
            'password' => bcrypt('password'), // or random default
        ]);

        return response()->json($customer);
    }
}

