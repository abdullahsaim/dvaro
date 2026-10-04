<h1 align="center">DVARO</h1>
<p align="center"><strong>Intelligent Fleet &amp; Rental Operations Platform</strong></p>
<p align="center">Multi-tenant SaaS for car rental companies — Laravel 11 · Vue 3 · Inertia.js · PostgreSQL</p>

<p align="center">
  <img alt="Laravel" src="https://img.shields.io/badge/Laravel-11-FF2D20?logo=laravel&logoColor=white">
  <img alt="PHP" src="https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white">
  <img alt="Vue" src="https://img.shields.io/badge/Vue-3-4FC08D?logo=vuedotjs&logoColor=white">
  <img alt="Inertia" src="https://img.shields.io/badge/Inertia.js-9553E9?logo=inertia&logoColor=white">
  <img alt="PostgreSQL" src="https://img.shields.io/badge/PostgreSQL-16-4169E1?logo=postgresql&logoColor=white">
  <img alt="Tailwind" src="https://img.shields.io/badge/Tailwind-3-06B6D4?logo=tailwindcss&logoColor=white">
</p>

---

## Overview

**DVARO** is a multi-tenant SaaS platform that runs the full operational lifecycle of a car
rental business — fleet, customers, rental agreements (including remote e-signing and the full
bond collection/return/refund lifecycle), invoicing, payments, workshop maintenance, reporting, and
notifications — with every tenant fully isolated at the database level.

Built for the Australian market (AUD, Australian financial year) for **DJ Group of Companies Pty Ltd**,
Canning Vale, Western Australia. The platform handles real money, real agreements, and real fleets,
so it is engineered for **accuracy over speed**: append-only financial ledger, immutable agreement
versioning, and hard-blocked plan limits.

Each rental company signs up as a **tenant** and operates inside its own isolated workspace at
`/app/{tenant_slug}/…`. Mechanics, customers, and platform staff each get their own authentication
guard and portal.

---

## Architecture at a glance

| Concern | Approach |
|---|---|
| **Multi-tenancy** | Path-based (`/app/{tenant_slug}/`), row-level isolation via `tenant_id` + a global `TenantScope` on every model |
| **Auth guards** | Separate guards per audience — `tenant`, `mechanic`, `superadmin`, `customer` (never shared) |
| **Code organisation** | Domain modules under `app/Modules/*` — thin controllers, logic in Services / Actions / DTOs |
| **Financial record** | Append-only ledger (immutability enforced in code at 3 layers) — never mutated, only appended |
| **Agreements** | Immutable; every change creates a new version (`parent_agreement_id` lineage), re-sign required |
| **Events** | Every important action fires a domain event; listeners drive invoicing & notifications |
| **Plan limits** | Hard blocks — exceeding a limit throws `PlanLimitExceededException` (403), never a soft warning |
| **Heavy work** | Queued (Redis): PDF generation, notifications, expiry reminders — never run synchronously |
| **i18n** | All UI strings routed through Laravel `__()` + Vue i18n from day one (English in v1) |

> The full engineering contract lives in [`CLAUDE.md`](CLAUDE.md); a granular, per-feature build log
> lives in [`docs/MODULE_STATUS.md`](docs/MODULE_STATUS.md).

---

## Tech stack

| Layer | Technology |
|---|---|
| Backend | Laravel 11, PHP 8.3 |
| Frontend | Vue 3 + Inertia.js + Tailwind CSS |
| Database | PostgreSQL 16 |
| Cache / Queue / Sessions | Redis |
| Roles / Permissions | Spatie Laravel Permission (per-guard) |
| File Storage | AWS S3-compatible (`ap-southeast-2`) |
| PDF Generation | `barryvdh/laravel-dompdf` (pure PHP, queued) |
| QR Codes | `simplesoftwareio/simple-qrcode` (SVG, dependency-free) |
| Backup | `spatie/laravel-backup` → Contabo Object Storage |
| i18n | Laravel Lang + Vue i18n |
| Hosting | Contabo VPS — Apache + PHP-FPM 8.3 + Supervisor |

**Pluggable providers** (selected per tenant, called only through shared interfaces — never SDKs directly):

