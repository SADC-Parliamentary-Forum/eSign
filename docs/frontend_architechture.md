# Frontend Requirements Document – E‑Signature Platform

## 1. Purpose

Define comprehensive **frontend functional and non‑functional requirements** for a modern, secure, intuitive **general‑purpose e‑signature platform** (not limited to contracts). This document governs Web and Mobile frontends and aligns with the backend design, workflow engine, and template system.

---

## 2. Supported Clients

* **Web App**: Modern browsers (Chrome, Edge, Firefox, Safari)
* **Mobile App**: Android & iOS (native or Flutter/React Native)

Shared design language, RBAC enforcement, and API contracts across platforms.

---

## 3. Authentication & Session Management

### 3.1 Login & Access

* Token‑based authentication (OAuth2 / OpenID Connect)
* Login via:

  * Email + password
  * MFA (OTP / Authenticator)
* Session persistence:

  * Web: secure HTTP‑only cookies
  * Mobile: secure keychain / keystore

### 3.2 Account Creation (Signer Flow)

* Invited signer without account:

  * Receives secure email link
  * Forced account creation before signing
  * Must create or upload signature & initials

---

## 4. Dashboard & Navigation

### 4.1 Primary Dashboards

**Initiator Dashboard**

* Draft documents
* Sent for signature
* Completed
* Declined / Expired

**Signer Dashboard**

* Documents awaiting my action
* Completed documents

### 4.2 Global UI Elements

* Notifications bell (real‑time + unread count)
* Profile & saved signatures
* Role‑aware menus (RBAC‑controlled visibility)

---

## 5. Document Upload, Conversion & Preview (Core Requirement)

### 5.1 Upload & Conversion

* Supported input formats:

  * PDF (native)
  * DOCX / DOC → **auto-converted to PDF on upload**
* Conversion is synchronous for small files and async with progress indicator for large files
* Conversion failures are surfaced clearly with retry options

### 5.2 Immediate Preview Mode (Default)

After upload or conversion:

* Document **automatically opens in Preview Mode**
* Preview Mode is the **default and mandatory first step**
* User can:

  * Scroll, zoom, navigate pages
  * Review document content

❗ **Key Principle**:

> The user does not choose a separate “prepare” step. Preparation is **embedded directly into the preview experience** to reduce friction.

---

## 6. Interactive Signing Layout (Embedded Workflow)

### 6.1 Assign Signers First

At the top of the preview screen:

* User adds one or more signers by:

  * Email address
  * Optional role/label (e.g. Approver, Witness)
* System indicates:

  * Existing users (account exists)
  * New users (account will be created on invite)

### 6.2 Draw-to-Place Fields (Primary UX)

Once at least one signer is added:

* User selects a signer
* User **draws a rectangle directly on the PDF** to indicate where an action is required
* On mouse/touch release, user chooses the field type:

  * Signature
  * Initials
  * Date (auto-filled at sign time)

This removes drag‑and‑drop complexity and mirrors natural human behavior.

### 6.3 Field Rules

* Every drawn field:

  * Must belong to a signer
  * Is visually color-coded per signer
* Fields are editable until submission
* No submission allowed if:

  * A signer has no required fields
  * A required field has no assigned signer

---

## 7. Templates (Frontend Behavior)

### 7.1 Template Creation

* User defines:

  * Field positions
  * Field types
  * Signer roles (Role A, Role B, etc.)
* Save as template

### 7.2 Template Application

* When uploading a similar document:

  * Select template
  * Fields auto‑populate
  * User maps actual signers to template roles

### 7.3 Template Restrictions

* Only **approved templates** usable in production
* Visual badge:

  * Draft
  * Approved
  * Restricted (financial threshold)

---

## 8. AI‑Assisted Template Matching (UI)

* On document upload:

  * AI suggests best‑fit templates
  * Confidence score shown
* User can:

  * Accept suggestion
  * Override manually

⚠️ AI suggestions are **non‑binding** and always user‑confirmed.

---

## 9. Submission, Notification & Signing Workflow

### 9.1 Submit for Action

When layout is complete:

* User clicks **Submit for Signing**
* System performs frontend validation:

  * All signers have at least one action
  * No overlapping conflicting fields

Document state transitions to **Pending Signatures**.

### 9.2 Signer Notification & Consent

Each designated signer receives:

* Email notification (mandatory)
* In‑app notification (web & mobile)

The notification allows the signer to:

* **View document (read‑only)**
* **Approve (sign)**
* **Reject (with reason)**

Viewing the document is explicitly supported to ensure informed consent.

### 9.3 Signer Experience

When signer opens the document:

* Document opens in guided mode
* Only fields assigned to that signer are active
* Other fields are visible but locked

