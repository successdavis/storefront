<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Reports\WalletBalancePdfExportRequest;
use App\Http\Requests\Admin\Reports\WalletBalancePreviewRequest;
use App\Http\Requests\Admin\Reports\WalletCategoryAccountsExportRequest;
use App\Services\Reports\WalletBalanceReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class WalletBalanceReportController extends Controller
{
    public function __construct(
        protected WalletBalanceReportService $walletBalanceReportService,
    ) {}

    public function index(WalletBalancePreviewRequest $request): Response
    {
        $filters = [
            'branch_id' => $request->validated('branch_id'),
        ];

        return Inertia::render('Admin/Reports/WalletBalances', [
            'branches' => $this->walletBalanceReportService->listBranches(),
            'filters' => $filters,
            'report' => $this->walletBalanceReportService->report($filters),
        ]);
    }

    public function exportPdf(WalletBalancePdfExportRequest $request): HttpResponse
    {
        $filters = [
            'branch_id' => $request->validated('branch_id'),
        ];

        $payload = $this->walletBalanceReportService->pdfPayload($filters, $request->user());
        $branchName = data_get($payload, 'summary.selected_branch.name', 'all-branches');
        $filename = sprintf(
            'wallet-balances-%s-%s.pdf',
            Str::slug($branchName),
            now()->format('YmdHis'),
        );

        $pdf = Pdf::loadView('reports.wallet-balances', $payload)
            ->setPaper('a4', 'portrait');

        return $pdf->stream($filename);
    }

    public function exportCategoryAccounts(WalletCategoryAccountsExportRequest $request): HttpResponse
    {
        $walletTypeKey = $request->validated('wallet_type');
        $filters = [
            'branch_id' => $request->validated('branch_id'),
        ];

        $payload = $this->walletBalanceReportService->categoryExportPayload($walletTypeKey, $filters);
        $walletLabel = data_get($payload, 'wallet_type.label', $walletTypeKey);
        $branchName = data_get($payload, 'branch.name', 'all-branches');
        $filename = sprintf(
            'wallet-accounts-%s-%s-%s.xls',
            Str::slug($walletLabel),
            Str::slug($branchName),
            now()->format('YmdHis'),
        );

        return response()
            ->view('reports.wallet-category-accounts-excel', $payload)
            ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
}
