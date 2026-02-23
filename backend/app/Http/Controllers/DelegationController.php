<?php

namespace App\Http\Controllers;

use App\Models\Delegation;
use App\Models\User;
use App\Services\DelegationService;
use App\Services\AuditService;
use Illuminate\Http\Request;

class DelegationController extends Controller
{
    /**
     * Maximum number of concurrent active delegations per user.
     */
    private const MAX_ACTIVE_DELEGATIONS = 3;

    /**
     * Maximum delegation duration in months.
     */
    private const MAX_DELEGATION_MONTHS = 6;

    protected $delegationService;
    protected $auditService;

    public function __construct(DelegationService $delegationService, AuditService $auditService)
    {
        $this->delegationService = $delegationService;
        $this->auditService = $auditService;
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
        $maxEndDate = now()->addMonths(self::MAX_DELEGATION_MONTHS)->toDateString();

        $validated = $request->validate([
            'delegate_email' => 'required|email|exists:users,email',
            'starts_at' => 'required|date|after_or_equal:today',
            'ends_at' => "required|date|after:starts_at|before_or_equal:{$maxEndDate}",
            'reason' => 'required|string|max:500',
        ]);

        $delegator = $request->user();
        $delegate = User::where('email', $validated['delegate_email'])->firstOrFail();

        // Security: Prevent self-delegation
        if ($delegate->id === $delegator->id) {
            return response()->json(['message' => 'Cannot delegate to yourself'], 400);
        }

        // Security: Check departmental boundaries (if departments are configured)
        if ($delegator->department_id && $delegate->department_id) {
            if ($delegator->department_id !== $delegate->department_id) {
                return response()->json([
                    'message' => 'Delegation is only allowed to users within your department.'
                ], 403);
            }
        }

        // Security: Limit number of concurrent active delegations
        $activeDelegations = Delegation::where('user_id', $delegator->id)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })
            ->count();

        if ($activeDelegations >= self::MAX_ACTIVE_DELEGATIONS) {
            return response()->json([
                'message' => 'Maximum ' . self::MAX_ACTIVE_DELEGATIONS . ' active delegations allowed. Please cancel an existing delegation first.'
            ], 422);
        }

        // Security: Prevent duplicate delegations to same user
        $existingDelegation = Delegation::where('user_id', $delegator->id)
            ->where('delegate_user_id', $delegate->id)
            ->where('is_active', true)
            ->where(function ($q) use ($validated) {
                // Check for overlapping dates
                $q->where(function ($q2) use ($validated) {
                    $q2->where('starts_at', '<=', $validated['ends_at'])
                        ->where(function ($q3) use ($validated) {
                            $q3->whereNull('ends_at')
                                ->orWhere('ends_at', '>=', $validated['starts_at']);
                        });
                });
            })
            ->exists();

        if ($existingDelegation) {
            return response()->json([
                'message' => 'An active delegation to this user already exists for the specified period.'
            ], 422);
        }

        $delegation = $this->delegationService->createDelegation($delegator->id, [
            'delegate_user_id' => $delegate->id,
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
            'reason' => $validated['reason']
        ]);

        // Audit: Log delegation creation
        $this->auditService->log($delegator, 'delegation_created', 'delegation', $delegation->id, [
            'delegate_email' => $delegate->email,
            'delegate_name' => $delegate->name,
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
            'reason' => $validated['reason'],
        ]);

        return response()->json($delegation, 201);
    }

    public function destroy(Request $request, $id)
    {
        $delegation = Delegation::where('user_id', $request->user()->id)->findOrFail($id);

        // Capture details for audit
        $delegateId = $delegation->delegate_user_id;

        // Soft delete or just mark inactive
        $delegation->update(['is_active' => false]);

        // Audit: Log delegation cancellation
        $this->auditService->log($request->user(), 'delegation_cancelled', 'delegation', $id, [
            'delegate_user_id' => $delegateId,
        ]);

        return response()->json(['message' => 'Delegation cancelled']);
    }
}
