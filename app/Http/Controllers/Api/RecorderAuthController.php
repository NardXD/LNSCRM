<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecorderLoginRequest;
use App\Models\Company;
use App\Models\RecorderApiToken;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class RecorderAuthController extends Controller
{
    public function login(RecorderLoginRequest $request)
    {
        $subdomain = $request->input('company_subdomain');
        $company = is_string($subdomain) && $subdomain !== ''
            ? Company::where('subdomain', $subdomain)->first()
            : null;
        $company ??= Company::current();

        if (! $company) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found.',
            ], 404);
        }

        $user = User::where('email', $request->string('email')->toString())
            ->where('company_id', $company->id)
            ->first();

        if (! $user || ! Hash::check($request->string('password')->toString(), $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials.',
            ], 401);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is not active.',
            ], 403);
        }

        $plainToken = RecorderApiToken::generateToken();

        $token = RecorderApiToken::create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'name' => $request->input('token_name', 'Recorder Device'),
            'token_hash' => RecorderApiToken::hashToken($plainToken),
            'token_prefix' => RecorderApiToken::tokenPrefix($plainToken),
            'device_id' => $request->string('device_id')->toString(),
            'platform' => $request->string('platform')->toString(),
            'expires_at' => now()->addDays(30),
            'last_used_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'token' => $plainToken,
            'expires_at' => $token->expires_at,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'company' => [
                'id' => $company->id,
                'name' => $company->name,
                'subdomain' => $company->subdomain,
                'timezone' => $company->timezone ?: (string) config('app.timezone', 'UTC'),
            ],
        ]);
    }
}
