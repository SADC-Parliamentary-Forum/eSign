<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Str;
use App\Models\User;

class AuthController extends Controller
{
    /**
     * Maximum failed login attempts before lockout.
     */
    private const MAX_LOGIN_ATTEMPTS = 5;

    /**
     * Lockout duration in minutes.
     */
    private const LOCKOUT_DURATION = 30;

    protected $auditService;

    public function __construct(\App\Services\AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Handle login request
     * Security: Implements account lockout after failed attempts
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // Security: Check if account is locked before attempting authentication
        $user = User::where('email', $credentials['email'])->first();

        if ($user && $user->locked_until && $user->locked_until > now()) {
            $minutesRemaining = now()->diffInMinutes($user->locked_until);
            return response()->json([
                'message' => "Account temporarily locked due to too many failed login attempts. Try again in {$minutesRemaining} minutes.",
            ], 429);
        }

        if (Auth::attempt($credentials)) {
            $user = Auth::user();

            // Security: Reset failed login attempts on successful login
            if ($user->failed_login_attempts > 0) {
                $user->update([
                    'failed_login_attempts' => 0,
                    'locked_until' => null,
                ]);
            }

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

        // Security: Track failed login attempts and lock account if threshold exceeded
        if ($user) {
            $user->increment('failed_login_attempts');

            if ($user->failed_login_attempts >= self::MAX_LOGIN_ATTEMPTS) {
                $user->update(['locked_until' => now()->addMinutes(self::LOCKOUT_DURATION)]);

                $this->auditService->log($user, 'account_locked', 'user', $user->id, [
                    'reason' => 'Too many failed login attempts',
                    'failed_attempts' => $user->failed_login_attempts,
                ]);

                return response()->json([
                    'message' => 'Account locked due to too many failed login attempts. Try again in ' . self::LOCKOUT_DURATION . ' minutes.',
                ], 429);
            }
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
            // Security: Strong password policy with complexity requirements
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(12)
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],
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

        $user = User::with('role')->find($userId);
        return response()->json($user);
    }

    /**
     * Handle logout
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        // Audit: Log logout event
        $this->auditService->log($user, 'logout', 'user', $user->id);

        $user->currentAccessToken()->delete();
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
     * Update user avatar
     */
    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:2048'], // Max 2MB
        ]);

        $user = $request->user();

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');

            // Delete old avatar if exists and is not a default/external one
            if ($user->avatar_url && Str::contains($user->avatar_url, 'avatars/')) {
                $oldPath = str_replace('/storage/', '', parse_url($user->avatar_url, PHP_URL_PATH));
                Storage::disk('public')->delete($oldPath);
            }

            $path = $file->store('avatars', 'public');
            $url = Storage::url($path);

            $user->update(['avatar_url' => $url]);

            // Invalidate cache
            \Illuminate\Support\Facades\Cache::forget("user.{$user->id}");

            return response()->json([
                'message' => 'Avatar updated successfully',
                'avatar_url' => $url,
            ]);
        }

        return response()->json(['message' => 'No file uploaded'], 400);
    }

    /**
     * Update user password
     */
    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            // Security: Strong password policy with complexity requirements
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(12)
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],
        ]);

        $user = $request->user();

        if (!Auth::attempt(['email' => $user->email, 'password' => $validated['current_password']])) {
            return response()->json(['message' => 'Current password is incorrect'], 422);
        }

        $user->update([
            'password' => bcrypt($validated['password']),
            'must_change_password' => false,
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
