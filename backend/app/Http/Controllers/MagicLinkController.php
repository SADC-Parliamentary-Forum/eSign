<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class MagicLinkController extends Controller
{
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
                'role_id' => Role::where('name', 'external_signer')->first()->id,
                'mfa_enabled' => false,
            ]
        );

        // Generate signed URL valid for 7 days
        $url = URL::temporarySignedRoute(
            'login.magic',
            now()->addDays(7),
            ['id' => $user->id]
        );

        return response()->json(['url' => $url]);
    }

    /**
     * Handle the magic link click
     */
    public function login(Request $request, $id)
    {
        if (!$request->hasValidSignature()) {
            return response()->json(['message' => 'Invalid or expired link'], 401);
        }

        $user = User::findOrFail($id);

        // Log the user in
        $token = $user->createToken('magic_link_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->load('role'),
        ]);
    }
}
