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
    /**
     * Send MFA Code
     */
    public function send(Request $request)
    {
        $user = $request->user();

        if (!$user->mfa_enabled) {
            return response()->json(['message' => 'MFA not enabled for this user'], 400);
        }

        $code = Str::random(6); // Numeric or scanning logic later. For now alphanumeric is fine.
        $code = rand(100000, 999999); // Numeric is better for manual entry

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
        $cachedCode = Cache::get('mfa:' . $user->id);

        if (!$cachedCode || $cachedCode != $request->code) {
            return response()->json(['message' => 'Invalid or expired code'], 401);
        }

        // Clear code
        Cache::forget('mfa:' . $user->id);

        // Here we would normally issue the "REAL" long-lived token if we were doing 
        // a 2-step login flow where the first token is partial.
        // For simplicity in this implementation, we assume the user already has a token 
        // but needs to pass this check to perform sensitive actions OR 
        // we can return a "mfa_verified" boolean or scope.

        return response()->json(['message' => 'MFA verified successfully']);
    }
}
