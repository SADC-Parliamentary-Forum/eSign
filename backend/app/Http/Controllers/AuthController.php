<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    protected $auditService;

    public function __construct(\App\Services\AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Handle login request
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials)) {
            $user = Auth::user();

            // Check if user is active
            if (!$user->isActive()) {
                Auth::logout();
                return response()->json([
                    'message' => 'Your account is suspended or inactive.'
                ], 403);
            }

            // MFA Check
            if ($user->mfa_enabled) {
                // Issue Partial Token checking 'mfa-pending' ability
                $token = $user->createToken('mfa_partial_token', ['mfa:verify'])->plainTextToken;

                // Trigger sending code
                // Ideally call MfaController::send logic here or let frontend trigger it.
                // For better UX, let's trigger it here or assume frontend calls /api/mfa/send immediately.
                // Let's assume frontend will call /send using this token.

                return response()->json([
                    'status' => 'mfa_required',
                    'message' => 'MFA verification required',
                    'access_token' => $token, // Restricted token
                    'token_type' => 'Bearer',
                ], 200);
            }

            $token = $user->createToken('auth_token', ['*'])->plainTextToken;

            $this->auditService->log($user, 'login', 'user', $user->id);

            return response()->json([
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => $user->load('role'),
            ]);
        }

        return response()->json([
            'message' => 'Invalid credentials',
        ], 401);
    }

    /**
     * Handle user registration
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone_number' => ['nullable', 'string', 'max:20'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'phone_number' => $validated['phone_number'] ?? null,
        ]);

        \App\Jobs\SendEmailVerificationNotification::dispatch($user);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->load('role'),
        ], 201);
    }

    /**
     * Get authenticated user
     */
    public function me(Request $request)
    {
        $userId = $request->user()->id;
        $user = \Illuminate\Support\Facades\Cache::remember("user.{$userId}", 3600, function () use ($userId) {
            return User::with('role')->find($userId);
        });
        return response()->json($user);
    }

    /**
     * Handle logout
     */
    public function logout(Request $request)
    {
        $currentToken = $request->user()->currentAccessToken();
        if ($currentToken instanceof PersonalAccessToken) {
            $currentToken->delete();
        }

        // Logout from session guard if applicable
        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['message' => 'Logged out']);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'department' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $user->update($validated);

        // Invalidate cache
        \Illuminate\Support\Facades\Cache::forget("user.{$user->id}");

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->fresh()->load('role'),
        ]);
    }

    /**
     * Update user password
     */
    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (!Auth::attempt(['email' => $user->email, 'password' => $validated['current_password']])) {
            return response()->json(['message' => 'Current password is incorrect'], 422);
        }

        $user->update([
            'password' => bcrypt($validated['password']),
        ]);

        return response()->json(['message' => 'Password updated successfully']);
    }
    /**
     * Resend verification email
     */
    public function resendVerification(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification link sent.']);
    }
}
