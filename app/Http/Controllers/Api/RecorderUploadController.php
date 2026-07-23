<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecorderFinalizeUploadRequest;
use App\Http\Requests\RecorderStartUploadRequest;
use App\Http\Requests\RecorderUpdateStatusRequest;
use App\Http\Requests\RecorderUploadChunkRequest;
use App\Models\Company;
use App\Models\ScreenRecording;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RecorderUploadController extends Controller
{
    public function todaysRecordings(Request $request)
    {
        $user = $request->attributes->get('recorder_user');
        $companyId = $request->attributes->get('recorder_company_id');
        $company = Company::query()->find($companyId);
        $tz = $company?->timezone ?: (string) config('app.timezone', 'UTC');
        $today = now($tz)->toDateString();

        $recordings = ScreenRecording::query()
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->whereDate('date', $today)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get([
                'id',
                'upload_id',
                'date',
                'screen_recording_duration',
                'status',
                'sync_status',
                'queued_at',
                'uploaded_at',
                'created_at',
            ]);

        return response()->json([
            'success' => true,
            'recordings' => $recordings,
        ]);
    }

    public function pending(Request $request)
    {
        $user = $request->attributes->get('recorder_user');
        $companyId = $request->attributes->get('recorder_company_id');

        $uploads = ScreenRecording::where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->whereIn('sync_status', ['queued', 'uploading', 'failed'])
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get([
                'id',
                'upload_id',
                'sync_status',
                'retry_count',
                'last_error',
                'queued_at',
                'last_retry_at',
            ]);

        return response()->json([
            'success' => true,
            'uploads' => $uploads,
        ]);
    }

    public function start(RecorderStartUploadRequest $request)
    {
        $user = $request->attributes->get('recorder_user');
        $companyId = $request->attributes->get('recorder_company_id');
        $token = $request->attributes->get('recorder_token');

        $recording = ScreenRecording::updateOrCreate(
            [
                'user_id' => $user->id,
                'company_id' => $companyId,
                'upload_id' => $request->string('upload_id')->toString(),
            ],
            [
                'date' => $request->date('date')->format('Y-m-d'),
                'status' => 'recording',
                'sync_status' => 'queued',
                'device_id' => $token->device_id,
                'device_platform' => $token->platform,
                'screen_recording_duration' => $request->integer('duration', 0),
                'upload_checksum' => $request->input('upload_checksum'),
                'file_size' => $request->input('file_size'),
                'queued_at' => now(),
                'metadata' => array_merge($request->input('metadata', []), [
                    'client_platform' => $token->platform,
                    'client_device_id' => $token->device_id,
                    'queued_via' => 'recorder_api',
                ]),
            ]
        );

        return response()->json([
            'success' => true,
            'recording_id' => $recording->id,
            'upload_id' => $recording->upload_id,
        ]);
    }

    public function upload(RecorderUploadChunkRequest $request)
    {
        $user = $request->attributes->get('recorder_user');
        $companyId = $request->attributes->get('recorder_company_id');
        $uploadId = $request->string('upload_id')->toString();

        $recording = ScreenRecording::where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->where('upload_id', $uploadId)
            ->first();

        if (! $recording) {
            return response()->json([
                'success' => false,
                'message' => 'Upload session not found.',
            ], 404);
        }

        $path = $request->file('recording')->store("recordings/{$companyId}/{$user->id}", 'private');

        $recording->update([
            'screen_recording_path' => $path,
            'file_size' => $request->file('recording')->getSize(),
            'sync_status' => 'uploading',
            'last_error' => null,
        ]);

        return response()->json([
            'success' => true,
            'path' => $path,
        ]);
    }

    public function finalize(RecorderFinalizeUploadRequest $request)
    {
        $user = $request->attributes->get('recorder_user');
        $companyId = $request->attributes->get('recorder_company_id');
        $uploadId = $request->string('upload_id')->toString();

        $recording = ScreenRecording::where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->where('upload_id', $uploadId)
            ->first();

        if (! $recording || ! $recording->screen_recording_path) {
            return response()->json([
                'success' => false,
                'message' => 'Recording file not uploaded.',
            ], 404);
        }

        if (! Storage::disk('private')->exists($recording->screen_recording_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Stored recording file was not found.',
            ], 404);
        }

        $recording->update([
            'screen_recording_duration' => $request->integer('duration', $recording->screen_recording_duration ?? 0),
            'upload_checksum' => $request->input('upload_checksum', $recording->upload_checksum),
            'file_size' => $request->input('file_size', $recording->file_size),
            'sync_status' => 'uploaded',
            'status' => 'completed',
            'uploaded_at' => now(),
            'last_error' => null,
        ]);

        return response()->json([
            'success' => true,
            'recording_id' => $recording->id,
            'message' => 'Recording finalized.',
        ]);
    }

    public function updateStatus(RecorderUpdateStatusRequest $request)
    {
        $user = $request->attributes->get('recorder_user');
        $companyId = $request->attributes->get('recorder_company_id');
        $uploadId = $request->string('upload_id')->toString();

        $recording = ScreenRecording::where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->where('upload_id', $uploadId)
            ->first();

        if (! $recording) {
            return response()->json([
                'success' => false,
                'message' => 'Upload session not found.',
            ], 404);
        }

        $recording->update([
            'sync_status' => $request->string('sync_status')->toString(),
            'retry_count' => $request->integer('retry_count', $recording->retry_count),
            'last_retry_at' => now(),
            'last_error' => $request->input('last_error'),
        ]);

        if ($recording->sync_status === 'failed') {
            $recording->update(['status' => 'failed']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Sync status updated.',
        ]);
    }
}
