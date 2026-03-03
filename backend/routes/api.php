<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\MfaController;
use App\Http\Controllers\MagicLinkController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\SignatureController;
use App\Http\Controllers\SignerController;
use App\Http\Controllers\UserSignatureController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\DocumentFieldController;
use App\Http\Controllers\NotificationController;

// Health check endpoint (must return 200 for container healthcheck; catch so 500 is never returned)
Route::get('/health', function () {
    $db = 'down';
    $cache = 'down';
    try {
        $db = DB::connection()->getPdo() ? 'up' : 'down';
    } catch (\Throwable $e) {
        // Leave as 'down'
    }
    try {
        $cache = Cache::has('health_check') || Cache::put('health_check', true, 10) ? 'up' : 'down';
    } catch (\Throwable $e) {
        // Leave as 'down'
    }
    $status = ($db === 'up' && $cache === 'up') ? 'healthy' : 'degraded';
    return response()->json([
        'status' => $status,
        'timestamp' => now()->toISOString(),
        'services' => [
            'database' => $db,
            'cache' => $cache,
        ],
    ]);
});

// Broadcasting auth route for private channels
Broadcast::routes(['middleware' => ['auth:sanctum']]);

// ...

// ...

// =============================================================================
// Public Routes
// =============================================================================
Route::post('/auth/login', [AuthController::class, 'login'])->middleware(['throttle:5,1', 'human:login']);
Route::post('/auth/register', [AuthController::class, 'register'])->middleware(['throttle:5,1', 'human:register']);

// Password Reset
Route::post('/auth/forgot-password', [App\Http\Controllers\PasswordResetController::class, 'sendResetLinkEmail'])
    ->middleware(['guest', 'human:forgot_password', 'throttle:5,1'])
    ->name('password.email');

Route::post('/auth/reset-password', [App\Http\Controllers\PasswordResetController::class, 'reset'])
    ->middleware(['guest', 'throttle:5,1'])
    ->name('password.update');

// Magic Link Login
Route::get('/auth/magic/login/{id}', [MagicLinkController::class, 'login'])
    ->name('login.magic')
    ->middleware(['signed', 'throttle:5,1']);

// =============================================================================
// Guest Signer Routes (token-based access, no auth required)
// =============================================================================
Route::prefix('sign/{token}')->middleware('throttle:30,1')->group(function () {
    Route::get('/', [SignerController::class, 'show']);
    Route::post('/view', [SignerController::class, 'markViewed']);
    Route::post('/sign', [SignerController::class, 'sign']);
    Route::post('/decline', [SignerController::class, 'decline']);
});

