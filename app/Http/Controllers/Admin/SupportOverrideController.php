<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;

class SupportOverrideController extends Controller
{
    /**
     * Display the support override page.
     */
    public function index()
    {
        $companies = Company::with('activeSubscription.plan')->get();

        return view('admin.support-override', compact('companies'));
    }
}
