<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function list()
    {
        return response()->json(
            User::query()
                ->role(RoleNames::CUSTOMER)
                ->select('id', 'name')
                ->get()
        );
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
            'address' => 'nullable|string|max:255',
        ]);


        $customer = User::create([
            ...$validated,
            'email' => $validated['email'] ?? sprintf('customer-%s@example.invalid', now()->timestamp.Str::random(4)),
            'password' => bcrypt(Str::random(32)),
        ]);

        $customer->assignRole(RoleNames::CUSTOMER);

        return response()->json($customer);
    }
}
