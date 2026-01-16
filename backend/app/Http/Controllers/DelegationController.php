<?php

namespace App\Http\Controllers;

use App\Models\Delegation;
use App\Models\User;
use App\Services\DelegationService;
use Illuminate\Http\Request;

class DelegationController extends Controller
{
    protected $delegationService;

    public function __construct(DelegationService $delegationService)
    {
        $this->delegationService = $delegationService;
    }

    public function index(Request $request)
    {
        // Get delegations where I am the delegator (I delegated to someone)
        $myDelegations = $this->delegationService->getMyDelegations($request->user()->id);

        // Get delegations where I am the delegate (Someone delegated to me) (Optional display)
        $delegationsToMe = $this->delegationService->getDelegationsToMe($request->user()->id);

        return response()->json([
            'my_delegations' => $myDelegations,
            'delegations_to_me' => $delegationsToMe
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'delegate_email' => 'required|email|exists:users,email',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'reason' => 'nullable|string'
        ]);

        $delegate = User::where('email', $validated['delegate_email'])->firstOrFail();

        if ($delegate->id === $request->user()->id) {
            return response()->json(['message' => 'Cannot delegate to yourself'], 400);
        }

        $delegation = $this->delegationService->createDelegation($request->user()->id, [
            'delegate_user_id' => $delegate->id,
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
            'reason' => $validated['reason']
        ]);

        return response()->json($delegation, 201);
    }

    public function destroy(Request $request, $id)
    {
        $delegation = Delegation::where('user_id', $request->user()->id)->findOrFail($id);

        // Soft delete or just mark inactive
        $delegation->update(['is_active' => false]);

        return response()->json(['message' => 'Delegation cancelled']);
    }
}
