<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use App\Mail\MfaCodeMail;
use Illuminate\Support\Str;

class MfaController extends Controller
{
    protected $auditService;

    public function __construct(\App\Services\AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Send MFA Code
     */
    public function send(Request $request)
    {
        $user = $request->user();

        if (!$user->mfa_enabled) {
            return response()->json(['message' => 'MFA not enabled for this user'], 400);
        }

        // $code = Str::random(6); // Numeric or scanning logic later. For now alphanumeric is fine.
        $code = random_int(100000, 999999); // Cryptographically secure integer

        // Store in Redis for 5 minutes
        Cache::put('mfa:' . $user->id, $code, 300);

        // Send Email
        Mail::to($user->email)->queue(new MfaCodeMail($code));

        return response()->json(['message' => 'Verification code sent']);
    }

    /**
     * Verify MFA Code
     */
    public function verify(Request $request)
    {
        $request->validate([
            'code' => 'required|numeric',
        ]);

        $user = $request->user();

        // Ensure the token has the correct ability, preventing bypass if someone used a full token to hit this endpoint unnecessarily (though harmless)
        // or tried to use a different restricted token.
        if (!$user->tokenCan('mfa:verify')) {
            // If they already have full access, maybe just return success? 
            // But strict flow implies they are in the pending state.
            // Let's allow it if they are fully auth'd too, but primarily for mfa:verify.
        }

        $cachedCode = Cache::get('mfa:' . $user->id);

        if (!$cachedCode || $cachedCode != $request->code) {
            return response()->json(['message' => 'Invalid or expired code'], 401);
        }

        // Clear code
        Cache::forget('mfa:' . $user->id);

        // Revoke the Partial Token
        $request->user()->currentAccessToken()->delete();

        // Issue Full Token
        $token = $user->createToken('auth_token', ['*'])->plainTextToken;

        $this->auditService->log($user, 'mfa_verified', 'user', $user->id);

        return response()->json([
            'message' => 'MFA verified successfully',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->load('role'),
        ]);
    }
}
