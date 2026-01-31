<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
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
     * List all departments.
     */
    public function index()
    {
        $departments = Department::where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json($departments);
    }

    /**
     * Create a new department.
     * Security: Requires admin role
     */
    public function store(Request $request)
    {
        $this->requireAdmin($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:departments,code',
            'description' => 'nullable|string',
        ]);

        $department = Department::create($validated);

        return response()->json($department, 201);
    }

    /**
     * Get a single department.
     */
    public function show($id)
    {
        $department = Department::findOrFail($id);
        return response()->json($department);
    }

    /**
     * Update a department.
     * Security: Requires admin role
     */
    public function update(Request $request, $id)
    {
        $this->requireAdmin($request);

        $department = Department::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:50|unique:departments,code,' . $id,
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $department->update($validated);

        return response()->json($department);
    }

    /**
     * Delete a department.
     * Security: Requires admin role
     */
    public function destroy(Request $request, $id)
    {
        $this->requireAdmin($request);

        $department = Department::findOrFail($id);
        $department->delete();

        return response()->json(['message' => 'Department deleted']);
    }
}
