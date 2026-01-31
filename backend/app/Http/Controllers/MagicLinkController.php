<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use App\Models\User;
use App\Models\MagicLinkUse;
use Illuminate\Support\Facades\Auth;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MagicLinkController extends Controller
{
    protected $auditService;

    public function __construct(\App\Services\AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Generate a magic link for an external email
     * (Admin/Finance Officer only)
     */
    public function generate(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        $email = $request->email;

        // Find or create external user
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'External Signer',
                'password' => Hash::make(Str::random(32)),
                'role_id' => Role::where('name', 'external_signer')->first()?->id,
                'mfa_enabled' => false,
            ]
        );

        // Generate signed URL valid for 60 minutes
        $url = URL::temporarySignedRoute(
            'login.magic',
            now()->addMinutes(60),
            ['id' => $user->id]
        );

        // Audit: Log magic link generation
        $this->auditService->log($request->user(), 'magic_link_generated', 'user', $user->id, [
            'target_email' => $email,
            'expires_in' => '60 minutes',
        ]);

        return response()->json(['url' => $url]);
    }

    /**
     * Handle the magic link click
     * Security: Single-use enforcement - each link can only be used once
     */
    public function login(Request $request, $id)
    {
        // Validate signature
        if (!$request->hasValidSignature()) {
            return response()->json(['message' => 'Invalid or expired link'], 401);
        }

        // Security: Generate hash of the full signed URL to track usage
        $signatureHash = hash('sha256', $request->fullUrl());

        // Security: Check if this exact link has already been used
        if (MagicLinkUse::hasBeenUsed($signatureHash)) {
            \Log::warning('Attempted reuse of magic link', [
                'user_id' => $id,
                'ip_address' => $request->ip(),
            ]);
            return response()->json([
                'message' => 'This link has already been used. Please request a new link.'
            ], 401);
        }

        $user = User::findOrFail($id);

        // Security: Mark link as used BEFORE issuing token (prevent race conditions)
        MagicLinkUse::markAsUsed(
            $signatureHash,
            'auth',
            $user->id,
            $request->ip()
        );

        // Log the user in
        $token = $user->createToken('magic_link_token')->plainTextToken;

        // Audit: Log successful magic link login
        $this->auditService->log($user, 'magic_link_login', 'user', $user->id, [
            'ip_address' => $request->ip(),
        ]);

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->load('role'),
        ]);
    }
}
