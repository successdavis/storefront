<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\State;
use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Sales/Customers/Index', [
            'customers' => User::query()
                ->role(RoleNames::CUSTOMER)
                ->latest()
                ->paginate(15)
                ->through(fn (User $user) => [
                    'id' => (int) $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'address' => $user->address,
                    'created_at' => optional($user->created_at)?->toIso8601String(),
                ]),
            'countries' => Country::query()->select('id', 'name')->orderBy('name')->get(),
            'states' => State::query()->select('id', 'name', 'country_id')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'lga_id' => ['nullable', 'integer', 'exists:lgas,id'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $customer = User::query()->create([
            ...$validated,
            'email' => $validated['email'] ?? sprintf('customer-%s@example.invalid', now()->timestamp.Str::random(4)),
            'password' => bcrypt(Str::random(32)),
        ]);

        $customer->assignRole(RoleNames::CUSTOMER);

        return back()->with('success', 'Customer created successfully.');
    }
}
