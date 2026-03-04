<?php

namespace App\Http\Controllers;

use App\Services\AmountInWordsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AmountVerificationController extends Controller
{
    protected AmountInWordsService $service;

    public function __construct(AmountInWordsService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /api/amount/{numeric}/words
     *
     * Returns the canonical word form of a numeric amount.
     * Used by the frontend/mobile to show signers the expected format.
     *
     * Example: GET /api/amount/1500.50/words
     * Response: { "numeric": 1500.50, "words": "One Thousand Five Hundred Rand and Fifty Cents" }
     */
    public function toWords(Request $request, string $numeric): JsonResponse
    {
        $amount = (float) $numeric;

        if ($amount < 0) {
            return response()->json(['message' => 'Amount must be a positive number.'], 422);
        }

        $currency = $request->query('currency', 'Rand');
        $cents = $request->query('cents', 'Cents');

        return response()->json([
            'numeric' => $amount,
            'words' => $this->service->toWords($amount, $currency, $cents),
            'currency' => $currency,
        ]);
    }

    /**
     * GET /api/documents/{id}/amount-words
     *
     * Extracts the numeric amount from the document's PDF, converts it to
     * canonical words, and returns both. Used by the mobile/frontend to show
     * the signer the expected word format before they type it.
     */
    public function fromDocument(Request $request, string $id): JsonResponse
    {
        $document = \App\Models\Document::findOrFail($id);

        // Auth: owner or signer
        if (
            $request->user()->id !== $document->user_id &&
            !$document->signers()->where('email', $request->user()->email)->exists()
        ) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $currency = $request->query('currency', 'Rand');
        $cents = $request->query('cents', 'Cents');

        // Extract from PDF
        $amount = $this->service->extractAmountFromDocument($document);

        // Fallback to DB amount
        if ($amount === null && $document->amount) {
            $amount = (float) $document->amount;
        }

        if ($amount === null || $amount <= 0) {
            return response()->json([
                'message' => 'No numeric amount could be found in the document PDF.',
            ], 422);
        }

        return response()->json([
            'document_id' => $document->id,
            'numeric' => $amount,
            'words' => $this->service->toWords($amount, $currency, $cents),
            'currency' => $currency,
            'source' => 'pdf',
        ]);
    }

    /**
     * POST /api/amount/verify
     *
     * Verifies that a word amount matches a numeric amount.
     * Returns whether they match and what the expected canonical form is.
     *
     * Body: { "numeric_amount": 1500.50, "amount_in_words": "One Thousand Five Hundred Rand and Fifty Cents" }
     * Response: { "match": true, "expected": "...", "provided": "..." }
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'numeric_amount' => 'required|numeric|min:0',
            'amount_in_words' => 'required|string|max:500',
            'currency' => 'nullable|string|max:50',
            'cents' => 'nullable|string|max:50',
        ]);

        $result = $this->service->verify(
            (float) $validated['numeric_amount'],
            $validated['amount_in_words'],
            $validated['currency'] ?? 'Rand',
            $validated['cents'] ?? 'Cents'
        );

        $status = $result['match'] ? 200 : 422;

        return response()->json($result, $status);
    }
}
