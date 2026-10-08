<?php

namespace App\Http\Controllers\Twilio;

use App\Http\Controllers\Controller;
use App\Models\PhoneCallLog;
use App\Services\PhoneCallLogService;
use App\Services\PhoneReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PhoneReportController extends Controller
{
    public function __construct(
        protected PhoneReportService $reports,
        protected PhoneCallLogService $callLogs
    ) {}

    public function index(): View
    {
        $user = Auth::user();
        [$dateFrom, $dateTo] = $this->reports->defaultDateRange();
        $canViewAllUsers = $this->reports->canViewAllUsers($user);

        return view('dashboard.phone-reports', [
            'reportDefaultDateFrom' => $dateFrom,
            'reportDefaultDateTo' => $dateTo,
            'canViewAllUsers' => $canViewAllUsers,
            'reportUsers' => $canViewAllUsers ? $this->reports->userOptions((int) $user->company_id) : collect(),
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $filters = $request->validate($this->filterRules());

        return response()->json([
            'success' => true,
            'data' => $this->reports->summary(Auth::user(), $filters),
        ]);
    }

    public function calls(Request $request): JsonResponse
    {
        $filters = $request->validate($this->filterRules() + $this->listRules() + [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->reports->calls(Auth::user(), $filters),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $request->validate($this->filterRules() + $this->listRules());
        $export = $this->reports->exportRows(Auth::user(), $filters);
        $headers = $this->reports->exportHeaders();
        $filename = 'phone-report-'.$export['date_from'].'-to-'.$export['date_to'].'.csv';

        return response()->streamDownload(function () use ($export, $headers) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            foreach ($export['rows'] as $row) {
                fputcsv($out, array_map(fn ($value) => $this->csvSafe($value), $row));
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function recording(Request $request, PhoneCallLog $phoneCallLog): Response
    {
        $user = Auth::user();

        if ((int) $phoneCallLog->company_id !== (int) $user->company_id) {
            abort(404);
        }

        if (! $this->reports->canViewAllUsers($user) && (int) $phoneCallLog->user_id !== (int) $user->id) {
            abort(403, 'You do not have access to this recording.');
        }

        return $this->callLogs->recordingResponse($phoneCallLog, $user->company, $request->boolean('download'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function filterRules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'user_id' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function listRules(): array
    {
        return [
            'direction' => ['nullable', Rule::in(['all', 'inbound', 'outbound'])],
            'outcome' => ['nullable', Rule::in(['all', 'completed', 'missed'])],
            'recordings_only' => ['nullable', Rule::in(['0', '1', 'true', 'false'])],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'longest'])],
        ];
    }

    /**
     * Neutralize spreadsheet formulas in exported text (e.g. agent or lead names starting with "=").
     * Plain phone numbers like "+15551234567" are left untouched.
     */
    protected function csvSafe(mixed $value): mixed
    {
        if (! is_string($value) || $value === '' || preg_match('/^\+?\d+$/', $value)) {
            return $value;
        }

        return in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
