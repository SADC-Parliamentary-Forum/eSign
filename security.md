# eSign Application Security Audit Report

**Audit Date:** 2026-01-31
**Application:** eSign - Electronic Signature Platform
**Auditor:** Security Assessment (Automated + Manual Review)
**Status:** IN PROGRESS - Remediation underway

---

## Remediation Progress (Updated: 2026-01-31)

| Task | Description | Status | Priority |
|------|-------------|--------|----------|
| #1 | Rotate all exposed production secrets | **PENDING (Manual)** | CRITICAL |
| #2 | Disable debug mode and fix production config | **COMPLETED** | CRITICAL |
| #3 | Fix Sanctum token expiration | **COMPLETED** | CRITICAL |
| #4 | Fix OTP security vulnerabilities | **COMPLETED** | CRITICAL |
| #5 | Implement file upload security | **COMPLETED** | CRITICAL |
| #6 | Prevent deletion of signed documents | **COMPLETED** | CRITICAL |
| #7 | Fix IDOR vulnerabilities and authorization | **COMPLETED** | HIGH |
| #8 | Fix SQL injection in orderBy | **COMPLETED** | HIGH |
| #9 | Implement account lockout & password policy | **COMPLETED** | HIGH |
| #10 | Configure HTTPS/TLS | Pending | HIGH |
| #11 | Implement encrypted backups | Pending | HIGH |
| #12 | Add document hash verification | Pending | HIGH |
| #13 | Complete audit logging coverage | Pending | HIGH |
| #14 | Standardize error handling | **COMPLETED** | HIGH |
| #15 | Restrict CORS configuration | **COMPLETED** | MEDIUM |
| #16 | Add pagination limits | **COMPLETED** | MEDIUM |
| #17 | Upgrade cryptographic settings | **COMPLETED** | MEDIUM |
| #18 | Implement single-use magic links | Pending | MEDIUM |
| #19 | Add delegation boundaries | Pending | MEDIUM |
| #20 | Protect system logs | **COMPLETED** | MEDIUM |

**Completed:** 12/20 tasks (60%)
**Remaining Critical:** 1 (requires manual secret rotation)
**Remaining High:** 4
**Remaining Medium:** 3

---

## Executive Summary

A comprehensive security audit was conducted against the eSign application following the Pre-Production Application Security Test Strategy & Validation Framework. The audit identified **9 Critical**, **11 High**, and **10 Medium** severity issues that must be remediated before production deployment.

### Compliance Status

| Standard | Status | Key Gaps |
|----------|--------|----------|
| OWASP ASVS | Non-compliant | V2 (Auth), V3 (Session), V6 (Crypto), V7 (Logging) |
| NIST 800-53 | Non-compliant | AC (Access Control), IA (Auth), AU (Audit), SC (System Protection) |
| ISO 27001 | Non-compliant | A.9 (Access), A.10 (Crypto), A.12 (Operations), A.14 (Dev Security) |

### Security Sign-Off Status

| Criterion | Status |
|-----------|--------|
| Threat Modeling Completed | Not Done |
| Automated Security Tests Passed | **FAILED** |
| Manual Review Completed | **Done** |
| Critical Issues | **9** |
| High Issues | **11** |
| Medium Issues | **10** |

**Decision: REJECTED - Security Risks Unacceptable**

---

## Critical Findings

### 1. Production Secrets Committed to Git
**Severity:** CRITICAL
**Location:** `backend/.env`, `production/.env`, `docker-compose.prod.yml`

All production secrets are exposed in version control:
- Database passwords
- APP_KEY, JWT_SECRET, ENCRYPTION_KEY
- MinIO credentials
- Email credentials (SADCParliament02)
- REVERB keys
- reCAPTCHA keys

### 2. Debug Mode Enabled in Production
**Severity:** CRITICAL
**Location:** `production/.env:9`, `docker-compose.prod.yml:38`

`APP_DEBUG=true` exposes stack traces, configuration values, and file paths to attackers.

### 3. Sanctum Tokens Never Expire
**Severity:** CRITICAL
**Location:** `backend/config/sanctum.php:50`

Token expiration is set to `null`, meaning compromised tokens remain valid indefinitely.

