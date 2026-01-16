<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\JsonResponse;

class DocumentStatsController extends Controller
{
    public function index(): JsonResponse
    {
        $userId = auth()->id();

        $stats = [
            'total' => Document::where('user_id', $userId)->count(),
            'draft' => Document::where('user_id', $userId)->where('status', 'DRAFT')->count(),
            'in_progress' => Document::where('user_id', $userId)->where('status', 'IN_PROGRESS')->count(),
            'completed' => Document::where('user_id', $userId)->where('status', 'COMPLETED')->count(),
            'voided' => Document::where('user_id', $userId)->where('status', 'VOIDED')->count(),
        ];

        return response()->json($stats);
    }
}
