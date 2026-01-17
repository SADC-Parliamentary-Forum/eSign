<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DocumentActivityController extends Controller
{
    public function index(Request $request)
    {
        $userId = auth()->id();
        $limit = $request->input('limit', 10);

        try {
            $documents = Document::where('user_id', $userId)
                ->orderBy('updated_at', 'desc')
                ->take($limit)
                ->get();

            $activity = $documents->map(function ($doc) {
                // Ensure dates are Carbon instances
                $created = $doc->created_at instanceof Carbon ? $doc->created_at : Carbon::parse($doc->created_at);
                $updated = $doc->updated_at instanceof Carbon ? $doc->updated_at : Carbon::parse($doc->updated_at);

                $action = 'updated';
                if ($created->eq($updated)) {
                    $action = 'created';
                } elseif ($doc->status === 'COMPLETED') {
                    $action = 'completed';
                }

                return [
                    'id' => $doc->id,
                    'title' => $doc->title,
                    'description' => "Document '{$doc->title}' was {$action}",
                    'time' => $updated->diffForHumans(),
                    'type' => $action,
                    'status' => $doc->status,
                    'icon' => $this->getIcon($action),
                    'color' => $this->getColor($action)
                ];
            });

            return response()->json($activity);
        } catch (\Exception $e) {
            \Log::error('Activity error: ' . $e->getMessage());
            // Return validation error structure or empty list to not break frontend
            return response()->json([], 200);
        }
    }

    private function getIcon($action)
    {
        switch ($action) {
            case 'created':
                return 'mdi-file-plus';
            case 'completed':
                return 'mdi-check-circle';
            default:
                return 'mdi-pencil';
        }
    }

    private function getColor($action)
    {
        switch ($action) {
            case 'created':
                return 'primary';
            case 'completed':
                return 'success';
            default:
                return 'info';
        }
    }
}
