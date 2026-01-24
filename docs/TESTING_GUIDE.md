# eSign Platform - Testing Guide

## Overview


This guide provides comprehensive testing scenarios for validating the eSign platform before production deployment. For higher-level objectives, scope, and governance, please refer to the [Test Strategy Document](./TEST_STRATEGY.md).


---

## Test Environment Setup

### Prerequisites

```bash
# Start test environment
docker-compose up -d

# Seed test data
docker-compose exec app php artisan db:seed --class=TestDataSeeder

# Create test users
docker-compose exec app php artisan tinker
>>> User::factory()->create(['email' => 'initiator@test.com', 'role' => 'initiator'])
>>> User::factory()->create(['email' => 'signer1@test.com', 'role' => 'signer'])
>>> User::factory()->create(['email' => 'signer2@test.com', 'role' => 'signer'])
```

---

## Test Scenarios

### Scenario 1: SIMPLE Signature Flow

**Objective:** Validate basic signature flow with email verification only

**Preconditions:**
- User logged in as initiator@test.com
- Test PDF document ready

**Steps:**

1. **Upload Document**
   - [ ] Navigate to Dashboard
   - [ ] Click "Upload Document"
   - [ ] Enter title: "Test Agreement"
   - [ ] Upload `test_documents/simple_agreement.pdf`
   - [ ] Verify SignatureLevelSelector displays
   - [ ] Select **SIMPLE** level
   - [ ] Verify description shows "Email verification only"
   - [ ] Click "Continue"

2. **expected Result:** Step 2 (Add Signers) shown

3. **Add Signer**
   - [ ] Enter Name: "John Doe"
   - [ ] Enter Email: signer1@test.com
   - [ ] Leave Role empty
   - [ ] Sequential Signing: OFF
   - [ ] Click "Continue"

4. **Expected Result:** Step 3 (Send) shown

5. **Send Document**
   - [ ] Verify document title displayed
   - [ ] Verify 1 signer listed
   - [ ] Expiration: 30 days (default)
   - [ ] Click "Send for Signing"

6. **Expected Result:** 
   - Success message displayed
   - Redirected to dashboard
   - Document status = "PENDING"

7. **Check Email (signer1@test.com)**
   - [ ] Open Mailpit: http://localhost:8025
   - [ ] Verify email received
   - [ ] Subject: "Please sign: Test Agreement"
   - [ ] Body contains document link

8. **Sign Document**
   - [ ] Click signing link in email
   - [ ] Verify document loads
   - [ ] Verify only email verification shown (no OTP)
   - [ ] Click "I Agree to Sign"
   - [ ] Draw signature
   - [ ] Click "Sign Document"

9. **Expected Result:**
   - Confirmation page shown
   - Confirmation email received
   - Document status = "COMPLETED"

10. **Verify Trust Score**
    - [ ] Log in as initiator@test.com
    - [ ] Open document details
    - [ ] Trust score displayed: ~60-70
    - [ ] Score breakdown shows Simple level

11. **Generate Evidence Package**
    - [ ] Click "Generate Evidence Package"
    - [ ] Wait for generation (~5 seconds)
    - [ ] Click "Download Evidence Package"
    - [ ] Open PDF

12. **Verify Evidence PDF Contains:**
    - [ ] Cover page with trust score
    - [ ] Document summary with hash
    - [ ] Signer details with IP address
    - [ ] Email verification entry
    - [ ] Audit trail with timestamps

**Pass Criteria:** All checkboxes completed without errors

---

### Scenario 2: ADVANCED Signature Flow

**Objective:** Validate OTP verification for advanced signatures

**Steps:**

1. **Upload Document**
   - [ ] Select **ADVANCED** signature level
   - [ ] Verify description shows "Email + OTP verification"
   - [ ] Upload document and add signer
   - [ ] Send for signing

2. **Sign with OTP**
   - [ ] Click signing link
   - [ ] Complete email verification
   - [ ] **OTP Modal appears**
   - [ ] Verify 6-digit input field shown
   - [ ] Verify 5:00 countdown timer displayed
   - [ ] Check email for OTP code
   - [ ] Enter OTP code
   - [ ] Verify code accepted

3. **Test OTP Validation**
   - [ ] Resend document to new signer
   - [ ] Enter wrong code
   - [ ] Verify error: "Invalid code. 2 attempts remaining"
   - [ ] Enter wrong code again
   - [ ] Verify error: "Invalid code. 1 attempt remaining"
   - [ ] Enter correct code
   - [ ] Verify success

