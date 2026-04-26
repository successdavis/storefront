<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function list(Request $request)
    {
        $search = trim((string) $request->string('q'));
        $limit = max(1, min((int) $request->integer('limit', 10), 25));

        $customers = User::query()
            ->role(RoleNames::CUSTOMER)
            ->where(function ($query) {
                $query
                    ->whereNull('email')
                    ->orWhereRaw('LOWER(email) <> ?', ['walkincustomer@example.com']);
            })
            ->select('id', 'name', 'email', 'phone', 'created_at')
            ->withSum([
                'customerInvoices as outstanding_receivable' => fn ($query) => $query->where('outstanding_balance', '>', 0),
            ], 'outstanding_balance')
            ->withCount([
                'customerInvoices as overdue_invoice_count' => fn ($query) => $query
                    ->where('outstanding_balance', '>', 0)
                    ->whereDate('due_date', '<', now()->toDateString()),
            ])
            ->withMax('sales as latest_sale_at', 'created_at')
            ->withMax('orders as latest_order_at', 'created_at')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($innerQuery) use ($search) {
                    $innerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");

                    if (ctype_digit($search)) {
                        $innerQuery->orWhereKey((int) $search);
                    }
                });
            })
            ->orderByRaw("
                GREATEST(
                    COALESCE(latest_sale_at, '1970-01-01 00:00:00'),
                    COALESCE(latest_order_at, '1970-01-01 00:00:00'),
                    COALESCE(created_at, '1970-01-01 00:00:00')
                ) DESC
            ")
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (User $customer) => [
                'id' => (int) $customer->id,
                'name' => $customer->name,
                'email' => $customer->hasRealEmail() ? $customer->email : null,
                'phone' => $customer->phone,
                'outstanding_receivable' => round((float) ($customer->outstanding_receivable ?? 0), 2),
                'overdue_invoice_count' => (int) ($customer->overdue_invoice_count ?? 0),
            ])
            ->values();

        return response()->json($customers);
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