Signer actions:

* Approve → completes their assigned fields
* Reject → workflow stops or rolls back (configurable)

### 9.4 Multi‑Signer Handling

Supported patterns:

* Parallel signing (default)
* Sequential signing (optional ordering toggle)

Visual indicators show:

* Who has signed
* Who is pending
* Who rejected

---

## 10. Notifications (Frontend)

### 10.1 Channels

* In‑app (real‑time)
* Push (mobile)
* Email (mandatory for legal consent)

### 10.2 Notification Triggers

* Document assigned
* Reminder (configurable)
* Document completed
* Document declined / expired

---

## 11. Audit Trail & Transparency

* Always accessible read‑only panel
* Displays:

  * Signer identity
  * Timestamp
  * IP / device
  * Action taken

---

## 12. Security & Compliance (Frontend Enforced)

* No UI exposure of unauthorized actions
* Screenshot prevention (mobile where possible)
* Watermark on unsigned previews
* Auto‑logout on inactivity

---

## 13. Error Handling & Edge Cases

### 13.1 Common Edge Cases

* Signer opens document on unsupported browser
* Signer declines to sign
* Document updated after partial signing (blocked)
* Template mismatch detected by AI

### 13.2 UX Requirements

* Clear blocking messages
* No silent failures
* Undo only allowed **before sending**

---

## 14. Accessibility & UX Standards

* WCAG 2.1 AA compliance
* Keyboard navigation
* Large‑touch targets (mobile)

---

## 15. Non‑Functional Requirements

* Load PDF < 2 seconds (10MB)
* Offline read‑only cache (mobile)
* Responsive down to 360px width

---

## 16. Future‑Ready Hooks (Frontend)

* Biometric signing
* Video consent capture
* Government e‑ID integration
* Blockchain verification badge

---

## 17. Out of Scope (Explicit)

* Backend validation logic
* Legal policy enforcement rules
* Certificate authority management

---

**Status**: Frontend Requirements – Approved Draft

---

# Appendix A – Frontend ↔ Backend API Flow Specification

## A1. End-to-End Flow (Happy Path)

1. Upload document (PDF/DOCX)
2. Backend converts DOCX → PDF (if needed)
3. Frontend opens PDF in preview mode
4. User adds signers
5. User draws signature/initial/date fields on PDF
6. User submits document for signing
7. Backend locks layout & generates signing workflow
8. Notifications sent to signers
9. Signers view → approve/reject
10. Initiator notified of completion

---

## A2. Core API Interactions

### Upload & Conversion

* POST /documents/upload
* POST /documents/{id}/convert
* GET /documents/{id}/pdf

### Signer & Field Assignment

* POST /documents/{id}/signers
* POST /documents/{id}/fields

### Submission & Workflow

* POST /documents/{id}/submit
* GET /documents/{id}/status

### Signing Actions

* POST /signing/{id}/approve
* POST /signing/{id}/reject

---

# Appendix B – UX Wireframe Specification (Screen = Responsibility)

## B1. Screen 1: Upload & Preview

Purpose: Immediate document trust & context

* PDF viewer (full screen)
* Top bar: Add signers
* Side indicator: Page thumbnails

## B2. Screen 2: Signer Assignment Overlay

Purpose: Define who participates

* Email input
* Role label (optional)
* Status badge (Existing / New)

## B3. Screen 3: Interactive PDF Annotation

Purpose: Define where actions occur

* Click signer → draw rectangle
* Field type selector
* Color-coded signer fields

## B4. Screen 4: Review & Submit

Purpose: Prevent errors

* Summary of signers & actions
* Validation warnings
* Submit button

## B5. Screen 5: Signer Guided Signing View

Purpose: Focused, zero-confusion signing

* Read-only PDF
* Highlight assigned fields only
* Approve / Reject CTA

---

# Appendix C – Signer Consent & Rejection Policy

## C1. Consent Principles

* Signer must be able to read entire document before signing
* Signing action explicitly confirms intent
* Timestamp and device recorded

## C2. Reject Flow

* Rejection requires reason
* On rejection:

  * Workflow pauses or terminates (configurable)
  * Initiator notified immediately

## C3. Partial Completion Rules

* Completed signatures remain immutable
* Document cannot be edited once any signer has approved

---

# Appendix D – Performance & Rendering Strategy

## D1. PDF Rendering

* Progressive page rendering
* Lazy loading for large documents
* Canvas-based rendering for annotations

## D2. Mobile Constraints

* Memory-aware rendering
* Offline read-only cache
* Secure storage for signatures

## D3. SLA Targets

* PDF load < 2s (10MB)
* Annotation latency < 100ms
* Notification dispatch < 5s

---

