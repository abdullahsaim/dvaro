# CLAUDE.md — DVARO
# Intelligent Fleet & Rental Operations Platform
# Multi-Tenant SaaS — Laravel 11 + Vue 3 + Inertia.js
# Version: FINAL

---

## PROJECT OVERVIEW

DVARO is a multi-tenant SaaS platform for car rental companies (Australia market).
Client: DJ Group of Companies Pty Ltd, Canning Vale, Western Australia.
Each rental company is a tenant. Tenants are fully isolated at the database level.
This system handles real money, real agreements, and real fleets. Accuracy over speed.

---

## TECH STACK

| Layer             | Technology                                 |
|-------------------|--------------------------------------------|
| Backend           | Laravel 11, PHP 8.3                        |
| Frontend          | Vue 3 + Inertia.js + Tailwind CSS          |
| Database          | PostgreSQL                                 |
| Cache / Queue     | Redis                                      |
| Auth              | Laravel Sanctum                            |
| Roles/Permissions | Spatie Laravel Permission                  |
| File Storage      | AWS S3-compatible (ap-southeast-2)         |
| PDF Generation    | Queued (spatie/laravel-pdf)                |
| QR Codes          | simplesoftwareio/simple-qrcode             |
| Backup            | spatie/laravel-backup → Contabo Object Storage |
| Real-Time         | Polling (30–60s intervals, Reverb future)  |
| SMS               | ClickSend or Cellcast (tenant selects)     |
| WhatsApp          | ClickSend (Meta BSP)                       |
| Email             | Mailgun, Resend, or SMTP (tenant selects)  |
| Payments          | Stripe + PayPal                            |
| AI                | Groq, Qwen, DeepSeek (tenant selects)      |
| Digital Signature | Canvas-based (custom)                      |
| i18n              | Laravel Lang + Vue i18n (English now, future-ready) |
| Hosting           | Contabo VPS — Apache + PHP-FPM 8.3 + Supervisor (shared with Jaral Motors) |
| CI/CD             | GitHub Actions (staging + production)      |

---

## ENVIRONMENTS

| Environment | Branch    | Server          | Domain                        |
|-------------|-----------|-----------------|-------------------------------|
| Production  | `main`    | Contabo VPS (shared w/ Jaral Motors) | dvaro.com.au          |
| Staging/Demo| `staging` | Same VPS, separate Apache vhost | demo.dvaro.com.au          |

**CI/CD Flow:**
- Push to `staging` → GitHub Actions → auto-deploy to demo vhost (/var/www/dvaro-demo, pre-loaded seed data)
- Push to `main` → GitHub Actions → auto-deploy to production vhost (/var/www/dvaro)
- Pipeline runs: composer install, npm build, migrations, cache clear, queue restart
- Zero-downtime deploy using `php artisan down` + maintenance mode swap
- Demo and production are separate Apache virtual hosts + separate PostgreSQL databases on the same VPS

---

## COMMANDS

```bash
# Development
php artisan serve
npm run dev

# Queue worker (ALWAYS run in dev)
php artisan queue:work redis --queue=default,notifications,pdf,ai,exports

# Tests
php artisan test
php artisan test --filter=ModuleName

# Cache
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Backup
php artisan backup:run
php artisan backup:clean

# Demo seed
php artisan db:seed --class=DemoSeeder
```

---

## FOLDER STRUCTURE