### 4. OTP Security Vulnerabilities
**Severity:** CRITICAL
**Location:** `backend/app/Http/Controllers/VerificationController.php`

- Line 77: OTP stored in plain text in database
- Line 85: OTP logged to application logs
- Line 90: Debug OTP returned in API response
- MfaController.php:65: Type coercion vulnerability (`!=` instead of `!==`)

### 5. No Malware Scanning on File Uploads
**Severity:** CRITICAL
**Location:** `backend/app/Jobs/ProcessDocumentUpload.php`

Uploaded documents are processed without any malware/antivirus scanning.

### 6. No File Signature Validation
**Severity:** CRITICAL
**Location:** `backend/app/Services/DocumentConversionService.php:21`

Files are validated only by extension, allowing extension-based attacks (e.g., .exe renamed to .pdf).

### 7. Signed Documents Can Be Deleted
**Severity:** CRITICAL
**Location:** `backend/app/Http/Controllers/DocumentController.php:472-493`

COMPLETED documents can be deleted by owner, destroying legal evidence and audit trail.

### 8. Default Credentials in Production
**Severity:** CRITICAL
**Location:** `docker-compose.prod.yml`

- Database password: `secret`
- MinIO credentials: `minioadmin/minioadmin`

### 9. MFA Type Coercion Vulnerability
**Severity:** CRITICAL
**Location:** `backend/app/Http/Controllers/MfaController.php:65`

Uses loose comparison `!=` instead of strict `!==` for OTP verification.

---

## High Severity Findings

### 10. No Account Lockout
**Location:** `backend/app/Http/Controllers/AuthController.php`

No lockout mechanism after failed login attempts enables brute-force attacks.

### 11. IDOR on Document Status Endpoint
**Location:** `backend/app/Http/Controllers/DocumentController.php:367-398`

Any authenticated user can view any document's signing status without authorization check.

### 12. Missing Authorization on Admin Endpoints
**Location:** `OrganizationalRoleController.php`, `DepartmentController.php`, `SettingsController.php`

CRUD operations lack authorization checks - any authenticated user can modify organizational structure and system settings.

### 13. SQL Injection in orderBy Parameter
**Location:** `backend/app/Http/Controllers/DocumentController.php:59-61`

User-controlled column name passed directly to `orderBy()` without validation.

### 14. No Document Hash Verification
**Location:** `backend/app/Services/DocumentService.php`

Document hashes are computed at upload but never verified on retrieval.

### 15. No HTTPS/TLS Enforcement
**Location:** `docker/nginx/conf.d/esign.conf`

Nginx listens on HTTP only; no SSL/TLS configuration.

### 16. Unencrypted Backups
**Location:** `docker/scripts/backup-db.sh`

Database backups stored as plain text SQL dumps.

### 17. Exception Messages Exposed in API
**Location:** `backend/app/Http/Controllers/EvidencePackageController.php:65,97`

Internal exception messages returned directly to API clients.

### 18. Incomplete Audit Logging
**Location:** Various controllers

Document upload, delete, send, and signer rejection not logged to audit trail.

### 19. Weak Password Policy
**Location:** `backend/app/Http/Controllers/AuthController.php:81`

Only minimum 8 characters required; no complexity requirements.

### 20. No Audit Log Retention Policy
**Location:** `backend/config/logging.php`

No retention or archival policy for audit logs.

---

## Medium Severity Findings

| # | Issue | Location |
|---|-------|----------|
| 21 | MFA OTP not hashed in cache | `MfaController.php:36` |
| 22 | Magic links not single-use | `MagicLinkController.php` |
| 23 | SHA1 used for email verification | `AppServiceProvider.php:30` |
| 24 | CORS allows all methods/headers | `config/cors.php:20,26` |
| 25 | No pagination limits on documents | `DocumentController.php:63` |
| 26 | Health check endpoint unprotected | `routes/api.php:21` |
| 27 | RSA-2048 (should be 4096) | `CertificateService.php:61` |
| 28 | Delegation has no boundaries | `DelegationController.php` |
| 29 | System logs exposed to admin | `SystemLogController.php` |
| 30 | Session encryption disabled | `production/.env` |

---

## eSign-Specific Findings (Legal Non-Repudiation)

