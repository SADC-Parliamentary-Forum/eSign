<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * List all roles
     */
    public function index()
    {
        // Return simple list for dropdowns
        return response()->json(Role::all(['id', 'name', 'display_name']));
    }
}
