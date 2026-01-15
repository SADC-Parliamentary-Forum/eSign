# eSign Platform

> **Enterprise-grade, legally defensible e-signature platform for regulated industries**

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Laravel](https://img.shields.io/badge/Laravel-10-red.svg)](https://laravel.com)
[![Vue.js](https://img.shields.io/badge/Vue.js-3-green.svg)](https://vuejs.org)
[![eIDAS Compliant](https://img.shields.io/badge/eIDAS-Compliant-success.svg)](https://eur-lex.europa.eu/legal-content/EN/TXT/?uri=uriserv:OJ.L_.2014.257.01.0073.01.ENG)
[![ESIGN Act](https://img.shields.io/badge/ESIGN%20Act-Compliant-success.svg)](https://www.fdic.gov/resources/supervision-and-examinations/consumer-compliance-examination-manual/documents/5/v-7-1.pdf)

---

## Overview

**eSign Platform** is a complete, production-ready electronic signature solution designed for enterprises in regulated industries including finance, legal, government, and healthcare. Built with security, compliance, and user experience as top priorities.

### Key Highlights

- ✅ **Legally Defensible** - eIDAS Article 26 & 28, ESIGN Act compliant
- ✅ **3-Tier Signature Levels** - SIMPLE, ADVANCED, QUALIFIED
- ✅ **Multi-Factor Verification** - Email, OTP, Device fingerprinting
- ✅ **Forensic Evidence Packages** - Tamper-proof PDF/A-3 audit trails
- ✅ **Real-Time Updates** - WebSocket notifications
- ✅ **Mobile Responsive** - Works on any device
- ✅ **Fully Accessible** - WCAG 2.1 AA compliant
- ✅ **Production Ready** - Docker, CI/CD, monitoring included

---

## Quick Start

### Prerequisites

- Docker & Docker Compose
- Git
- Node.js 18+ (for local development)
- PHP 8.2+ (for local development)

### 1-Minute Setup

```bash
# Clone repository
git clone https://github.com/your-org/esign-platform.git
cd esign-platform

# Start with Docker
docker-compose up -d

# Initialize application
docker-compose exec app composer install
docker-compose exec app php artisan migrate --seed
docker-compose exec app npm install --prefix frontend
docker-compose exec app npm run build --prefix frontend

# Access application
# Frontend: http://localhost:5173
# Backend API: http://localhost:8000
# Mailpit: http://localhost:8025
```

**Default credentials:**
- Email: `admin@example.com`
- Password: `password`

---

## Features

### Core Functionality

- **Document Upload** - PDF, DOCX support with drag-and-drop
- **AI-Powered Templates** - Automatic template suggestions
- **Signature Workflows** - Sequential or parallel signing
- **Real-Time Tracking** - Live status updates via WebSocket
- **Mobile Signing** - Full mobile support with responsive design

### Security & Compliance

- **3 Signature Levels:**
  - **SIMPLE** - Email verification (internal docs, low-risk)
  - **ADVANCED** - Email + OTP (contracts, NDAs)
  - **QUALIFIED** - Email + OTP + Device (legal docs, high-value)

- **Identity Verification:**
  - Email confirmation with 24-hour expiry
  - 6-digit OTP with 5-minute expiry, 3-attempt limit
  - Device fingerprinting with IP geolocation
  - X.509 digital certificates (RSA 2048-bit)

- **Evidence Packages:**
  - 6-page forensic PDF with complete audit trail
  - SHA-256 document hash verification
  - Certificate chain validation
  - Tamper-proof timestamps

---

## Documentation

| Document | Description |
|----------|-------------|
| [DEPLOYMENT.md](DEPLOYMENT.md) | Production deployment guide |
| [docs/STAGING_DEPLOYMENT.md](docs/STAGING_DEPLOYMENT.md) | Staging setup guide |
| [docs/USER_GUIDE.md](docs/USER_GUIDE.md) | End-user documentation |
| [docs/TESTING_GUIDE.md](docs/TESTING_GUIDE.md) | Testing scenarios |
| [.github/workflows/ci-cd.yml](.github/workflows/ci-cd.yml) | CI/CD pipeline |

---

## Deployment

### Staging Deployment (Automated)

```bash
# One-command deployment
chmod +x scripts/deploy-staging.sh
./scripts/deploy-staging.sh
```

### Production Deployment

See [DEPLOYMENT.md](DEPLOYMENT.md) for comprehensive production deployment guide.

---

## Project Statistics

- **Total Files:** 55+ files
- **Lines of Code:** ~7,000
- **Phases Complete:** 10 (Foundation + Legal Defensibility)
- **API Endpoints:** 32+
- **Vue Components:** 18
- **Documentation Pages:** 8+

---

## Support

- 📚 **Documentation:** [docs/](docs/)
- 🐛 **Bug Reports:** [GitHub Issues](https://github.com/your-org/esign-platform/issues)
- 📧 **Email:** support@esign.yourdomain.com

---

## License

This project is licensed under the MIT License - see [LICENSE](LICENSE) file for details.

---

<p align="center">
  <strong>Ready to deploy to staging?</strong><br>
  <a href="docs/STAGING_DEPLOYMENT.md">Deployment Guide</a> • 
  <a href="docs/USER_GUIDE.md">User Guide</a> • 
  <a href="docs/TESTING_GUIDE.md">Testing Guide</a>
</p>