4. **Test OTP Expiry**
   - [ ] Wait 5 minutes (or modify code to 10 seconds for testing)
   - [ ] Try to enter code
   - [ ] Verify error: "Code expired. Please request a new one"
   - [ ] Click "Resend"
   - [ ] Verify new code sent
   - [ ] Enter new code
   - [ ] Verify success

5. **Verify Trust Score**
   - [ ] Trust score: ~80-85
   - [ ] Breakdown shows Email: 100, OTP: 100

**Pass Criteria:** OTP flow works correctly with validation and expiry

---

### Scenario 3: QUALIFIED Signature Flow

**Objective:** Validate all verification methods for qualified signatures

**Steps:**

1. **Upload Document**
   - [ ] Select **QUALIFIED** signature level
   - [ ] Verify description shows all verification methods
   - [ ] Upload and send

2. **Complete All Verifications**
   - [ ] Email verification ✅
   - [ ] OTP verification ✅
   - [ ] Device fingerprint captured automatically ✅

3. **Verify Evidence Package**
   - [ ] Generate evidence package
   - [ ] Download PDF
   - [ ] Verify contains:
     - [ ] Email verification entry
     - [ ] OTP verification entry
     - [ ] Device fingerprint data
     - [ ] IP geolocation (city, country)

4. **Verify Trust Score**
   - [ ] Trust score: ~95-100
   - [ ] All components at 100

**Pass Criteria:** Maximum trust score achieved with all verifications

---

### Scenario 4: Template Flow

**Objective:** Validate AI template suggestions and application

**Steps:**

1. **Create Template**
   - [ ] Navigate to Templates
   - [ ] Click "Create Template"
   - [ ] Name: "NDA Template"
   - [ ] Upload NDA document
   - [ ] Define roles: "Discloser", "Recipient"
   - [ ] Save template

2. **Upload Similar Document**
   - [ ] Upload another NDA document
   - [ ] Wait for AI analysis
   - [ ] Verify template suggestions appear
   - [ ] Verify "NDA Template" is suggested
   - [ ] Confidence score >= 70%

3. **Apply Template**
   - [ ] Click "Apply Template"
   - [ ] Verify roles auto-populated
   - [ ] Adjust signers if needed
   - [ ] Send document

**Pass Criteria:** Template suggestion accuracy >= 80%

---

### Scenario 5: Real-Time Notifications

**Objective:** Validate WebSocket real-time updates

**Setup:**
- Open two browser windows
- Window 1: Logged in as initiator
- Window 2: Logged in as signer

**Steps:**

1. **Send Document (Window 1)**
   - [ ] Upload and send document to signer

2. **Check Notification (Window 2)**
   - [ ] Verify notification appears immediately
   - [ ] Notification shows: "New document to sign"
   - [ ] Document appears in "Pending" list

3. **Sign Document (Window 2)**
   - [ ] Sign the document

4. **Check Update (Window 1)**
   - [ ] Verify notification appears: "Document signed"
   - [ ] Document status updates to "COMPLETED"
   - [ ] No page refresh required

**Pass Criteria:** Notifications appear within 2 seconds

---

### Scenario 6: Mobile Responsiveness

**Objective:** Validate mobile experience

**Devices to Test:**
- [ ] iPhone (Safari)
- [ ] Android (Chrome)
- [ ] iPad (Safari)

**Steps:**

1. **Upload Flow**
   - [ ] Signature level selector displays correctly
   - [ ] File upload works on mobile
   - [ ] All buttons are touch-friendly (min 44px)

2. **Signing Flow**
   - [ ] Document renders on small screen
   - [ ] Signature pad works with touch
   - [ ] OTP input keyboard-friendly

3. **Dashboard**
   - [ ] Cards stack vertically
   - [ ] FAB (Floating Action Button) visible
   - [ ] Navigation accessible

**Pass Criteria:** All features work on mobile without horizontal scrolling

---

### Scenario 7: Accessibility

**Objective:** Validate WCAG 2.1 AA compliance

**Tools:**
- WAVE browser extension
- Screen reader (NVDA/JAWS)
- Keyboard only (no mouse)

**Steps:**

1. **Keyboard Navigation**
   - [ ] Tab through upload form
   - [ ] All fields reachable
   - [ ] Focus visible (:focus-visible)
   - [ ] Skip navigation link works
   - [ ] Esc closes modals

