<?php

namespace App\Http\Controllers;

use App\Services\ContactConversationHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactHistoryController extends Controller
{
    public function index(): View
    {
        return view('dashboard.contact-history');
    }

    public function search(Request $request, ContactConversationHistoryService $history): JsonResponse
    {
        $company = $request->user()?->company;
        if (! $company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $phone = $request->query('phone');
        $email = $request->query('email');
        $name = $request->query('name');
        $leadId = $request->query('lead_id');
        $limit = min(200, max(20, (int) $request->query('limit', 100)));

        $data = $history->history(
            (int) $company->id,
            is_string($phone) ? $phone : null,
            is_string($email) ? $email : null,
            $limit,
            is_string($name) ? $name : null,
            is_numeric($leadId) ? (int) $leadId : null
        );

        return response()->json($data);
    }
}