// Verification (Public Signed Route)
Route::get('/verification/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

// =============================================================================
// Protected Routes
// =============================================================================
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {

    // -------------------------------------------------------------------------
    // Authentication
    // -------------------------------------------------------------------------
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::put('/auth/password', [AuthController::class, 'updatePassword']);
    Route::post('/auth/mfa/send', [MfaController::class, 'send']);
    Route::post('/auth/mfa/verify', [MfaController::class, 'verify']);
    Route::post('/auth/magic/generate', [MagicLinkController::class, 'generate']);
    Route::post('/email/verification-notification', [AuthController::class, 'resendVerification'])
        ->middleware(['throttle:60,1'])
        ->name('verification.send');

    // -------------------------------------------------------------------------
    // Notifications
    // -------------------------------------------------------------------------
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);

    // -------------------------------------------------------------------------
    // User Management (Moved to Admin Group)
    // -------------------------------------------------------------------------
    // Route::apiResource('/users', UserController::class);

    // -------------------------------------------------------------------------
    // User's Saved Signatures
    // -------------------------------------------------------------------------
    Route::prefix('signatures/mine')->group(function () {
        Route::get('/', [UserSignatureController::class, 'index']);
        Route::get('/{id}', [UserSignatureController::class, 'show']);
        Route::post('/', [UserSignatureController::class, 'store']);
        Route::put('/{id}', [UserSignatureController::class, 'update']);
        Route::delete('/{id}', [UserSignatureController::class, 'destroy']);
        Route::patch('/{id}/default', [UserSignatureController::class, 'setDefault']);
    });

    // -------------------------------------------------------------------------
    // Folders
    // -------------------------------------------------------------------------
    Route::apiResource('folders', App\Http\Controllers\FolderController::class);
    Route::post('folders/{id}/move-documents', [App\Http\Controllers\FolderController::class, 'moveDocuments']);
    Route::get('folders/{id}/download', [App\Http\Controllers\FolderController::class, 'download']);

    // -------------------------------------------------------------------------
    // Departments (read-only for all authenticated; create/update/delete under admin)
    // -------------------------------------------------------------------------
    Route::get('departments', [App\Http\Controllers\DepartmentController::class, 'index']);
    Route::get('departments/{department}', [App\Http\Controllers\DepartmentController::class, 'show']);

    // -------------------------------------------------------------------------
    // Organizational Roles (read-only for all authenticated; create/update/delete under admin)
    // -------------------------------------------------------------------------
    Route::get('org-roles', [App\Http\Controllers\OrganizationalRoleController::class, 'index']);
    Route::get('org-roles/{organizational_role}', [App\Http\Controllers\OrganizationalRoleController::class, 'show']);

    // -------------------------------------------------------------------------
    // Templates
    // -------------------------------------------------------------------------
    Route::apiResource('templates', TemplateController::class);
    Route::post('templates/{id}/bulk-create', [TemplateController::class, 'bulkCreate']);
    Route::post('templates/{id}/fields', [TemplateController::class, 'storeFields']);

    // Template Governance & Management
    Route::post('templates/{id}/roles', [TemplateController::class, 'storeRoles']);
    Route::post('templates/{id}/field-mappings', [TemplateController::class, 'storeFieldMappings']);
    Route::post('templates/{id}/thresholds', [TemplateController::class, 'storeThresholds']);
    Route::post('templates/{id}/submit-review', [TemplateController::class, 'submitForReview']);
    Route::post('templates/{id}/approve', [TemplateController::class, 'approve']);
    Route::post('templates/{id}/activate', [TemplateController::class, 'activate']);
    Route::post('templates/{id}/archive', [TemplateController::class, 'archive']);
    Route::get('templates/{id}/threshold-matrix', [TemplateController::class, 'getThresholdMatrix']);
    Route::post('templates/{id}/versions', [TemplateController::class, 'createVersion']);
    Route::post('documents/{id}/sign-self', [DocumentController::class, 'finishSelfSign']);
    // Route::get('templates/{id}/versions', [TemplateController::class, 'getVersions']);
    Route::get('templates/{id}/pdf', [TemplateController::class, 'streamPdf']);

    // Template Enhanced Features
    Route::post('templates/{id}/clone', [TemplateController::class, 'clone']);
    Route::post('templates/{id}/apply', [TemplateController::class, 'apply']);
    Route::get('templates/meta/categories', [TemplateController::class, 'categories']);
    Route::get('templates/meta/most-used', [TemplateController::class, 'mostUsed']);
    Route::get('templates/meta/recent', [TemplateController::class, 'recentlyUsed']);

    // -------------------------------------------------------------------------
    // Workflows
    // -------------------------------------------------------------------------
    Route::get('workflows/{id}', [App\Http\Controllers\WorkflowController::class, 'show']);
    Route::get('workflows/{id}/steps', [App\Http\Controllers\WorkflowController::class, 'getSteps']);
    Route::post('workflows/{id}/cancel', [App\Http\Controllers\WorkflowController::class, 'cancel']);
    Route::get('documents/{documentId}/workflow', [App\Http\Controllers\WorkflowController::class, 'getByDocument']);
    Route::get('workflows/user/pending', [App\Http\Controllers\WorkflowController::class, 'getUserPending']);

    // -------------------------------------------------------------------------
    // Documents
    // -------------------------------------------------------------------------
    Route::get('documents/stats', [App\Http\Controllers\DocumentStatsController::class, 'index']);
    Route::get('documents/stats/weekly', [App\Http\Controllers\DocumentStatsController::class, 'weekly']);
    Route::post('documents/bulk-delete', [App\Http\Controllers\DocumentController::class, 'bulkDestroy']);
    Route::post('documents/bulk-sign', [App\Http\Controllers\DocumentController::class, 'bulkSign'])->middleware('human:bulk_sign');
    Route::post('documents/bulk-download', [App\Http\Controllers\DocumentController::class, 'bulkDownload']);
    Route::get('documents/activity', [App\Http\Controllers\DocumentActivityController::class, 'index']);
    Route::get('documents/pending', [DocumentController::class, 'pending']);

    // Explicit Upload Route for Bot Protection
    Route::post('documents', [DocumentController::class, 'store'])->middleware('human:document_upload');
    Route::apiResource('documents', DocumentController::class)->except(['update', 'store']);
    Route::post('documents/{id}/signers', [DocumentController::class, 'addSigners']);
    Route::get('documents/{id}/fields', [DocumentFieldController::class, 'index']);
    Route::post('documents/{id}/fields', [DocumentFieldController::class, 'store']);
    Route::post('documents/{id}/send', [DocumentController::class, 'send']);
    Route::get('documents/{id}/status', [DocumentController::class, 'status']);
    Route::get('documents/{id}/evidence', [DocumentController::class, 'downloadEvidence']);
    Route::get('documents/{id}/pdf', [DocumentController::class, 'streamPdf']);

    // -------------------------------------------------------------------------
    // Signing (for authenticated users viewing their assigned documents)
    // -------------------------------------------------------------------------
    Route::post('documents/{id}/sign', [SignatureController::class, 'sign'])->middleware('human:sign_document');
    Route::post('documents/{id}/reject', [SignatureController::class, 'reject']);

    // -------------------------------------------------------------------------
    // Audit Logs (Moved to Admin Group)
    // -------------------------------------------------------------------------
    // Route::get('/audit-logs', [AuditController::class, 'index']);

    // -------------------------------------------------------------------------
    // AI Features
    // -------------------------------------------------------------------------
    Route::post('/documents/{id}/analyze', [App\Http\Controllers\AIController::class, 'analyze']);
    Route::post('/ai/suggest-template', [App\Http\Controllers\AIController::class, 'suggestTemplate']);
    Route::post('/ai/analyze-document', [App\Http\Controllers\AIController::class, 'analyzeDocument']);
    Route::post('/ai/validate-template', [App\Http\Controllers\AIController::class, 'validateTemplateForDocument']);
    Route::post('/ai/best-match', [App\Http\Controllers\AIController::class, 'getBestMatch']);

    // -------------------------------------------------------------------------
    // Identity Verification (Phase 10: Legal Defensibility)
    // -------------------------------------------------------------------------
    Route::prefix('verification')->group(function () {
        Route::post('/signers/{signerId}/email', [VerificationController::class, 'createEmailVerification']);

        Route::post('/signers/{signerId}/otp', [VerificationController::class, 'createOTPVerification'])->middleware('throttle:10,1');
        Route::post('/signers/{signerId}/otp/verify', [VerificationController::class, 'verifyOTP'])->middleware('throttle:10,1');
        Route::post('/signers/{signerId}/device', [VerificationController::class, 'createDeviceVerification']);
        Route::get('/signers/{signerId}/status', [VerificationController::class, 'getVerificationStatus']);
    });

    // -------------------------------------------------------------------------
    // Evidence Packages (Phase 10: Legal Defensibility)
    // -------------------------------------------------------------------------
    // TODO: Implement EvidencePackageController
    Route::prefix('evidence')->group(function () {
        Route::get('/documents/{id}', [App\Http\Controllers\EvidencePackageController::class, 'show']);
        Route::post('/documents/{id}/generate', [App\Http\Controllers\EvidencePackageController::class, 'generate']);
        Route::get('/documents/{id}/download', [App\Http\Controllers\EvidencePackageController::class, 'download']);
    });

    // Delegations
    Route::get('/delegations', [App\Http\Controllers\DelegationController::class, 'index']);
    Route::post('/delegations', [App\Http\Controllers\DelegationController::class, 'store']);
    Route::delete('/delegations/{id}', [App\Http\Controllers\DelegationController::class, 'destroy']);

    // Compliance (Admin)
    // TODO: Implement ComplianceController
    // Route::post('/compliance/rules', [App\Http\Controllers\ComplianceController::class, 'store']);
    // -------------------------------------------------------------------------
    // Admin Routes
    // -------------------------------------------------------------------------
    Route::middleware(['admin'])->prefix('admin')->group(function () {
        Route::get('/logs/system', [App\Http\Controllers\SystemLogController::class, 'show']);
        Route::get('/roles', [App\Http\Controllers\RoleController::class, 'index']);
        Route::get('/settings', [App\Http\Controllers\SettingsController::class, 'index']);
        Route::put('/settings', [App\Http\Controllers\SettingsController::class, 'update']);

        // Moved from general protected area
        Route::apiResource('/users', UserController::class);
        Route::get('/audit-logs', [AuditController::class, 'index']);

        // Departments and org-roles: only admins can create/update/delete (read remains in main group for templates)
        Route::apiResource('/departments', App\Http\Controllers\DepartmentController::class)->only(['store', 'update', 'destroy']);
        Route::apiResource('/org-roles', App\Http\Controllers\OrganizationalRoleController::class)->only(['store', 'update', 'destroy']);
    });
});
