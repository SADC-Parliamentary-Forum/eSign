<?php

namespace App\Http\Controllers;

use App\Models\OrganizationalRole;
use Illuminate\Http\Request;

class OrganizationalRoleController extends Controller
{
    /**
     * List all organizational roles.
     */
    public function index()
    {
        $roles = OrganizationalRole::where('is_active', true)
            ->orderBy('level')
            ->orderBy('name')
            ->get();

        return response()->json($roles);
    }

    /**
     * Create a new organizational role.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:organizational_roles,code',
            'level' => 'required|integer|min:1',
            'description' => 'nullable|string',
        ]);

        $role = OrganizationalRole::create($validated);

        return response()->json($role, 201);
    }

    /**
     * Get a single organizational role.
     */
    public function show($id)
    {
        $role = OrganizationalRole::findOrFail($id);
        return response()->json($role);
    }

    /**
     * Update an organizational role.
     */
    public function update(Request $request, $id)
    {
        $role = OrganizationalRole::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:50|unique:organizational_roles,code,' . $id,
            'level' => 'sometimes|integer|min:1',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $role->update($validated);

        return response()->json($role);
    }

    /**
     * Delete an organizational role.
     */
    public function destroy($id)
    {
        $role = OrganizationalRole::findOrFail($id);
        $role->delete();

        return response()->json(['message' => 'Role deleted']);
    }
}