2. **Screen Reader**
   - [ ] Form labels announced
   - [ ] Button purposes clear
   - [ ] Error messages announced (live region)
   - [ ] Status updates announced

3. **WAVE Checker**
   - [ ] No errors
   - [ ] All images have alt text
   - [ ] Sufficient color contrast
   - [ ] Proper heading hierarchy

**Pass Criteria:** 0 errors from WAVE, full keyboard navigation

---

### Scenario 8: Performance

**Objective:** Validate performance metrics

**Tools:**
- Chrome Lighthouse
- Network throttling

**Steps:**

1. **Lighthouse Audit**
   - [ ] Performance score >= 90
   - [ ] Accessibility score >= 95
   - [ ] Best Practices score >= 90
   - [ ] SEO score >= 90

2. **Load Times**
   - [ ] Initial page load < 2 seconds
   - [ ] API responses < 500ms
   - [ ] Document upload < 5 seconds (10MB file)

3. **Network Resilience**
   - [ ] Enable offline mode
   - [ ] Try to upload document
   - [ ] Verify queued for retry
   - [ ] Re-enable network
   - [ ] Verify auto-retry succeeds

**Pass Criteria:** All performance targets met

---

### Scenario 9: Security

**Objective:** Validate security measures

**Steps:**

1. **Session Timeout**
   - [ ] Log in
   - [ ] Wait 25 minutes (or modify timeout for testing)
   - [ ] Verify warning appears at 5 min remaining
   - [ ] Click "Extend Session"
   - [ ] Verify timeout reset
   - [ ] Wait for full timeout
   - [ ] Verify auto-logout

2. **Authentication**
   - [ ] Try to access /upload without login
   - [ ] Verify redirect to login
   - [ ] Log in
   - [ ] Verify returned to /upload

3. **Authorization**
   - [ ] Log in as Signer
   - [ ] Try to access another user's document
   - [ ] Verify 403 Forbidden

4. **XSS Protection**
   - [ ] Enter `<script>alert('XSS')</script>` in document title
   - [ ] Verify script not executed
   - [ ] Verify displayed as text

**Pass Criteria:** All security measures functional

---

## Automated Tests

### Backend Tests

```bash
# Run all tests
docker-compose exec app php artisan test

# Run specific test suite
docker-compose exec app php artisan test --testsuite=Feature

# Run with coverage
docker-compose exec app php artisan test --coverage --min=70
```

### Frontend Tests

```bash
# Unit tests
cd frontend && npm run test:unit

# E2E tests with Cypress
cd frontend && npm run test:e2e

# Visual regression tests
cd frontend && npm run test:visual
```

---

## Test Data Cleanup

```bash
# Reset database
docker-compose exec app php artisan migrate:fresh --seed

# Clear cache
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan route:clear
```

---

## Bug Reporting Template

```markdown
### Bug Report

**Title:** [Brief description]

**Environment:**
- Browser: Chrome 120
- OS: Windows 11
- User Role: Initiator

**Steps to Reproduce:**
1. Navigate to /upload
2. Select ADVANCED signature level
3. Click Continue

**Expected Behavior:**
Should proceed to Step 2

**Actual Behavior:**
Error message displayed

**Screenshots:**
[Attach screenshot]

**Console Errors:**
[Paste console errors]

**Severity:**
- [ ] Critical (blocks release)
- [ ] High (major feature broken)
- [ ] Medium (workaround available)
- [ ] Low (cosmetic)
```

---

## Test Completion Checklist

### Functional Tests
- [ ] SIMPLE signature flow
- [ ] ADVANCED signature flow
- [ ] QUALIFIED signature flow
- [ ] Template creation and application
- [ ] Real-time notifications
- [ ] Evidence package generation

### Non-Functional Tests
- [ ] Mobile responsiveness (iOS + Android)
- [ ] Accessibility (WCAG 2.1 AA)
- [ ] Performance (Lighthouse >= 90)
- [ ] Security (session timeout, XSS protection)
- [ ] Browser compatibility (Chrome, Firefox, Safari, Edge)

### Integration Tests
- [ ] Email delivery (SMTP)
- [ ] WebSocket connection (Reverb)
- [ ] Storage upload (MinIO/S3)
- [ ] Database transactions (PostgreSQL)
- [ ] Cache operations (Redis)

---

**Testing Complete! Ready for UAT** ✅
