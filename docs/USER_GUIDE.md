# eSign Platform - User Guide

## Welcome to eSign Platform

This guide will help you use the eSign platform to upload, sign, and manage electronic signature documents.

---

## Table of Contents

1. [Getting Started](#getting-started)
2. [Uploading Documents](#uploading-documents)
3. [Signing Documents](#signing-documents)
4. [Managing Templates](#managing-templates)
5. [Tracking Document Status](#tracking-document-status)
6. [Understanding Signature Levels](#understanding-signature-levels)
7. [Downloading Evidence Packages](#downloading-evidence-packages)
8. [FAQs](#faqs)

---

## Getting Started

### Creating an Account

1. Navigate to `https://esign.yourdomain.com`
2. Click **Sign Up**
3. Enter your email and create a password
4. Check your email for verification link
5. Click the link to verify your account
6. Log in with your credentials

### Dashboard Overview

After logging in, you'll see your dashboard with:

- **Pending Documents** - Documents waiting for your signature
- **Sent Documents** - Documents you've sent to others
- **Completed Documents** - Fully signed documents
- **Quick Actions** - Upload new document, create template

---

## Uploading Documents

### Step 1: Upload Your Document

1. Click **Upload Document** button
2. Fill in the document title (e.g., "Employment Agreement")
3. Click **Choose File** and select your PDF or DOCX
4. **Choose Signature Level:**
   - **SIMPLE** - Email verification only (internal docs, low-risk)
   - **ADVANCED** - Email + OTP verification (contracts, NDAs)
   - **QUALIFIED** - Email + OTP + Device verification (legal docs, high-value)
5. Click **Continue**

### Step 2: Add Signers

1. Enter signer information:
   - **Name** - Full legal name
   - **Email** - Email address to receive signing link
   - **Role** (optional) - e.g., "Employee", "Manager", "Director"
2. Click **Add Another Signer** if needed
3. Toggle **Sequential Signing** if signers must sign in order
4. Click **Continue**

### Step 3: Send for Signing

1. Review document details and signers
2. Set **Expiration** (default: 30 days)
3. Add a message for signers (optional)
4. Click **Send for Signing**

✅ **Success!** Signers will receive an email with a link to sign.

---

## Signing Documents

### Receiving a Signature Request

You'll receive an email with:
- Document title
- Sender name
- Link to view and sign

### Signing Process

1. Click **Review & Sign** in the email
2. Review the document
3. **Complete Identity Verification** (based on signature level):
   
   **For SIMPLE signatures:**
   - Click email verification link (you're done!)
   
   **For ADVANCED signatures:**
   - Click email verification link
   - Enter 6-digit OTP code sent to your email
   - Code expires in 5 minutes (3 attempts max)
   
   **For QUALIFIED signatures:**
   - Click email verification link
   - Enter 6-digit OTP code
   - Device fingerprint captured automatically

4. **Draw or Type Your Signature:**
   - **Draw** - Use mouse/finger to draw signature
   - **Type** - Type your name (auto-styled)
   - **Upload** - Upload image of signature
5. Click **Sign Document**

✅ **Success!** You'll see a confirmation page and receive a signed copy via email.

---

## Managing Templates

Templates help you reuse common documents.

### Creating a Template

1. Go to **Templates** menu
2. Click **Create Template**
3. **Step 1: Basic Information**
   - Template name (e.g., "NDA Template")
   - Description
   - Category
4. **Step 2: Document Upload**
   - Upload template file (PDF/DOCX)
5. **Step 3: Define Roles**
   - Add roles (e.g., "Employee", "Manager")
   - Set signing order
6. **Step 4: Configure Fields**
   - Add signature fields
   - Add text fields (name, date, etc.)
7. **Step 5: Review & Save**
   - Preview template
   - Click **Save Template**

### Using a Template

1. Click **Upload Document**
2. Upload your document
3. **AI will suggest matching templates** automatically
4. Click **Apply Template** on suggested template
5. Signers and roles will be auto-populated
6. Adjust if needed and send

---

## Tracking Document Status

### Document Statuses

- **DRAFT** - Document uploaded, not sent yet
- **PENDING** - Sent for signing
- **PARTIALLY_SIGNED** - Some signers have signed
- **COMPLETED** - All signers have signed
- **DECLINED** - A signer declined to sign
- **EXPIRED** - Signing deadline passed
- **CANCELLED** - Sender cancelled the document

### Real-Time Updates

The platform updates automatically when:
- A signer views the document
- A signer signs
- Document is completed
- Someone declines

You'll see a notification in the top-right corner and receive an email.

### Workflow Timeline

Click on any document to see:
- Who has signed (✅)
- Who is pending (⏳)
- Timestamps for each action
- Current status

---

## Understanding Signature Levels

### SIMPLE - Basic Security

**Verification:** Email only  
**Use Cases:** Internal documents, low-risk agreements  
**Legal Strength:** Basic  
**Compliance:** ESIGN Act compliant  
**Signing Time:** ~1 minute  

**Best for:**
- Internal approvals
- Team agreements
- Non-binding documents

---

### ADVANCED - Strong Security

**Verification:** Email + OTP (6-digit code)  
**Use Cases:** Contracts, business agreements, NDAs  
**Legal Strength:** Strong  
**Compliance:** eIDAS Article 26 aligned  
**Signing Time:** ~2 minutes  

**Best for:**
- Employment contracts
- Vendor agreements
- Non-disclosure agreements
- Service contracts

---

### QUALIFIED - Maximum Security

**Verification:** Email + OTP + Device fingerprint  
**Use Cases:** Legal documents, financial agreements, government forms  
**Legal Strength:** Strongest (equivalent to handwritten)  
**Compliance:** eIDAS Article 28 aligned  
**Signing Time:** ~3 minutes  

**Best for:**
- Loan agreements
- Property deeds
- Power of attorney
- High-value contracts (>$10K)

---

## Downloading Evidence Packages

Evidence packages provide legal proof of the signing process.

### What's Included

A 6-page PDF containing:
1. **Cover Page** - Trust score, legal notice
2. **Document Summary** - Title, ID, hash, timestamps
3. **Signature Details** - Each signer with IP address, location
4. **Identity Verification** - All verification methods used
5. **Certificate Chain** - Digital certificates
6. **Hash Verification** - SHA-256 proof of integrity
7. **Audit Trail** - Chronological event log

### How to Download

1. Open a **completed** document
2. Scroll to **Evidence Package** section
3. Click **Generate Evidence Package** (first time only)
4. Click **Download Evidence Package** (PDF)

💡 **Tip:** Keep evidence packages for 7 years (recommended retention period).

---

## FAQs

### Can I sign on mobile?

✅ **Yes!** The platform is fully mobile-responsive. You can sign on any smartphone or tablet.

### What file formats are supported?

📄 **PDF** and **DOCX** (Microsoft Word) files are supported.

### How long does a signing link stay valid?

⏰ **30 days by default**, but senders can customize the expiration period (7-90 days).

### Can I cancel a document after sending?

✅ **Yes!** As the sender, you can cancel pending documents from the document details page.

### What happens if I don't receive the OTP code?

1. Check spam/junk folder
2. Wait 60 seconds and click **Resend Code**
3. Contact support if issue persists

### Is my data secure?

🔒 **Absolutely!**
- All data encrypted (TLS 1.3)
- SHA-256 document hashing
- Tamper-proof audit trails
- Encrypted storage
- Regular security audits

### Can I use my own signature?

✅ **Yes!** You can:
- Draw your signature
- Upload an image of your signature
- Type your name (auto-styled)
- Save signatures for reuse

### What's the Trust Score?

🎯 The Trust Score (0-100) indicates the security level of a signed document:
- **80-100** - Excellent (QUALIFIED signature)
- **60-79** - Good (ADVANCED signature)
- **40-59** - Fair (SIMPLE signature)

Higher scores mean stronger legal protection.

### Can I integrate with other systems?

🔌 **Yes!** API documentation is available for developers. Contact your admin for API access.

### What browsers are supported?

✅ **All modern browsers:**
- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

### Is there a mobile app?

📱 **Progressive Web App (PWA)** - You can "install" the website as an app on your phone:
1. Open site in browser
2. Menu → "Add to Home Screen"
3. Icon appears on home screen like a native app

---

## Support

**Need Help?**

- 📧 Email: support@esign.yourdomain.com
- 💬 Live Chat: Available 9 AM - 5 PM (Mon-Fri)
- 📞 Phone: +1-XXX-XXX-XXXX
- 📚 Knowledge Base: https://help.esign.yourdomain.com

**Found a Bug?**

Report bugs via the feedback button (bottom-right corner) or email bugs@esign.yourdomain.com

---

**Happy Signing! 📝✅**