```
app/
├── Modules/
│   ├── SaasCore/           # Tenant mgmt, subscriptions, plans, system settings
│   ├── SuperAdmin/         # Super admin panel, roles, CMS, platform analytics
│   ├── CRM/                # Leads, intake forms, secure links
│   ├── Customer/           # Profiles, ledger, blacklist, bond, customer portal
│   ├── Fleet/              # Vehicles, statuses, utilisation, QR codes
│   ├── Rental/             # Bookings, lifecycle, return inspection
│   ├── Agreement/          # Agreements, consent/return/breach, sig, PDF, templates
│   ├── Invoice/            # Invoice engine, prorated billing, late fees, ledger
│   ├── Finance/            # Expense tracking, financial summaries, bond refunds
│   ├── Workshop/           # QR workflow, mechanic portal, parts, labour costs
│   ├── AI/                 # AI assistant, provider switching, tenant-restricted
│   ├── Notification/       # Email, SMS, WhatsApp dispatching
│   ├── Reporting/          # Analytics, PDF export, Excel export
│   └── CMS/                # Landing page content management (super admin controlled)
├── Services/
├── Actions/
├── DTOs/
├── Events/
├── Listeners/
└── Http/
    ├── Controllers/
    └── Middleware/

resources/
└── js/
    ├── Pages/
    │   ├── SuperAdmin/     # Super admin panel pages
    │   ├── Tenant/         # Tenant app pages
    │   ├── Customer/       # Customer portal pages
    │   ├── Mechanic/       # Mechanic portal pages
    │   └── Public/         # Landing website pages
    ├── Components/
    └── Layouts/
        ├── AppLayout.vue
        ├── SuperAdminLayout.vue
        ├── CustomerLayout.vue
        ├── MechanicLayout.vue
        └── PublicLayout.vue

routes/
├── web.php                 # Public landing + auth routes
├── api.php                 # API v1 (mobile-ready)
├── tenant.php              # All tenant-scoped routes
├── superadmin.php          # Super admin panel routes
├── customer.php            # Customer portal routes
└── mechanic.php            # Mechanic portal routes
```

---

## TENANT ROUTING

**Path-based multi-tenancy:**
```
dvaro.com.au/app/{tenant_slug}/dashboard
dvaro.com.au/app/{tenant_slug}/fleet
dvaro.com.au/app/{tenant_slug}/customers
```
TenantMiddleware resolves tenant from `{tenant_slug}` path segment.
Super admin: `dvaro.com.au/superadmin/`
Customer portal: `dvaro.com.au/portal/{tenant_slug}/`
Mechanic portal: `dvaro.com.au/mechanic/{tenant_slug}/`
Public landing: `dvaro.com.au/`

---

## FULL MODULE SCOPE

### SaaS Core
- Company self-registration & onboarding
- Subscription plans (monthly + annual)
- Admin-configured module packages per plan
- Plan limits enforced as HARD BLOCKS:
  - Vehicle count limit
  - Storage quota limit
  - File size limit per upload
  - User/staff count limit
- When limit hit: block action + show upgrade message
- If no paid plan available: downgrade to default free package
- Free trial (time-limited, super admin controls duration)
- Permanent freemium tier (super admin controls)
- Stripe + PayPal for subscription billing
- Self-onboarding with optional manual approval toggle (system setting)

### Super Admin Panel
**Users & Roles inside super admin:**
- Platform Owner (full access)
- Billing Manager (subscriptions, payments, invoices)
- Customer Support (tenant management, support tools)

**Features:**
- All tenants list + management
- Subscription & billing records
- Package / plan management
- Subscription history per tenant
- Platform revenue analytics
- Manual tenant approval (toggle on/off via system settings)
- Tenant impersonation (support access)
- Activity logs across platform
- System settings (global platform configuration)
- CMS (landing page content control)
- Demo data management
- Backup management
- Notification templates management

### System Settings (Super Admin)
Global platform configuration panel:
- Manual tenant approval: on/off
- Free trial: duration, enabled/disabled
- Freemium tier: enabled/disabled
- Default plan on limit breach
- Supported payment gateways: enable/disable globally
- Supported AI providers: enable/disable globally
- Supported SMS providers: enable/disable globally
- Supported email providers: enable/disable globally
- Default currency (AUD)
- Supported currencies list
- Maintenance mode toggle
- Platform-wide notifications
- Storage limits per plan
- File size limits per plan
- Vehicle count limits per plan

### CMS Module (Super Admin Controlled)
Landing page content management:
- Hero section (heading, subheading, CTA text)
- Features section (title, description per feature)
- Pricing section (synced with actual plans)
- About us page content
- Contact details
- Testimonials
- Image replacement (swap images already in design — no layout changes)
- Simple rich text editor for content blocks
- Changes reflect on landing page immediately
- No code access — content only

### Fleet Management
- Vehicle registration & categorisation
- Vehicle documentation & image storage (S3)
- Insurance & registration tracking + expiry reminders
- Unique QR code per vehicle
- Vehicle history & audit logs
- Service due reminders
- Plan-enforced vehicle count limit (hard block)

