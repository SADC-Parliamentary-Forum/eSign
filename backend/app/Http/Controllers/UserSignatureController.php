<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserSignature;
use Illuminate\Support\Facades\Storage;

class UserSignatureController extends Controller
{
    /**
     * List user's saved signatures and initials.
     */
    public function index(Request $request)
    {
        $signatures = UserSignature::where('user_id', $request->user()->id)
            ->orderBy('type')
            ->orderByDesc('is_default')
            ->get(['id', 'type', 'name', 'is_default', 'image_data', 'method', 'created_at']);

        $signatures->makeVisible('image_data');

        return response()->json($signatures);
    }

    /**
     * Get a specific signature with image data.
     */
    public function show(Request $request, $id)
    {
        $signature = UserSignature::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $signature->makeVisible('image_data');

        return response()->json($signature);
    }

    /**
     * Create a new saved signature or initials.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:signature,initials',
            'name' => 'nullable|string|max:100',
            'image_data' => 'required|string', // Base64 encoded image
            'is_default' => 'nullable|boolean',
            'method' => 'nullable|in:DRAWN,UPLOADED,TYPED',
        ]);

        // Validate base64 image
        // 1. Max size check (approx 500KB = 680,000 base64 chars)
        if (strlen($validated['image_data']) > 680000) {
            return response()->json(['message' => 'Signature image too large. Max 500KB.'], 422);
        }

        // 2. Strict format check (No SVGs allowed)
        if (!preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $validated['image_data'])) {
            return response()->json([
                'message' => 'Invalid image format. Must be a base64 encoded PNG or JPEG image.'
            ], 422);
        }

        $user = $request->user();

        // If this is set as default, remove default from others
        if (!empty($validated['is_default'])) {
            UserSignature::where('user_id', $user->id)
                ->where('type', $validated['type'])
                ->update(['is_default' => false]);
        }

        // Auto-set as default if this is the first of its type
        $isFirst = !UserSignature::where('user_id', $user->id)
            ->where('type', $validated['type'])
            ->exists();

        $signature = UserSignature::create([
            'user_id' => $user->id,
            'type' => $validated['type'],
            'name' => $validated['name'] ?? ($validated['type'] === 'signature' ? 'My Signature' : 'My Initials'),
            'image_data' => $validated['image_data'],
            'is_default' => $validated['is_default'] ?? $isFirst,
            'method' => $validated['method'] ?? 'DRAWN',
        ]);

        $signature->makeVisible('image_data');

        return response()->json($signature, 201);
    }

    /**
     * Update a saved signature.
     */
    public function update(Request $request, $id)
    {
        $signature = UserSignature::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => 'nullable|string|max:100',
            'image_data' => 'nullable|string',
            'method' => 'nullable|in:DRAWN,UPLOADED,TYPED',
        ]);

        if (isset($validated['image_data'])) {
            if (strlen($validated['image_data']) > 680000) {
                return response()->json(['message' => 'Signature image too large. Max 500KB.'], 422);
            }
            if (!preg_match('/^data:image\/(png|jpeg|jpg);base64,/', $validated['image_data'])) {
                return response()->json([
                    'message' => 'Invalid image format. Must be PNG or JPEG.'
                ], 422);
            }
        }

        $signature->update($validated);

        $signature->makeVisible('image_data');

        return response()->json($signature);
    }

    /**
     * Delete a saved signature.
     */
    public function destroy(Request $request, $id)
    {
        $signature = UserSignature::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $wasDefault = $signature->is_default;
        $type = $signature->type;
        $userId = $signature->user_id;

        $signature->delete();

        // If deleted signature was default, make another one default
        if ($wasDefault) {
            UserSignature::where('user_id', $userId)
                ->where('type', $type)
                ->first()
                    ?->update(['is_default' => true]);
        }

        return response()->json(['message' => 'Signature deleted successfully']);
    }

    /**
     * Set a signature as the default for its type.
     */
    public function setDefault(Request $request, $id)
    {
        $signature = UserSignature::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $signature->setAsDefault();

        return response()->json(['message' => 'Default signature updated']);
    }
}
