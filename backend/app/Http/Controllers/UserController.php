<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * List all users
     */
    public function index()
    {
        return response()->json(User::with('role')->get());
    }

    /**
     * Create a new user
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'role_id' => 'required|exists:roles,id',
            'mfa_enabled' => 'boolean',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role_id' => $validated['role_id'],
            'mfa_enabled' => $validated['mfa_enabled'] ?? false,
        ]);

        return response()->json($user, 201);
    }

    /**
     * Show user details
     */
    public function show(string $id)
    {
        return response()->json(User::with('role')->findOrFail($id));
    }

    /**
     * Update user (Role, MFA)
     */
    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'string',
            'email' => 'email|unique:users,email,' . $id,
            'role_id' => 'exists:roles,id',
            'mfa_enabled' => 'boolean',
        ]);

        $user->update($validated);

        return response()->json($user);
    }

    /**
     * Delete user
     */
    public function destroy(string $id)
    {
        User::destroy($id);
        return response()->json(['message' => 'User deleted']);
    }
}
