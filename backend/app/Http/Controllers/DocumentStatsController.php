<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\JsonResponse;

class DocumentStatsController extends Controller
{
    public function index(): JsonResponse
    {
        $userId = auth()->id();

        $drafts = Document::where('user_id', $userId)->where('status', 'DRAFT')->count();
        $inProgress = Document::where('user_id', $userId)->where('status', 'IN_PROGRESS')->count();
        $completed = Document::where('user_id', $userId)->where('status', 'COMPLETED')->count();
        $declined = Document::where('user_id', $userId)->where('status', 'DECLINED')->count();
        $voided = Document::where('user_id', $userId)->where('status', 'VOIDED')->count();
        $total = $drafts + $inProgress + $completed + $declined + $voided;

        $completionRate = $total > 0 ? round(($completed / $total) * 100) : 0;

        // Calculate average signing time for completed documents
        $avgSigningTime = '0h';
        $completedDocs = Document::where('user_id', $userId)
            ->where('status', 'COMPLETED')
            ->whereNotNull('completed_at')
            ->get();

        if ($completedDocs->count() > 0) {
            $totalHours = 0;
            foreach ($completedDocs as $doc) {
                $created = new \DateTime($doc->created_at);
                $completedAt = new \DateTime($doc->completed_at);
                $diff = $created->diff($completedAt);
                $totalHours += ($diff->days * 24) + $diff->h;
            }
            $avgHours = $totalHours / $completedDocs->count();
            if ($avgHours < 24) {
                $avgSigningTime = round($avgHours, 1) . 'h';
            } else {
                $avgSigningTime = round($avgHours / 24, 1) . 'd';
            }
        }

        $stats = [
            'total' => $total,
            'drafts' => $drafts,
            'awaitingSignatures' => $inProgress,
            'completed' => $completed,
            'declined' => $declined,
            'voided' => $voided,
            'completionRate' => $completionRate,
            'avgSigningTime' => $avgSigningTime,
        ];

        return response()->json($stats);
    }
}
