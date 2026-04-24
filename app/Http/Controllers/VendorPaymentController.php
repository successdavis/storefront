<?php

namespace App\Http\Controllers;

use App\Services\Accounting\AccountingService;
use App\Models\VendorBill;
use Illuminate\Http\Request;

class VendorPaymentController extends Controller
{
    public function __construct(
        protected AccountingService $accountingService,
    ) {}

    public function store(Request $request, $billId)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'method' => 'required|string|max:50',
            'note'   => 'nullable|string|max:255',
        ]);

        $vendorBill = VendorBill::findOrFail($billId);

        // Validate against bill's total amount
        if ($validated['amount'] > $vendorBill->outstandingBalance()) {
            return back()->withErrors([
                'amount' => 'The payment exceeds the outstanding balance for this bill.',
            ]);
        }

        // Use the shortcut alias on the model
        $payment = $vendorBill->addPayment([
            'type'   => 'outflow',
            'method' => $validated['method'],
            'amount' => $validated['amount'],
            'status' => 'paid',
            'note'   => $validated['note'] ?? null,
        ]);

        $this->accountingService->postVendorBillPayment($payment, auth()->id());

        return redirect()->back();
    }
}