**Vehicle Statuses (6):**
Available | Rented | Maintenance | Suspended | Accident | Reserved

### Customer Management
- Customer profiles (auto-created on agreement sign)
- Driver licence / passport storage
- Rental history
- Outstanding balances
- Emergency contacts
- Risk notes / internal remarks
- Blacklist management
- Bond/security deposit management
- Complete financial ledger per customer

### Customer Portal (customer login)
Customers can:
- View all their invoices
- Download invoices as PDF
- Make online payments (Stripe or PayPal)
- View and download signed agreements
- Track current rental status
- View rental history

### Booking & Rental Workflow
- Rental creation workflow
- Automated agreement generation
- Digital signature (canvas-based)
- Rental lifecycle tracking
- Payment schedule management
- Bond / security deposit management
- Vehicle return inspection workflow
- Fuel, odometer & damage tracking on return
- Late payment & overdue tracking

### Invoice & Finance
- Automatic invoice generation (daily/weekly/monthly)
- Manual / one-time invoices
- Custom billing cycle dates
- Prorated invoice splitting on vehicle change
- PDF receipts (queued)
- Payment link via SMS/email (hosted payment page)
- Customer portal payment (login-based)
- Online (Stripe/PayPal) + manual payment recording
- Late fee automation (configurable grace period + amount/%)
- Expense tracking
- Financial summaries & reports
- Bond refund calculations

### Agreement & Documents
- Rental agreements (Private, Delivery, Rideshare)
- Consent forms
- Return forms
- Breach notices
- Templates: super admin uploads defaults, tenants can customise own
- PDF export (queued)
- Digital signature (canvas-based)
- Agreement version history — immutable, append-only
- Auto-send updated agreement on vehicle change
- Customer re-sign trigger (admin setting)

### Workshop & Maintenance (QR-Based)
- Mechanic login (dedicated role, login required — not public)
- QR-based vehicle identification
- Service history management
- Maintenance & repair logging
- Spare parts tracking
- Labour cost management
- Service scheduling
- Photo / document upload
- Workshop analytics dashboard

**Maintenance Statuses (5):**
Pending | In Progress | Completed | Waiting for Parts | Re-inspection Required

### Analytics & Reporting
- Revenue analytics
- Fleet utilisation reports
- Active rentals overview
- Maintenance cost analysis
- Customer growth analytics
- Late payment / default reports
- Most profitable vehicles
- Workshop performance insights
- Export: PDF + Excel (both required)
- Financial year aligned to Australian FY (1 July – 30 June)

### CRM / Lead Management
- Lead intake form
- Secure unique links (SMS/email/WhatsApp)
- Links: manually expirable by admin, timestamped submissions
- Lead notification to admin on form submit
- Lead-to-agreement single-click conversion
- Auto customer profile + invoice creation on agreement sign

### AI Assistant
- System Help Mode (explains platform features)
- Business Intelligence Mode (tenant data queries)
- Tenant-restricted — no cross-tenant data
- Providers: Groq, Qwen, DeepSeek (tenant selects)
- All AI calls queued

### Notification System
- Email (Mailgun / Resend / SMTP — tenant selects)
- SMS (ClickSend or Cellcast — tenant selects)
- WhatsApp (ClickSend via Meta BSP)

**Triggers:**
Registration expiry | Insurance expiry | Service due | Overdue invoice |
Agreement expiry | Payment received | Lead submitted | Agreement signed |
Plan limit reached | Trial expiring | Subscription renewed | Bond refunded

### Public Landing Website
- Modern SaaS homepage (content managed via CMS)
- Features & modules showcase
- Pricing plans (synced with actual plans)
- About us & contact pages
- Demo request & lead generation forms
- Login / Register pages
- SEO-friendly architecture
- Mobile & tablet responsive
- Fast-loading optimised frontend

---

## MULTI-TENANCY

**Routing:** Path-based (`/app/{tenant_slug}/`)
**Isolation:** Row-level via `tenant_id` on every table + TenantScope global Eloquent scope
**Timezone:** Per-tenant setting (Australia/Sydney default)
**Currency:** Per-tenant setting (AUD default, multi-currency ready)
**Language:** English now — i18n layer built from day one for future languages
**Dark/Light Mode:** Per-user preference, stored in user profile, applied at runtime

