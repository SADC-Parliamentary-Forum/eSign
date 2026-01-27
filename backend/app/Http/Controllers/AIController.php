<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Template;
use App\Services\AIService;
use App\Services\AITemplateMatchingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AIController extends Controller
{
    protected $aiService;
    protected AITemplateMatchingService $templateMatchingService;

    public function __construct(
        AIService $aiService,
        AITemplateMatchingService $templateMatchingService
    ) {
        $this->aiService = $aiService;
        $this->templateMatchingService = $templateMatchingService;
    }

    public function analyze(Request $request, $id)
    {
        $document = Document::findOrFail($id);

        // Auth check
        if (auth()->user()->cannot('view', $document)) {
            abort(403);
        }

        try {
            $risks = $this->aiService->analyzeRisk($document);
            $suggestions = $this->aiService->suggestSignatureFields($document);

            return response()->json([
                'document_id' => $document->id,
                'risks' => $risks,
                'suggestions' => $suggestions,
                'ai_model' => 'Heuristic-v1',
            ]);
        } catch (\Exception $e) {
            $message = app()->isProduction() ? 'AI Analysis failed.' : 'AI Analysis failed: ' . $e->getMessage();
            return response()->json(['message' => $message], 500);
        }
    }

    /**
     * Suggest templates for an uploaded document.
     */
    public function suggestTemplate(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:pdf|max:20480'
        ]);

        try {
            $file = $request->file('file');
            $tempPath = $file->store('temp', 'local');
            $fullPath = Storage::disk('local')->path($tempPath);

            $suggestions = $this->templateMatchingService->suggestTemplates($fullPath);

            Storage::disk('local')->delete($tempPath);

            return response()->json([
                'suggestions' => $suggestions,
                'total_count' => count($suggestions)
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::build([
                'driver' => 'single',
                'path' => storage_path('logs/ai_error.log'),
            ])->error($e);
            $message = app()->isProduction() ? 'An error occurred during template suggestion.' : $e->getMessage();
            return response()->json(['message' => $message], 500);
        }
    }

    /**
     * Analyze a document without template matching.
     */
    public function analyzeDocument(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:pdf|max:20480'
        ]);

        try {
            $file = $request->file('file');
            $tempPath = $file->store('temp', 'local');
            $fullPath = Storage::disk('local')->path($tempPath);

            $analysis = $this->templateMatchingService->analyzeDocument($fullPath);

            Storage::disk('local')->delete($tempPath);

            return response()->json($analysis);
        } catch (\Exception $e) {
            $message = app()->isProduction() ? 'An error occurred during AI processing.' : $e->getMessage();
            return response()->json(['message' => $message], 500);
        }
    }

    /**
     * Validate if a template is applicable for a document.
     */
    public function validateTemplateForDocument(Request $request)
    {
        $validated = $request->validate([
            'template_id' => 'required|exists:templates,id',
            'file' => 'required|file|mimes:pdf|max:20480'
        ]);

        try {
            $template = Template::findOrFail($validated['template_id']);
            $file = $request->file('file');
            $tempPath = $file->store('temp', 'local');
            $fullPath = Storage::disk('local')->path($tempPath);

            $validation = $this->templateMatchingService->validateTemplateForDocument($template, $fullPath);

            Storage::disk('local')->delete($tempPath);

            return response()->json($validation);
        } catch (\Exception $e) {
            $message = app()->isProduction() ? 'An error occurred during AI processing.' : $e->getMessage();
            return response()->json(['message' => $message], 500);
        }
    }

    /**
     * Get best matching template for a document.
     */
    public function getBestMatch(Request $request)
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:pdf|max:20480'
        ]);

        try {
            $file = $request->file('file');
            $tempPath = $file->store('temp', 'local');
            $fullPath = Storage::disk('local')->path($tempPath);

            $bestMatch = $this->templateMatchingService->getBestMatch($fullPath);

            Storage::disk('local')->delete($tempPath);

            if (!$bestMatch) {
                return response()->json([
                    'message' => 'No suitable template found',
                    'best_match' => null
                ], 404);
            }

            return response()->json([
                'best_match' => $bestMatch,
                'message' => 'Best matching template found'
            ]);
        } catch (\Exception $e) {
            $message = app()->isProduction() ? 'An error occurred during AI processing.' : $e->getMessage();
            return response()->json(['message' => $message], 500);
        }
    }
}
