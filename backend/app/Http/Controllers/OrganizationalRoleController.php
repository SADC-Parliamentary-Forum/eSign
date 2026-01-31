<?php

namespace App\Http\Controllers;

use App\Models\OrganizationalRole;
use Illuminate\Http\Request;

class OrganizationalRoleController extends Controller
{
    /**
     * Security: Require admin for write operations.
     */
    private function requireAdmin(Request $request): void
    {
        if (!$request->user() || !$request->user()->hasPermission('admin')) {
            abort(403, 'Unauthorized. Admin access required.');
        }
    }

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
     * Security: Requires admin role
     */
    public function store(Request $request)
    {
        $this->requireAdmin($request);

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
     * Security: Requires admin role
     */
    public function update(Request $request, $id)
    {
        $this->requireAdmin($request);

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
     * Security: Requires admin role
     */
    public function destroy(Request $request, $id)
    {
        $this->requireAdmin($request);

        $role = OrganizationalRole::findOrFail($id);
        $role->delete();

        return response()->json(['message' => 'Role deleted']);
    }
}