| Requirement | Status | Gap |
|-------------|--------|-----|
| Cryptographic document binding | Partial | Hash computed but never verified |
| Trusted timestamp | Missing | No timestamp authority (RFC 3161) |
| Document immutability post-sign | Missing | COMPLETED docs can be deleted |
| Tamper-evident audit logs | Partial | No hash chain or crypto signing |
| Evidence package for legal use | Implemented | But relies on unverified hashes |

---

## Remediation Task List

### CRITICAL Priority (Must fix before production)

#### Task #1: Rotate All Exposed Production Secrets
**Effort:** High
**Status:** Pending

All production secrets are committed to git and must be rotated immediately:

1. Database passwords (`secret` → strong random)
2. APP_KEY (regenerate with `php artisan key:generate`)
3. JWT_SECRET (regenerate 64-char hex)
4. ENCRYPTION_KEY (regenerate 64-char hex)
5. MinIO credentials (`minioadmin` → strong random)
6. Email password (`SADCParliament02` → new credential)
7. REVERB_APP_KEY and REVERB_APP_SECRET
8. RECAPTCHA_SECRET_KEY (regenerate in Google console)

After rotation:
- Remove secrets from git history using BFG or git filter-branch
- Move secrets to CI/CD secrets management (GitHub Secrets, Vault, etc.)
- Never commit .env files again

**Files affected:**
- `backend/.env`
- `production/.env`
- `env.production`
- `docker-compose.prod.yml`

---

#### Task #2: Disable Debug Mode and Fix Production Config
**Effort:** Low
**Status:** Pending

1. Set `APP_DEBUG=false` in:
   - `production/.env` (line 9)
   - `docker-compose.prod.yml` (line 38)

2. Set `SESSION_SECURE_COOKIE=true` in `production/.env`

3. Set `SESSION_ENCRYPT=true` for session encryption

4. Change default DB password from `secret` to strong random in:
   - `docker-compose.prod.yml` (lines 44-45, 114-115)

5. Change MinIO defaults from `minioadmin` in:
   - `docker-compose.prod.yml` (lines 149-150)

6. Configure `LOG_LEVEL=warning` or `error` for production

---

#### Task #3: Fix Sanctum Token Expiration
**Effort:** Low
**Status:** Pending

**File:** `backend/config/sanctum.php` (line 50)

Change:
```php
'expiration' => null,
```

To:
```php
'expiration' => 60, // 1 hour expiration
```

Also implement token refresh mechanism in AuthController for seamless user experience.

---

#### Task #4: Fix OTP Security Vulnerabilities
**Effort:** Medium
**Status:** Pending

**File:** `backend/app/Http/Controllers/VerificationController.php`

1. **Hash OTP before storage** (line 71-77):
```php
// Change from:
'verification_code' => $otp
// To:
'verification_code' => hash('sha256', $otp)
```

2. **Remove OTP from logs** (line 85):
```php
// DELETE THIS LINE:
\Log::info("OTP for Signer {$signer->email}: {$otp}");
```

3. **Remove debug OTP from response** (line 90):
```php
// DELETE THIS LINE:
'debug_otp' => $otp
```

4. **Update verification to compare hashed values** (line 121):
```php
hash('sha256', $request->code) !== $verification->verification_code
```

**File:** `backend/app/Http/Controllers/MfaController.php` (line 65)

```php
// Change from loose comparison:
$cachedCode != $request->code
// To strict comparison:
$cachedCode !== (string) $request->code
```

---

#### Task #5: Implement File Upload Security
**Effort:** High
**Status:** Pending

1. **Add file signature (magic bytes) validation** in DocumentController or DocumentService:
```php
private function validateFileSignature($file): bool
{
    $handle = fopen($file->getPathname(), 'rb');
    $bytes = fread($handle, 8);
    fclose($handle);

    $signatures = [
        'pdf' => "\x25\x50\x44\x46",  // %PDF
        'docx' => "\x50\x4B\x03\x04", // PK (ZIP)
        'doc' => "\xD0\xCF\x11\xE0",  // OLE
    ];

    foreach ($signatures as $type => $sig) {
        if (strpos($bytes, $sig) === 0) return true;
    }
    return false;
}
```

2. **Integrate ClamAV for malware scanning:**
   - Add ClamAV container to docker-compose
   - Install `xenolope/quahog` package for PHP ClamAV client
   - Scan files in ProcessDocumentUpload job before processing

