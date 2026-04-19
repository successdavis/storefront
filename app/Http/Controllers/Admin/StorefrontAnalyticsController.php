<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Analytics\StorefrontAnalyticsReportService;
use App\Services\Analytics\StorefrontAnalyticsSettings;
use App\Support\PermissionNames;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontAnalyticsController extends Controller
{
    public function __construct(
        protected StorefrontAnalyticsReportService $reportService,
        protected StorefrontAnalyticsSettings $settings,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $this->validatedFilters($request);

        return Inertia::render('Admin/Analytics/Index', [
            'report' => $this->reportService->dashboard($filters),
            'permissions' => [
                'can_manage' => $request->user()->can(PermissionNames::MANAGE_ADMIN_ANALYTICS),
            ],
        ]);
    }

    public function settings(Request $request): Response
    {
        return Inertia::render('Admin/Analytics/Settings', [
            'settings' => $this->settings->all(),
            'permissions' => [
                'can_manage' => $request->user()->can(PermissionNames::MANAGE_ADMIN_ANALYTICS),
            ],
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'capture_referrers' => ['required', 'boolean'],
            'track_authenticated_pages' => ['required', 'boolean'],
            'raw_retention_days' => ['required', 'integer', 'min:30', 'max:365'],
            'aggregation_refresh_window_days' => ['required', 'integer', 'min:1', 'max:90'],
        ]);

        $this->settings->update($validated);

        return back()->with('success', 'Analytics settings updated.');
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->validatedFilters($request);
        $validated = $request->validate([
            'type' => ['required', 'in:summary,trend,pages,countries,regions,devices,referrers'],
        ]);

        $export = $this->reportService->export((string) $validated['type'], $filters);

        return response()->streamDownload(function () use ($export) {
            $handle = fopen('php://output', 'wb');

            fputcsv($handle, $export['columns']);

            foreach ($export['rows'] as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $export['filename'], [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedFilters(Request $request): array
    {
        return $request->validate([
            'range' => ['nullable', 'in:today,7_days,30_days,90_days,custom'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'trend' => ['nullable', 'in:daily,weekly,monthly'],
        ]);
    }
}