# Appendix E – Security & Abuse Prevention

* Field tampering prevented post-submit
* All signing actions require fresh token
* Replay protection on signing endpoints
* Screenshot prevention (best-effort mobile)

---

**Document Status**: Frontend Requirements – Extended Architecture Approved

---

# Appendix F – LLM Instruction Set (Usability‑First Signing Workflow)

## F1. Purpose of This Instruction Set

This instruction set governs **how an LLM embedded in the platform must reason, guide users, and enforce UX decisions**. The LLM’s primary responsibility is **usability, clarity, and error prevention**, not feature exposure.

The LLM must always optimize for:

* Minimal user effort
* Predictable behavior
* Clear consent
* Zero ambiguity in signing intent

---

## F2. Core UX Principles the LLM MUST Enforce

1. **Preview First, Always**
   The user must always see the document before being asked to take any action.

2. **One Mental Model**
   Upload → See document → Say who signs → Mark where → Submit.

3. **Direct Manipulation Over Configuration**
   Users draw where to sign instead of filling forms.

4. **Consent Over Speed**
   Signers must be able to view and understand before approving.

5. **No Hidden States**
   Every step must be visible and reversible until submission.

---

## F3. Canonical User Workflow (LLM‑Enforced)

### Step 1: Upload & Open

* When a user uploads a document:

  * If Word → convert to PDF automatically
  * Open document immediately in preview mode

❗ LLM must NEVER suggest navigating away from the preview at this stage.

---

### Step 2: Assign Signers (Who Signs)

* LLM prompts user in plain language:

  > “Who needs to sign this document?”

* User adds one or more signers by email

* LLM must:

  * Confirm each signer visually exists
  * Explain if a signer will need to create an account

❗ LLM must NOT ask about signing order unless user explicitly requests it.

---

### Step 3: Mark Signing Locations (Where They Sign)

* LLM instructs:

  > “Select a signer, then draw a box where they should sign.”

* User draws a square directly on the PDF

* LLM infers intent:

  * Default field type = Signature
  * Optional switch to Initials or Date

LLM must:

* Prevent submission if a signer has no assigned box
* Warn if boxes overlap in a confusing way

---

### Step 4: Submit

* LLM summarizes clearly:

  * Who will sign
  * How many actions per signer

Example confirmation:

> “You are sending this document to 3 people. Each has at least one required signature. Would you like to send it now?”

Only after confirmation:

* Document is submitted
* Layout becomes locked

---

### Step 5: Signer Experience (Approve / Reject / View)

When a signer opens the document, the LLM must:

* Open the document in read-only preview
* Highlight only fields assigned to that signer

The signer is explicitly given three choices:

1. **View** – read without committing
2. **Approve** – complete assigned signatures
3. **Reject** – decline with a reason

❗ LLM must never auto-scroll or rush the signer to sign.

---

## F4. LLM Decision Rules (Hard Constraints)

The LLM must refuse or block actions when:

* User tries to submit without assigning signers
* A signer has no signing location
* User attempts to edit document after submission
* A signer attempts to sign fields not assigned to them

The LLM must always explain *why* an action is blocked.

---

## F5. Language & Tone Rules

The LLM must:

* Use simple, human language
* Avoid legal jargon unless explicitly requested
* Never mention backend concepts (tokens, APIs, workflows)

Bad:

> “Please configure signer roles and signature metadata.”

Good:

> “Add the people who need to sign, then show them where to sign.”

---

## F6. Error Prevention & Recovery

LLM responsibilities:

* Detect hesitation or repeated undo actions
* Offer gentle guidance (not tutorials)
* Always allow the user to:

  * Go back before submission
  * Review everything before sending

---

## F7. Non‑Goals (LLM Must Avoid)

The LLM must NOT:

* Expose advanced options by default
* Ask unnecessary setup questions
* Introduce templates, workflows, or AI features unless relevant

---

## F8. Success Definition (UX)

A first‑time user should be able to:

* Upload a document
* Assign signers
* Mark signature locations
* Send for signing

**Without training, documentation, or assistance**.

---

**Instruction Set Status**: Approved – Usability‑First LLM Behavior

---

# Appendix G – Formal Workflow State Machine (Authoritative)

## G1. Purpose

This state machine defines the **single source of truth** for document lifecycle behavior. Frontend, backend, and LLM behavior MUST conform to this model.

---

## G2. Document States

1. **Draft**

* Document uploaded
* PDF generated
* Preview available
* Signers and fields editable

2. **Prepared**

* Signers assigned
* All required fields placed
* Ready for submission

3. **Pending Signatures**

* Document submitted
* Layout locked
* Notifications sent

4. **Partially Signed**

* One or more signers approved
* Remaining signers pending