3. **Update ProcessDocumentUpload.php** (line 45+):
   - Add malware scan before conversion
   - Reject infected files with proper error message
   - Log security events for blocked uploads

---

#### Task #6: Prevent Deletion of Signed Documents
**Effort:** Low
**Status:** Pending

**File:** `backend/app/Http/Controllers/DocumentController.php`

1. **Fix destroy() method** (lines 472-493):
```php
public function destroy(Request $request, $id)
{
    $document = Document::findOrFail($id);

    if ($request->user()->cannot('delete', $document)) {
        abort(403, 'Unauthorized');
    }

    // ADD THIS CHECK:
    if (in_array($document->status, ['COMPLETED', 'SIGNED'])) {
        abort(403, 'Signed documents cannot be deleted. Use void instead.');
    }

    // ... rest of method
}
```

2. **Fix bulkDestroy() method** (lines 497-530):
   - Add same status check before deletion
   - Skip COMPLETED/SIGNED documents in bulk operations

3. **Implement void functionality** as alternative:
   - Add `voided_at`, `voided_by`, `void_reason` to documents table
   - Create void endpoint that marks document as voided but preserves it
   - Voided documents should be read-only

---

### HIGH Priority (Required for compliance)

#### Task #7: Fix IDOR Vulnerabilities and Add Missing Authorization
**Effort:** High
**Status:** Pending

1. **DocumentController.php status()** (lines 367-398):
```php
public function status($id)
{
    $document = Document::findOrFail($id);

    // ADD AUTHORIZATION:
    if (auth()->user()->cannot('view', $document)) {
        abort(403, 'Unauthorized');
    }

    // ... rest of method
}
```

2. **OrganizationalRoleController.php** - Add admin middleware or policy:
   - `store()` line 26 - require admin
   - `update()` line 52 - require admin
   - `destroy()` line 72 - require admin

3. **DepartmentController.php** - Add admin middleware or policy:
   - `store()` line 25 - require admin
   - `update()` line 50 - require admin
   - `destroy()` line 69 - require admin

4. **SettingsController.php**:
   - `index()` line 13 - add auth middleware
   - `update()` line 23 - require admin role

5. **Create missing Policy classes:**
   - OrganizationalRolePolicy
   - DepartmentPolicy
   - TemplatePolicy
   - FolderPolicy
   - SettingsPolicy

---

#### Task #8: Fix SQL Injection in orderBy Parameter
**Effort:** Low
**Status:** Pending

**File:** `backend/app/Http/Controllers/DocumentController.php` (lines 59-61)

Current vulnerable code:
```php
$sortBy = $request->input('sort', 'updated_at');
$sortOrder = $request->input('order', 'desc');
$query->orderBy($sortBy, $sortOrder);
```

Fix with whitelist validation:
```php
$allowedColumns = ['updated_at', 'created_at', 'title', 'status'];
$allowedOrders = ['asc', 'desc'];

$sortBy = in_array($request->input('sort'), $allowedColumns)
    ? $request->input('sort')
    : 'updated_at';

$sortOrder = in_array(strtolower($request->input('order')), $allowedOrders)
    ? strtolower($request->input('order'))
    : 'desc';

$query->orderBy($sortBy, $sortOrder);
```

---

#### Task #9: Implement Account Lockout and Strengthen Password Policy
**Effort:** Medium
**Status:** Pending

1. **Add failed login tracking to User model:**
   - Create migration adding `failed_login_attempts` and `locked_until` columns
   - Reset counter on successful login

2. **Implement lockout in AuthController.php:**
```php
public function login(Request $request)
{
    $user = User::where('email', $request->email)->first();

    if ($user && $user->locked_until && $user->locked_until > now()) {
        return response()->json([
            'message' => 'Account locked. Try again later.'
        ], 429);
    }

    if (!Auth::attempt($credentials)) {
        if ($user) {
            $user->increment('failed_login_attempts');
            if ($user->failed_login_attempts >= 5) {
                $user->update(['locked_until' => now()->addMinutes(30)]);
            }
        }
        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    // Reset on success
    $user->update(['failed_login_attempts' => 0, 'locked_until' => null]);
    // ... continue login
}
```