---

## PLAN LIMITS & ENFORCEMENT

```php
// Hard block enforcement on every limit
// Check before create — throw PlanLimitException if exceeded
// PlanLimitException returns: 403 + upgrade message to user
// If no paid plan available: downgrade to default_free_package (system setting)

Limits per plan (super admin configures):
- max_vehicles
- max_staff_users
- max_storage_gb
- max_file_size_mb
- modules_enabled[] (array of enabled module keys)
- ai_enabled (bool)
- api_access (bool)
```

---

## ROLES & PERMISSIONS

### Super Admin Panel Roles
```
platform_owner     — Full platform access
billing_manager    — Subscriptions, payments, revenue
support_agent      — Tenant management, impersonation, support tools
content_manager    — CMS only
```

### Tenant Roles
```
tenant_admin       — Full tenant access
tenant_staff       — General operations
tenant_accounts    — Finance & invoices only
mechanic           — Workshop portal only
```

### Customer Portal
```
customer           — Own data only (invoices, agreements, payments, rental status)
```

---

## ARCHITECTURE RULES — NON-NEGOTIABLE

### NO LOGIC IN CONTROLLERS
Controllers: validate input → call Service → return response. Nothing else.

### SERVICE / ACTION / DTO PATTERN
```php
// CORRECT
public function store(StoreRentalRequest $request, CreateRentalService $service) {
    $rental = $service->execute(RentalDTO::fromRequest($request));
    return Inertia::render('Rental/Show', compact('rental'));
}
// WRONG — never put logic, queries, or calculations in controllers
```

### EVENTS — EVERY IMPORTANT ACTION
```
RentalCreated, RentalEnded, VehicleChanged, VehicleStatusChanged
AgreementSigned, AgreementUpdated, InvoiceGenerated, InvoiceUpdated
LateFeeApplied, PaymentReceived, PaymentRecorded
BondCollected, BondRefunded, LeadSubmitted, LeadConverted
CustomerCreated, CustomerBlacklisted, MaintenanceStarted
MaintenanceCompleted, ReturnInspectionCompleted
PlanLimitReached, SubscriptionUpgraded, SubscriptionCancelled
TenantActivated, TenantSuspended
```

### TENANT ISOLATION
```php
// Every tenant model must use TenantScope
// Every query must be scoped — no exceptions
// TenantMiddleware on all tenant routes
// Super admin uses separate guard — never shares with tenant guards
protected static function booted(): void {
    static::addGlobalScope(new TenantScope());
}
```

### AGREEMENT = SOURCE OF TRUTH
- Customer created AFTER agreement signed
- Invoices generated FROM agreement billing settings
- Agreements are immutable — new version on every change
- Vehicle changes trigger agreement versioning + customer re-sign if required

### LEDGER = SYSTEM OF RECORD
- Append-only — never update existing entries
- Every financial event creates a ledger entry
- Entries: rental charges, vehicle changes, prorated amounts,
  payments, late fees, discounts, bonds, refunds, expenses

---

## INVOICE ENGINE RULES

### Prorated Vehicle Change
1. Days on old vehicle in current cycle → charge proportionally
2. Remaining days for new vehicle → charge proportionally
3. Split invoice created
4. Ledger entries for both vehicles
5. Both vehicle utilisation reports updated
6. Agreement versioned
7. Customer notified (email + SMS/WhatsApp)
8. `VehicleChanged` event fired

### Late Fee Automation
- Configurable grace period per tenant
- Fee = fixed AUD amount or percentage (tenant configures)
- Auto-applied after grace period expires
- `LateFeeApplied` event fired
- Ledger entry created, customer notified

### Bond / Security Deposit
- Collected at rental creation → ledger entry (liability)
- Return inspection records damage deductions
- Refund = bond − deductions
- `BondRefunded` event fired on release

---

## PROVIDER SWITCHING PATTERN

