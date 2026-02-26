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
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'role_id' => 'nullable|exists:roles,id',
            'mfa_enabled' => 'boolean',
            'status' => 'nullable|in:ACTIVE,INACTIVE,INVITED',
            'department' => 'nullable|string|max:255',
            'job_title' => 'nullable|string|max:255',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role_id' => $validated['role_id'] ?? null,
            'mfa_enabled' => $validated['mfa_enabled'] ?? false,
            'status' => $validated['status'] ?? 'ACTIVE',
            'department' => $validated['department'] ?? null,
            'job_title' => $validated['job_title'] ?? null,
        ]);

        return response()->json($user->load('role'), 201);
    }

    /**
     * Show user details
     */
    public function show(string $id)
    {
        return response()->json(User::with('role')->findOrFail($id));
    }

    /**
     * Update user (Role, MFA).
     * Only admins can reach this (admin middleware). Defense-in-depth: prevent self-role change.
     */
    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);
        $currentUser = $request->user();

        $validated = $request->validate([
            'name' => 'string|max:255',
            'email' => 'email|unique:users,email,' . $id,
            'password' => 'nullable|string|min:8',
            'role_id' => 'nullable|exists:roles,id',
            'mfa_enabled' => 'boolean',
            'status' => 'nullable|in:ACTIVE,INACTIVE,INVITED',
            'department' => 'nullable|string|max:255',
            'job_title' => 'nullable|string|max:255',
        ]);

        // Security: prevent any user (including an admin) from changing their own role (no self-elevation or self-demotion)
        if ($currentUser->id === $user->id && array_key_exists('role_id', $validated) && (string) $validated['role_id'] !== (string) $user->role_id) {
            return response()->json(['message' => 'You cannot change your own role.'], 403);
        }

        // Handle password separately
        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json($user->load('role'));
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