3. **Strengthen password policy** (AuthController.php line 81):
```php
'password' => [
    'required',
    'string',
    'min:12',
    'confirmed',
    Password::min(12)
        ->mixedCase()
        ->numbers()
        ->symbols()
        ->uncompromised(),
],
```

---

#### Task #10: Configure HTTPS/TLS and Security Headers
**Effort:** Medium
**Status:** Pending

**File:** `docker/nginx/conf.d/esign.conf`

1. **Add SSL configuration:**
```nginx
server {
    listen 80;
    server_name esign.sadcpf.org;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name esign.sadcpf.org;

    ssl_certificate /etc/nginx/ssl/cert.pem;
    ssl_certificate_key /etc/nginx/ssl/key.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256;
    ssl_prefer_server_ciphers off;

    # HSTS
    add_header Strict-Transport-Security "max-age=63072000" always;

    # ... rest of config
}
```

2. **Add/verify security headers:**
```nginx
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Content-Security-Policy "default-src 'self'" always;
```

3. **Update docker-compose.prod.yml** to mount SSL certificates

---

#### Task #11: Implement Encrypted Backups
**Effort:** Medium
**Status:** Pending

**File:** `docker/scripts/backup-db.sh`

Replace current script:
```bash
#!/bin/bash
set -e

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="backups/db"
ENCRYPTION_KEY_FILE="/run/secrets/backup_key"

mkdir -p $BACKUP_DIR

# Create encrypted backup
docker-compose exec -T postgres pg_dump -U sadc_esign sadc_esign | \
    gzip | \
    gpg --symmetric --cipher-algo AES256 --batch --passphrase-file "$ENCRYPTION_KEY_FILE" \
    > "$BACKUP_DIR/db_$DATE.sql.gz.gpg"

# Verify backup was created
if [ -f "$BACKUP_DIR/db_$DATE.sql.gz.gpg" ]; then
    echo "Backup created successfully: db_$DATE.sql.gz.gpg"
    # Remove backups older than 30 days
    find $BACKUP_DIR -name "*.gpg" -mtime +30 -delete
else
    echo "Backup failed!"
    exit 1
fi
```

Also implement MinIO backup in `backup-storage.sh` using `mc mirror` with encryption.

---

#### Task #12: Add Document Hash Verification on Retrieval
**Effort:** Medium
**Status:** Pending

1. **Add verification method to DocumentService.php:**
```php
public function verifyDocumentIntegrity(Document $document): bool
{
    $storedHash = $document->file_hash;

    if (!$storedHash) {
        return false; // No hash to verify
    }

    $filePath = Storage::disk('minio')->path($document->file_path);
    $currentHash = hash_file('sha256', $filePath);

    return hash_equals($storedHash, $currentHash);
}
```

2. **Add verification endpoint** in DocumentController:
```php
public function verifyIntegrity($id)
{
    $document = Document::findOrFail($id);

    if (auth()->user()->cannot('view', $document)) {
        abort(403);
    }

    $isValid = $this->documentService->verifyDocumentIntegrity($document);

    return response()->json([
        'document_id' => $document->id,
        'integrity_valid' => $isValid,
        'file_hash' => $document->file_hash,
        'verified_at' => now()->toIso8601String(),
    ]);
}
```

3. **Add automatic verification** before evidence package generation

---

#### Task #13: Complete Audit Logging Coverage
**Effort:** Medium
**Status:** Pending

Add AuditService logging for:

1. **DocumentController.php:**
   - `store()` - Log document upload
   - `destroy()` - Log document deletion
   - `bulkDestroy()` - Log bulk deletions
   - `send()` - Log document sent for signing

2. **SignatureController.php:**
   - `reject()` (line 225) - Log signer rejection/decline

3. **TemplateController.php:**
   - Template rejection events

4. **AuthController.php:**
   - `logout()` - Log user logout
   - Failed login attempts

5. **UserController.php:**
   - Password changes
   - Role changes
   - MFA enable/disable

Example implementation:
```php
$this->auditService->log(
    $request->user(),
    'document_deleted',
    'document',
    $document->id,
    ['title' => $document->title, 'status' => $document->status]
);
```

Set audit log retention to 7 years for compliance.

---

#### Task #14: Standardize Error Handling Across Controllers
**Effort:** Medium
**Status:** Pending

