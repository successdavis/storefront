<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use App\Models\CustomerSavedItem;
use App\Models\Order;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();

        return Inertia::render('Account/Dashboard', [
            'stats' => [
                'orders' => Order::query()->where('user_id', $user->id)->count(),
                'wishlist' => CustomerSavedItem::query()
                    ->where('user_id', $user->id)
                    ->where('list_type', CustomerSavedItem::TYPE_WISHLIST)
                    ->count(),
                'saved_for_later' => CustomerSavedItem::query()
                    ->where('user_id', $user->id)
                    ->where('list_type', CustomerSavedItem::TYPE_SAVED_FOR_LATER)
                    ->count(),
                'addresses' => CustomerAddress::query()->where('user_id', $user->id)->count(),
            ],
            'recentOrders' => Order::query()
                ->where('user_id', $user->id)
                ->latest()
                ->limit(5)
                ->get(['id', 'order_number', 'status', 'total_amount', 'currency', 'created_at'])
                ->map(fn (Order $order) => [
                    'id' => (int) $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'total_amount' => (float) $order->total_amount,
                    'currency' => $order->currency,
                    'created_at' => optional($order->created_at)?->toIso8601String(),
                ])
                ->values(),
        ]);
    }
}