- **SMS** — ClickSend / Cellcast · **WhatsApp** — ClickSend (Meta BSP)
- **Email** — Mailgun / Resend / SMTP
- **Payments** — Stripe / PayPal · **AI** — Groq / Qwen / DeepSeek

---

## Module status

DVARO is built module-by-module, with a session-by-session build log kept in
[`docs/MODULE_STATUS.md`](docs/MODULE_STATUS.md). The table below reflects what is **actually
implemented and verified** in the codebase today — every module in CLAUDE.md's full scope is live
except where noted.

| Module | Status | What works today |
|---|---|---|
| **Multi-tenancy core** | ✅ Built | `TenantScope` global scope, `TenantMiddleware`, `HasTenant` trait, per-tenant isolation verified by tests; 5 fully separate auth guards (tenant, mechanic, superadmin, customer, + web) |
| **SaaS Core** | ✅ Built | Plans & subscriptions, self-registration + onboarding (transactional), Stripe Checkout billing (hosted checkout, webhook activation/cancellation), hard plan-limit enforcement. PayPal still pending |
| **Super Admin Panel** | ✅ Built | Tenant management (suspend/activate/impersonate), plan management, subscription records, platform settings, revenue KPIs. Role-management UI and platform-wide activity logs are still manual/CLI |
| **Fleet Management** | ✅ Built | Vehicle CRUD, 6 statuses with a single sanctioned status-change path, QR code tokens, odometer tracking, registration/insurance/service expiry reminders, plan-enforced vehicle count |
| **Customer Management** | ✅ Built | Customer CRUD, encrypted licence/passport fields, blacklist workflow, identity document uploads (signed-URL access), ledger-backed outstanding balance, full rental history |
| **Customer Portal** | ✅ Built | A fifth, fully isolated guard — customers log in to view invoices, agreements, and track rental status |
| **CRM / Leads** | ✅ Built | Lead intake, public lead capture form (share link, QR, reCAPTCHA-gated), signed intake links, one-click lead → customer conversion |
| **Agreement Engine** | ✅ Built | Create / canvas signature / immutable versioning, per-state terms templates, queued dompdf PDF generation, vehicle-change re-versioning — **plus a public, token-gated remote e-signing link** (no login) with an automatic emailed copy on signing |
| **Rental Lifecycle (Return & Bond)** | ✅ Built | Signing collects the bond and puts the vehicle on the road; closing a rental records the vehicle's return condition, settles the bond (deduction + refund), and returns the vehicle to service — all on an append-only record |
| **Invoice & Finance** | ✅ Built | Append-only ledger, recurring invoice generation, **prorated vehicle-change splits**, late-fee automation, 4 invoice-template layouts, GST handling, the Expenses module, bond ledger kept separate from rental balance |
| **Workshop & Mechanic Portal** | ✅ Built | Dedicated mechanic guard (PIN **or** password), public QR vehicle scan, service logs, parts tracking, labour/total costs, admin read-only workshop view |
| **Notification System** | ✅ Built | Email/SMS/WhatsApp provider interfaces (tenant-selectable), a per-trigger notification matrix, queued listeners, daily expiry-reminder digests |
| **AI Assistant** | ✅ Built | System-help + business-intelligence modes, tenant-restricted, Groq/Qwen/DeepSeek + Log fallback |
| **Reporting & Analytics** | ✅ Built | Revenue, fleet utilisation, overdue, workshop, customer & maintenance reports; Redis cache-aside; queued PDF + Excel export; Australian financial year |
| **Dashboard** | ✅ Built | Needs-attention queue, fleet snapshot, the week ahead, polling refresh |
| **Settings & Customization** | ✅ Built | Company profile, staff, finance, regional, integrations, notification matrix, personal preferences, audit trail |
| **Landing Website + CMS** | ✅ Built | Public marketing site with super-admin-controlled content, FAQ, stat counters, demo request / lead generation |
| **CI/CD pipeline** | 🔜 Planned | Deploys today run manually per [`CLAUDE.md`](CLAUDE.md) → *Server Deployment*; GitHub Actions automation not yet built |

---

## Key engineering guarantees

These are **non-negotiable invariants** the codebase enforces:

- **Ledger is append-only** — entries are never updated or deleted (blocked at instance, model-event,
  and query-builder layers). Reversals are compensating entries.
