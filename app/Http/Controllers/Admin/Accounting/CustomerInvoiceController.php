<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Models\CustomerInvoice;
use App\Services\CustomerInvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerInvoiceController extends Controller
{
    public function __construct(
        protected CustomerInvoiceService $customerInvoiceService,
    ) {}

    public function storePayment(Request $request, CustomerInvoice $customerInvoice): RedirectResponse
    {
        $validated = $request->validate([
            'payment_lines' => ['required', 'array', 'min:1'],
            'payment_lines.*.method' => ['required', 'in:cash,card,transfer,wallet,paypal,stripe,cheque'],
            'payment_lines.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payment_lines.*.transaction_reference' => ['nullable', 'string', 'max:255'],
        ]);

        $this->customerInvoiceService->recordRepayment(
            $customerInvoice,
            $validated['payment_lines'],
            $request->user()->id,
        );

        return back()->with('success', 'Receivable repayment recorded successfully.');
    }
}
