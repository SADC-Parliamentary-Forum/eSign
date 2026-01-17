<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
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

// ...

Route::post('documents/{id}/signers', [DocumentController::class, 'addSigners']);
Route::get('documents/{id}/fields', [DocumentFieldController::class, 'index']);
Route::post('documents/{id}/fields', [DocumentFieldController::class, 'store']);
Route::post('documents/{id}/send', [DocumentController::class, 'send']);

// =============================================================================
// Public Routes
// =============================================================================
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1');

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

    // -------------------------------------------------------------------------
    // User Management
    // -------------------------------------------------------------------------
    Route::apiResource('/users', UserController::class);

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
    // Templates
    // -------------------------------------------------------------------------
    Route::apiResource('templates', TemplateController::class);
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
    Route::get('templates/{id}/versions', [TemplateController::class, 'getVersions']);

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
    Route::post('documents/bulk-delete', [App\Http\Controllers\DocumentController::class, 'bulkDestroy']);
    Route::get('documents/activity', [App\Http\Controllers\DocumentActivityController::class, 'index']);
    Route::get('documents/pending', [DocumentController::class, 'pending']);
    Route::apiResource('documents', DocumentController::class)->except(['update']);
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
    Route::post('documents/{id}/sign', [SignatureController::class, 'sign']);
    Route::post('documents/{id}/reject', [SignatureController::class, 'reject']);

    // -------------------------------------------------------------------------
    // Audit Logs
    // -------------------------------------------------------------------------
    Route::get('/audit-logs', [AuditController::class, 'index']);

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
        Route::post('/email/verify', [VerificationController::class, 'verifyEmail']);
        Route::post('/signers/{signerId}/otp', [VerificationController::class, 'createOTPVerification']);
        Route::post('/signers/{signerId}/otp/verify', [VerificationController::class, 'verifyOTP']);
        Route::post('/signers/{signerId}/device', [VerificationController::class, 'createDeviceVerification']);
        Route::get('/signers/{signerId}/status', [VerificationController::class, 'getVerificationStatus']);
    });

    // -------------------------------------------------------------------------
    // Evidence Packages (Phase 10: Legal Defensibility)
    // -------------------------------------------------------------------------
    // TODO: Implement EvidencePackageController
    // Route::prefix('evidence')->group(function () {
    //     Route::get('/documents/{id}', [EvidencePackageController::class, 'show']);
    //     Route::post('/documents/{id}/generate', [EvidencePackageController::class, 'generate']);
    //     Route::get('/documents/{id}/download', [EvidencePackageController::class, 'download']);
    // });

    // Delegations
    Route::get('/delegations', [App\Http\Controllers\DelegationController::class, 'index']);
    Route::post('/delegations', [App\Http\Controllers\DelegationController::class, 'store']);
    Route::delete('/delegations/{id}', [App\Http\Controllers\DelegationController::class, 'destroy']);

    // Compliance (Admin)
    // TODO: Implement ComplianceController
    // Route::post('/compliance/rules', [App\Http\Controllers\ComplianceController::class, 'store']);
});