- **Agreements are immutable** — a change never mutates a row; it creates a new version and triggers re-sign.
- **Customer-after-agreement** — a customer profile is created *from* a signed agreement, never assumed before.
- **Bond is money held in trust, not rental revenue** — bond ledger entries are excluded from the
  customer's rental balance; a return's bond figure is snapshotted onto its own append-only record so
  a later agreement version can never retroactively reinterpret a historical refund.
- **Every query is tenant-scoped** — cross-tenant access returns 404; verified by feature tests.
- **Plan limits are hard blocks** — never soft warnings.
- **Prorated splits sum exactly** — vehicle-change invoice splits always total the original to the cent.
- **No logic in controllers** — controllers validate → call a Service/Action → return a response.

---

## Getting started (local development)

### Requirements
- PHP 8.3, Composer
- Node.js 18+ and npm
- PostgreSQL 16
- Redis

### Setup

```bash
# 1. Install dependencies
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate
# → configure DB (PostgreSQL), Redis, and S3 credentials in .env

# 3. Database (NEVER use migrate:fresh on this project — plain migrate only)
php artisan migrate

# 4. Seed roles required before first use
php artisan db:seed --class=TenantRolesSeeder      # required before first tenant registration
php artisan db:seed --class=MechanicRolesSeeder    # required before first mechanic login
```

### Run

```bash
# App + queue + logs + Vite, all at once
composer dev

# …or individually:
php artisan serve
npm run dev
php artisan queue:work redis --queue=default,notifications,pdf,ai,exports
```

> **Important:** always keep a queue worker running in development — PDF generation, notifications,
> and expiry reminders are all queued.

### Scheduled commands

Run via the Laravel scheduler (`php artisan schedule:work` in dev):

| Command | Schedule | Purpose |
|---|---|---|
| `invoices:generate-recurring` | daily 00:01 | Raise due recurring invoices and advance billing dates |
| `invoices:apply-late-fees` | daily 00:05 | Apply configurable late fees past the grace period |
| `notifications:send-expiry-reminders` | daily 00:10 | 14-day registration / insurance / service / agreement reminders |

---

## Testing

```bash
php artisan test                 # full suite
php artisan test --filter=Workshop
```

Feature tests use `DatabaseTransactions` (never `RefreshDatabase`/`migrate:fresh`). The suite spans
every module — tenant auth & isolation, onboarding rollback, the workshop/mechanic flow, agreement
signing (in-person and public remote e-signing), the rental return/bond-settlement lifecycle, invoicing,
and more — currently **376 passing** (2 pre-existing, unrelated seeder-data failures tracked separately).

---

## Project structure

```
app/
├── Modules/            # Domain modules (SaasCore, SuperAdmin, Fleet, Customer, CRM, Agreement,
│   │                   #   Rental, Invoice, Finance, Workshop, AI, Notification, Reporting, CMS, …)
│   └── <Module>/       # Models, Services, Actions, DTOs, Events, Listeners, Controllers
├── Http/Middleware/    # TenantMiddleware, ResolveTenantForMechanic, guards
└── …
resources/js/
├── Pages/              # Inertia pages: Tenant/, Customer/, Mechanic/, SuperAdmin/, Public/
├── Components/         # Shared Vue components (StatusBadge, …)
└── Layouts/            # AppLayout, MechanicLayout, PublicLayout, …
routes/
├── web.php             # Public landing, auth, public CRM intake & QR scan
├── tenant.php          # Tenant-scoped app routes
├── mechanic.php        # Mechanic portal routes
├── superadmin.php      # Super admin panel routes
├── customer.php        # Customer portal routes
└── api.php             # API v1 (mobile-ready)
```

---

## Deployment

DVARO runs on a **shared Contabo VPS** alongside another client project (Jaral Motors). Deployment
is intentionally conservative — DVARO is added *alongside* the existing stack (its own Apache vhost,
PHP 8.3-FPM, and PostgreSQL), never replacing what is already there.

| Environment | Branch | Domain |
|---|---|---|
| Production | `main` | dvaro.com.au |
| Staging / Demo | `staging` | demo.dvaro.com.au |

See [`CLAUDE.md`](CLAUDE.md) → *Server Deployment* for the full shared-server rules and CI/CD pipeline.

---

## License

Proprietary — © DJ Group of Companies Pty Ltd. All rights reserved.