1. **Fix EvidencePackageController.php** (lines 62-67, 94-98):
```php
// Change from:
return response()->json([
    'message' => 'Failed to generate evidence package',
    'error' => $e->getMessage(),  // EXPOSED
], 500);

// To:
$message = app()->isProduction()
    ? 'Failed to generate evidence package'
    : 'Failed to generate evidence package: ' . $e->getMessage();

Log::error('Evidence package generation failed', [
    'document_id' => $id,
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString()
]);

return response()->json(['message' => $message], 500);
```

2. **Create centralized exception handler** in `bootstrap/app.php`:
```php
$exceptions->render(function (Throwable $e, Request $request) {
    if ($request->is('api/*')) {
        $message = app()->isProduction()
            ? 'An error occurred'
            : $e->getMessage();

        return response()->json(['message' => $message], 500);
    }
});
```

3. **Audit all controllers** for consistent error handling pattern

---

### MEDIUM Priority (Should fix)

#### Task #15: Restrict CORS Configuration
**Effort:** Low
**Status:** Pending

**File:** `backend/config/cors.php`

```php
// Line 20 - From:
'allowed_methods' => ['*'],
// To:
'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

// Line 26 - From:
'allowed_headers' => ['*'],
// To:
'allowed_headers' => [
    'Accept',
    'Authorization',
    'Content-Type',
    'X-Requested-With',
    'X-CSRF-TOKEN',
    'X-Socket-Id',
],

// Line 30 - From:
'max_age' => 0,
// To:
'max_age' => 86400, // Cache preflight for 24 hours
```

Ensure `allowed_origins` is properly restricted in production:
```
CORS_ALLOWED_ORIGINS=https://esign.sadcpf.org,https://app.sadcpf.org
```

---

#### Task #16: Add Pagination Limits to List Endpoints
**Effort:** Low
**Status:** Pending

1. **DocumentController.php** (line 63):
```php
$limit = min((int) $request->input('limit', 10), 100); // Max 100
```

2. **DocumentActivityController.php** (line 14):
```php
$limit = min((int) $request->input('limit', 10), 100);
```

3. **UserController.php** (line 16):
```php
$limit = min((int) $request->input('limit', 20), 100);
return response()->json(User::with('role')->paginate($limit));
```

4. **Add rate limiting to health endpoint** (routes/api.php line 21):
```php
Route::get('/health', [HealthController::class, 'index'])
    ->middleware('throttle:10,1');
```

---

#### Task #17: Upgrade Cryptographic Settings
**Effort:** Low
**Status:** Pending

1. **Upgrade RSA key size** in CertificateService.php (line 61):
```php
'private_key_bits' => 4096, // was 2048
```

2. **Change SHA1 to SHA256** in AppServiceProvider.php (line 30):
```php
hash('sha256', $notifiable->getEmailForVerification())
```

3. **Hash MFA OTP in cache** in MfaController.php (line 36):
```php
Cache::put('mfa:' . $user->id, hash('sha256', $code), 300);
```

4. **Ensure SESSION_ENCRYPT=true** in production

---

#### Task #18: Implement Single-Use Magic Links
**Effort:** Medium
**Status:** Pending

1. **Create magic_link_uses table:**
```php
Schema::create('magic_link_uses', function (Blueprint $table) {
    $table->id();
    $table->string('signature_hash')->unique();
    $table->timestamp('used_at');
});
```

2. **Update MagicLinkController:**
```php
public function authenticate(Request $request)
{
    if (!$request->hasValidSignature()) {
        return response()->json(['message' => 'Invalid or expired link'], 401);
    }

    $signatureHash = hash('sha256', $request->fullUrl());

    if (MagicLinkUse::where('signature_hash', $signatureHash)->exists()) {
        return response()->json(['message' => 'This link has already been used'], 401);
    }

    MagicLinkUse::create([
        'signature_hash' => $signatureHash,
        'used_at' => now(),
    ]);

    // ... continue authentication
}
```

3. **Add cleanup job** to remove old entries

---

#### Task #19: Add Delegation Boundaries and Limits
**Effort:** Medium
**Status:** Pending

**File:** `backend/app/Http/Controllers/DelegationController.php`

