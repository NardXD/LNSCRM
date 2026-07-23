<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ChangePasswordController extends Controller
{
    /**
     * Display the change password form.
     */
    public function index(): View
    {
        return view('dashboard.change-password');
    }

    /**
     * Update the user's password.
     */
    public function update(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ], [
            'current_password.required' => 'Please enter your current password.',
            'password.required' => 'Please enter a new password.',
            'password.confirmed' => 'The new password confirmation does not match.',
            'password.min' => 'The new password must be at least 8 characters.',
        ]);

        /** @var User $user */
        $user = Auth::user();

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'The current password is incorrect.',
            ], 422);
        }

        // Check if new password is different from current
        if (Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'The new password must be different from your current password.',
            ], 422);
        }

        // Update password
        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully!',
        ]);
    }
}
