<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\Dashboard\RecentTransactionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TransactionController extends Controller
{
    public function index(Request $request, RecentTransactionService $service)
    {
        return Inertia::render('Transactions/Index', [
            'transactions' => $service->getForExplorer(request()->all()),
            'filters' => $request->all(),
        ]);
    }
}