```php
public function store(Request $request)
{
    $validated = $request->validate([
        'delegate_email' => 'required|email|exists:users,email',
        'starts_at' => 'required|date|after_or_equal:today',
        'ends_at' => 'required|date|after:starts_at|before:' . now()->addMonths(6)->toDateString(),
        'reason' => 'required|string|max:500',
    ]);

    $delegate = User::where('email', $validated['delegate_email'])->firstOrFail();
    $delegator = $request->user();

    if ($delegate->id === $delegator->id) {
        return response()->json(['message' => 'Cannot delegate to yourself'], 400);
    }

    // Check departmental boundary
    if ($delegator->department_id && $delegate->department_id !== $delegator->department_id) {
        return response()->json([
            'message' => 'Can only delegate to users in your department'
        ], 403);
    }

    // Limit concurrent delegations
    $activeDelegations = Delegation::where('delegator_id', $delegator->id)
        ->where('ends_at', '>', now())
        ->count();

    if ($activeDelegations >= 3) {
        return response()->json([
            'message' => 'Maximum 3 active delegations allowed'
        ], 422);
    }

    // ... continue creation
}
```

---

#### Task #20: Protect System Logs from Exposure
**Effort:** Low
**Status:** Pending

**File:** `backend/app/Http/Controllers/SystemLogController.php`

```php
public function index()
{
    if (!auth()->user()->hasPermission('view_logs')) {
        abort(403);
    }

    $logPath = storage_path('logs/laravel.log');

    if (!file_exists($logPath)) {
        return response()->json(['logs' => '']);
    }

    $content = file_get_contents($logPath);
    $content = $this->sanitizeLogs($content);

    return response()->json(['logs' => $content]);
}

private function sanitizeLogs(string $content): string
{
    $patterns = [
        '/password["\s:=]+[^\s"]+/i' => 'password: [REDACTED]',
        '/token["\s:=]+[^\s"]+/i' => 'token: [REDACTED]',
        '/secret["\s:=]+[^\s"]+/i' => 'secret: [REDACTED]',
        '/key["\s:=]+[a-zA-Z0-9+\/=]{20,}/i' => 'key: [REDACTED]',
        '/Bearer\s+[^\s]+/' => 'Bearer [REDACTED]',
    ];

    foreach ($patterns as $pattern => $replacement) {
        $content = preg_replace($pattern, $replacement, $content);
    }

    return $content;
}
```

---

## Recommended Execution Order

### Phase 1 - Immediate (Day 1)
- Task #1: Rotate secrets (prevents active exploitation)
- Task #2: Disable debug mode
- Task #3: Fix token expiration
- Task #4: Fix OTP vulnerabilities

### Phase 2 - Short-term (Days 2-3)
- Task #6: Protect signed documents
- Task #7: Fix IDOR/authorization
- Task #8: Fix SQL injection
- Task #14: Standardize error handling

### Phase 3 - Medium-term (Week 1)
- Task #5: File upload security
- Task #9: Account lockout
- Task #10: HTTPS/TLS
- Task #11: Encrypted backups
- Task #12: Hash verification
- Task #13: Audit logging

### Phase 4 - Hardening (Week 2)
- Tasks #15-20: Medium priority items

---

## Production Security Sign-Off Template

### Application Information
- **Application Name:** eSign
- **Version:** _______________
- **Environment:** Production
- **Release Date:** _______________

### Security Validation Summary
- [ ] Threat Modeling Completed
- [ ] Automated Security Tests Passed
- [ ] Manual Review Completed

### Findings Summary
- Critical Issues: ___ (must be 0)
- High Issues: ___ (must be 0)
- Medium Issues: ___
- Accepted Risks Documented: [ ] Yes [ ] No

### Compliance Confirmation
- [ ] OWASP ASVS Compliant
- [ ] NIST Controls Compliant
- [ ] ISO 27001 Controls Compliant

### Final Decision
- [ ] Approved for Production
- [ ] Conditional Approval (Mitigations Required)
- [ ] Rejected - Security Risks Unacceptable

### Sign-Off
| Role | Name | Signature | Date |
|------|------|-----------|------|
| Security Lead | | | |
| Engineering Lead | | | |
| Product Owner | | | |

---

## Document History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-01-31 | Security Audit | Initial audit report |