```php
// All providers implement shared interfaces — never call SDKs directly
SmsProvider::for($tenant)->send($message);        // ClickSend or Cellcast
EmailProvider::for($tenant)->send($mailable);     // Mailgun, Resend, SMTP
AiProvider::for($tenant)->query($prompt);         // Groq, Qwen, DeepSeek
PaymentProvider::for($tenant)->charge($amount);   // Stripe or PayPal
```

---

## BACKUP & SECURITY

### Backup
- Package: spatie/laravel-backup
- Storage: Contabo Object Storage (dedicated backup bucket, separate from app files)
- Schedule: Daily, 30-day retention, auto-delete older
- Scope: PostgreSQL dump + app files (excluding S3 uploads)
- Alert super admin when VPS disk > 70%

### Security
- Tenant data isolation at query level (TenantScope — mandatory)
- Sanctum authentication
- Spatie RBAC
- Sensitive data encrypted at rest (Crypt cast on licence numbers, passport numbers)
- HTTPS enforced (Apache config + Certbot SSL)
- Stripe + PayPal webhook signatures verified
- QR codes signed + expirable
- Lead form links unique, signed, manually expirable
- Audit logs: append-only, tenant-scoped, store old/new values, IP, timestamp

---

## PERFORMANCE (VPS-CONSTRAINED — 8GB RAM)

Always queued — never synchronous:
- PDF generation
- AI calls
- Email / SMS / WhatsApp
- Report aggregations
- Excel exports
- Audit log writes

Max 3 Supervisor queue worker processes in production.
PHP-FPM pool tuned for memory.
Redis for cache, sessions, queues.
Dashboard KPIs via polling (30–60s) — not WebSockets in v1.

---

## API (MOBILE-READY FROM DAY ONE)

- All logic in Services — never in controllers
- `/api/v1/` routes maintained in parallel with Inertia routes
- Same Services used by both Inertia and API controllers
- Laravel API Resources for JSON responses
- Sanctum handles session (web) + token (mobile API) auth

---

## i18n (INTERNATIONALISATION)

- English only in v1
- All UI strings must go through translation layer from day one
- Backend: Laravel `__()` helper + `lang/en/` files
- Frontend: Vue i18n + `en.json` locale file
- Never hardcode display strings — always use translation keys
- Structure supports adding new languages without code changes

---

## UI / DESIGN RULES — NON-NEGOTIABLE

- Apple iOS / Stripe / Uber-level minimalism
- High-definition, crystal-quality — zero compromise
- Dark mode + light mode per user preference (Tailwind `dark:` classes)
- No admin-template patterns (no Filament, Voyager, generic CRUD)
- Custom Tailwind config — never raw default palette
- Smooth transitions + micro-interactions on all state changes
- Mobile responsive across all modules and portals
- Every screen intentionally designed

---

## CI/CD — GITHUB ACTIONS

```
Branch: staging → auto-deploy → Demo Server (demo.dvaro.com.au)
Branch: main    → auto-deploy → Production VPS (dvaro.com.au)

Pipeline steps:
1. Run tests (php artisan test)
2. composer install --no-dev
3. npm ci && npm run build
4. SSH to server
5. git pull
6. composer install --no-dev --optimize-autoloader
7. php artisan migrate --force
8. php artisan config:cache && route:cache && view:cache
9. php artisan queue:restart
10. php artisan up
```

---

## TESTING

- Framework: Pest PHP
- Every module: feature tests (HTTP) + unit tests (Services + Actions)
- Demo seeder: `DemoSeeder` class pre-loads realistic sample data

---

## WHAT CLAUDE MUST NEVER DO

- NEVER put business logic in a controller
- NEVER query without tenant scope
- NEVER skip firing an event for important actions
- NEVER call Stripe/PayPal/AI/SMS SDKs directly — always use provider interface
- NEVER generate a PDF synchronously — always queue it
- NEVER build a Vue page that looks like a generic admin template
- NEVER use `DB::table()` for tenant models — always Eloquent with global scopes
- NEVER mutate a ledger entry — append only
- NEVER mutate an agreement — always create a new version
- NEVER skip writing a migration
- NEVER assume a customer exists before agreement is signed
- NEVER expose one tenant's data to another
- NEVER hardcode a UI string — always use translation keys
- NEVER enforce plan limits as soft warnings — always hard block

---

## ENV VARIABLES

