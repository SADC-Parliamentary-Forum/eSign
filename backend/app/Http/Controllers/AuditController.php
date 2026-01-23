<?php

namespace App\Http\Controllers;

use OwenIt\Auditing\Models\Audit;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    /**
     * List audit logs (Admin only)
     */
    public function index(Request $request)
    {
        $audits = \OwenIt\Auditing\Models\Audit::with('user')
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        $audits->getCollection()->transform(function ($audit) {
            $model = class_basename($audit->auditable_type);
            $event = ucfirst($audit->event);

            // Build a human-readable description
            $description = "$event $model";

            if ($audit->event === 'updated') {
                $count = count($audit->new_values ?? []);
                $description .= " (updated $count fields)";
            } elseif ($audit->event === 'created') {
                $description .= " #{$audit->auditable_id}";
            }

            // Append description to the object so it appears in JSON
            $audit->description = $description;

            return $audit;
        });

        return response()->json($audits);
    }
}