5. **Completed**

* All required signers approved
* Document sealed

6. **Rejected**

* One signer rejected
* Workflow halted

7. **Expired** (optional)

* Signing window elapsed

---

## G3. Allowed Transitions

* Draft → Prepared
* Prepared → Pending Signatures
* Pending Signatures → Partially Signed
* Partially Signed → Completed
* Pending Signatures → Rejected
* Partially Signed → Rejected
* Pending Signatures → Expired

❌ No backward transitions once submission occurs.

---

## G4. Immutability Rules

* Once in Pending Signatures:

  * PDF content locked
  * Field layout locked
  * Signers locked

---

# Appendix H – Backend Enforcement Rules (Mirrors LLM UX)

## H1. Core Enforcement Principles

Backend must enforce the same constraints as the LLM. The LLM is advisory; the backend is authoritative.

---

## H2. Submission Validation Rules

Backend MUST reject submission if:

* No signers assigned
* Any signer has zero required fields
* Any required field has no signer

---

## H3. Signing Rules

* Signer may only sign assigned fields
* Signing requires fresh auth token
* Signing auto-applies timestamp

---

## H4. Rejection Rules

* Rejection requires reason
* Rejection immediately halts workflow

---

# Appendix I – Template Reuse Aligned to Draw-Box UX

## I1. Template Philosophy

Templates exist to **save time, not change behavior**.

Templates store:

* Relative field positions
* Field types
* Role placeholders (not real users)

---

## I2. Applying Templates

* User uploads document
* System suggests templates (AI-assisted)
* User confirms template
* User maps real people to template roles

Fields appear instantly on the PDF.

---

## I3. Governance

* Templates require approval before reuse
* Templates may be restricted by document type or value

---

# Appendix J – Audit & Legal Admissibility Matrix

## J1. Captured Evidence Per Action

| Action   | Evidence Captured       |
| -------- | ----------------------- |
| Upload   | User, time, IP          |
| Submit   | Hash, layout checksum   |
| View     | User, timestamp         |
| Approve  | Signature, device, time |
| Reject   | Reason, user, time      |
| Complete | Final hash              |

---

## J2. Legal Posture

* Signature intent explicitly confirmed
* Full audit trail immutable
* Document integrity verifiable

---

**System Architecture Status**: Workflow, Enforcement & Compliance Defined

---

# Appendix K – Core Database Schema (Logical ERD)

## K1. Design Principles

* Immutable audit records
* Separation of document, layout, and signature data
* Support for multi-signer, multi-action documents

---

## K2. Core Tables (Logical)

### documents

* id (UUID)
* title
* original_filename
* pdf_hash
* status (draft, prepared, pending, partial, completed, rejected, expired)
* created_by
* created_at

### document_files

* id
* document_id
* storage_url
* file_type (pdf)
* checksum

### signers

* id
* document_id
* user_id (nullable)
* email
* role_label
* status (pending, signed, rejected)

### fields

* id
* document_id
* signer_id
* page_number
* x, y, width, height
* field_type (signature, initials, date)
* required

### signatures

* id
* field_id
* signer_id
* signature_data_ref
* signed_at
* device_fingerprint

### audit_log

* id
* document_id
* actor_id
* action
* metadata (JSON)
* timestamp

---

# Appendix L – OpenAPI Contract (High-Level)

## L1. Authentication

* POST /auth/login
* POST /auth/refresh

---

## L2. Document Lifecycle

* POST /documents/upload
* GET /documents/{id}
* POST /documents/{id}/submit

---

## L3. Signer & Field APIs

* POST /documents/{id}/signers
* POST /documents/{id}/fields

---

## L4. Signing APIs

* POST /signing/{documentId}/approve
* POST /signing/{documentId}/reject

---

# Appendix M – Deployment & Infrastructure Model

## M1. Architecture Overview

* Frontend (Web & Mobile)
* API Gateway
* Auth Service
* Document Service
* Workflow Engine
* Notification Service
* Audit Service

All services containerized (Docker).

---

## M2. Environment Strategy

* Dev
* UAT
* Production

---

## M3. Resilience

* Stateless APIs
* Object storage for documents
* Database backups
* Horizontal scaling

---

# Appendix N – Sprint Plan (Execution-Ready)

## Sprint 1 – Core Flow

* Upload & preview
* PDF conversion
* Draw-box field placement

## Sprint 2 – Signer & Workflow

* Multi-signer support
* Approve / reject flow
* Notifications

## Sprint 3 – Audit & Templates

* Audit trail
* Template reuse
* AI template suggestion

## Sprint 4 – Hardening

* Security
* Performance
* Mobile optimization

---

**System Design Status**: End-to-End Architecture Complete