```env
APP_TIMEZONE=Australia/Sydney
APP_CURRENCY=AUD
APP_LOCALE=en

# SMS
CLICKSEND_USERNAME=
CLICKSEND_API_KEY=
CELLCAST_API_KEY=

# WhatsApp
CLICKSEND_WHATSAPP_NUMBER=

# Email
MAILGUN_DOMAIN=
MAILGUN_SECRET=
RESEND_API_KEY=

# Payments
STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=
PAYPAL_CLIENT_ID=
PAYPAL_CLIENT_SECRET=
PAYPAL_WEBHOOK_ID=

# AI
GROQ_API_KEY=
QWEN_API_KEY=
DEEPSEEK_API_KEY=

# Storage (app files)
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=ap-southeast-2
AWS_BUCKET=dvaro-app

# Contabo Object Storage (backups — separate bucket)
BACKUP_DISK=s3
CONTABO_BACKUP_ENDPOINT=https://eu2.contabostorage.com
CONTABO_BACKUP_KEY=
CONTABO_BACKUP_SECRET=
CONTABO_BACKUP_BUCKET=dvaro-backups
CONTABO_BACKUP_REGION=eu2

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Reverb (future)
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
```

---

## SERVER DEPLOYMENT — SHARED VPS (IMPORTANT)

DVARO is deployed on a SHARED Contabo VPS (IP 46.250.247.69) that also hosts
another client project (Jaral Motors). Both belong to the same client.

### Existing server state (do NOT break)
- Web server: Apache 2.4.58 (Jaral Motors runs on this)
- Database: MySQL (Jaral Motors uses this — leave untouched)
- Existing PHP: 8.2 NTS (used by Jaral Motors)
- Existing sites: jaralmotors.com, crm.jaralmotors.com (both with SSL)
- Other developer with access: ankit

### DVARO server requirements (added alongside, never replacing)
- Web server: Apache virtual host for dvaro.com.au (NOT Nginx — server uses Apache)
- PHP: 8.3 via PHP-FPM (installed alongside existing 8.2 — both coexist)
- Database: PostgreSQL 16 (installed alongside existing MySQL — both coexist)
- Cache/Queue: Redis (newly installed)
- DVARO project path: /var/www/dvaro
- Jaral Motors path: /var/www/html (DO NOT TOUCH)

### Apache Virtual Host strategy
- Each site has its own Apache .conf file in /etc/apache2/sites-enabled/
- dvaro.com.au gets its own virtual host pointing to /var/www/dvaro/public
- DVARO uses PHP 8.3-FPM; Jaral Motors keeps PHP 8.2 — set per-vhost
- Never modify jaralmotors.com.conf or crm.jaralmotors.com.conf

### Deployment rules on shared server
- NEVER run commands that restart Apache without coordinating (Jaral Motors goes down briefly)
- NEVER uninstall or change the default PHP version (breaks Jaral Motors)
- NEVER touch the MySQL database or its config
- ALWAYS back up before any server-wide change
- Use PostgreSQL for DVARO exclusively — MySQL belongs to Jaral Motors
- No control panel (CloudPanel/HestiaCP) — would wipe the Apache setup

### Backup before any change
```bash
sudo cp -r /etc/apache2 /etc/apache2.backup.$(date +%Y%m%d)
sudo tar -czf /root/jaralmotors-backup-$(date +%Y%m%d).tar.gz /var/www/html
mysqldump --all-databases > /root/mysql-backup-$(date +%Y%m%d).sql
```

---

## FINAL NOTES

- Market: Australia — AUD default, Australian FY (1 Jul – 30 Jun) for reports
- Per-tenant: timezone, currency, SMS provider, email provider, AI provider, payment gateway
- Per-user: dark/light mode preference
- VPS has no auto-scaling — every feature must be memory-efficient
- Agreement versioning mandatory — immutable history
- Ledger is immutable — append only
- Super admin panel is a separate guard and route group
- Customer portal is a separate guard and route group
- Mechanic portal is a separate guard and route group
- Public landing website is in same repo, separate route group, CMS-controlled content
- Mobile API works from day one via /api/v1/ routes
- i18n layer built from day one — English only in v1
- Demo server pre-loaded with realistic seed data via DemoSeeder