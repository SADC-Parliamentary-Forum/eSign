<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    /**
     * List audit logs (Admin only)
     */
    public function index(Request $request)
    {
        // Simple pagination for now
        return response()->json(AuditLog::with('user')->orderBy('created_at', 'desc')->paginate(50));
    }
}
