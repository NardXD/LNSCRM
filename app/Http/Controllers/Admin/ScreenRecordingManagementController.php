<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkDeleteScreenRecordingsRequest;
use App\Models\Company;
use App\Models\ScreenRecording;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ScreenRecordingManagementController extends Controller
{
    /**
     * Display the screen recording bulk delete page.
     */
    public function index(): View
    {
        $companies = Company::orderBy('name')->get(['id', 'name']);

        return view('admin.screen-recording-management', compact('companies'));
    }

    /**
     * Get count of recordings in date range (for preview).
     */
    public function previewCount(): \Illuminate\Http\JsonResponse
    {
        $dateFrom = request()->input('date_from');
        $dateTo = request()->input('date_to');
        $companyId = request()->input('company_id');

        if (! $dateFrom || ! $dateTo) {
            return response()->json(['count' => 0]);
        }

        $query = ScreenRecording::whereBetween('date', [$dateFrom, $dateTo]);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return response()->json(['count' => $query->count()]);
    }

    /**
     * Get sync status metrics grouped by company for monitoring.
     */
    public function syncOverview(): \Illuminate\Http\JsonResponse
    {
        $dateFrom = request()->input('date_from');
        $dateTo = request()->input('date_to');

        $query = ScreenRecording::query()
            ->selectRaw('company_id, sync_status, COUNT(*) as total')
            ->groupBy('company_id', 'sync_status');

        if ($dateFrom && $dateTo) {
            $query->whereBetween('date', [$dateFrom, $dateTo]);
        }

        $rows = $query->get();
        $companies = Company::whereIn('id', $rows->pluck('company_id')->unique()->values())
            ->pluck('name', 'id');

        $summary = [];

        foreach ($rows as $row) {
            if (! isset($summary[$row->company_id])) {
                $summary[$row->company_id] = [
                    'company_id' => $row->company_id,
                    'company_name' => $companies[$row->company_id] ?? 'Unknown',
                    'queued' => 0,
                    'uploading' => 0,
                    'uploaded' => 0,
                    'failed' => 0,
                ];
            }

            $status = $row->sync_status ?: 'queued';
            if (array_key_exists($status, $summary[$row->company_id])) {
                $summary[$row->company_id][$status] = (int) $row->total;
            }
        }

        return response()->json([
            'success' => true,
            'summary' => array_values($summary),
        ]);
    }

    /**
     * Bulk delete screen recordings by date range.
     */
    public function bulkDelete(BulkDeleteScreenRecordingsRequest $request): RedirectResponse
    {
        $dateFrom = $request->validated('date_from');
        $dateTo = $request->validated('date_to');
        $companyId = $request->input('company_id');

        $query = ScreenRecording::whereBetween('date', [$dateFrom, $dateTo]);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $recordings = $query->get();
        $deletedCount = 0;
        $fileErrors = [];

        foreach ($recordings as $recording) {
            try {
                if ($recording->screen_recording_path && Storage::disk('private')->exists($recording->screen_recording_path)) {
                    Storage::disk('private')->delete($recording->screen_recording_path);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to delete recording file', [
                    'recording_id' => $recording->id,
                    'path' => $recording->screen_recording_path,
                    'error' => $e->getMessage(),
                ]);
                $fileErrors[] = $recording->id;
            }

            $recording->delete();
            $deletedCount++;
        }

        $message = "{$deletedCount} screen recording(s) deleted successfully.";
        if (count($fileErrors) > 0) {
            $message .= ' Some recording files could not be removed from storage.';
        }

        return redirect()
            ->route('admin.screen-recording-management')
            ->with('success', $message);
    }
}
