# DVARO — Module Status

## Infrastructure
- [Done] Project scaffold + 14-module structure
- [Done] Multi-tenancy foundation (TenantScope + TenantMiddleware)
- [ ] Auth guards (tenant, superadmin, customer, mechanic)
- [ ] Provider interfaces (SMS, Email, AI, Payment)
- [ ] CI/CD pipeline (GitHub Actions)

## Modules
- [In Progress] SaaS Core (plans, subscriptions, tenant onboarding)
  - [Done] Plan model + migration (platform-wide, no tenant scope; cents; `getLimit()` returns -1 = unlimited)
  - [Done] Subscription model + migration (HasTenant; status/billing constants; active/trialing/onTrial scopes)
  - [Done] PlanEnforcementService (hard block; limit < 0 = unlimited, skipped)
  - [Done] PlanLimitExceededException (403 JSON, code PLAN_LIMIT_REACHED)
  - [Done] TenantOnboardingService + DTO + TenantRegistered event (DB::transaction, trialing subscription)
  - [Done] Tenant model: activeSubscription/activePlan/hasModule/withinLimit; plan_id FK (nullOnDelete)
  - [ ] Onboarding controller + routes (not built — services/models only)
  - [ ] Stripe/PayPal subscription billing
  - [ ] Super admin plan management UI
- [ ] Super Admin Panel
- [ ] CRM (leads, intake forms)
- [In Progress] Fleet Management
  - [Done] Vehicle model + migration (HasTenant; 6 status constants + STATUSES array; daily_rate in cents; registration_number unique per tenant (tenant_id+registration_number); qr_code_token nullable+globally unique, generation deferred; date casts on expiry/service fields; available/rented/needsService scopes; isAvailable / insuranceExpiringSoon / registrationExpiringSoon helpers; agreements() hasMany)
  - [Done] agreements.vehicle_id FK attached → vehicles.id (restrictOnDelete — vehicle can't be deleted with agreement history); Agreement->vehicle() belongsTo added
  - [Done] VehicleStatusChanged event (vehicle + old/new status; no listeners yet)
  - [Done] ChangeVehicleStatusAction — the ONLY sanctioned path to mutate vehicle status (validates against STATUSES, fires VehicleStatusChanged, no-op on unchanged status); never mutate status directly elsewhere
  - [ ] QR code token generation/resolution (Workshop session)
  - [ ] Utilisation/reporting, vehicle docs & image storage, expiry reminder notifications, controllers/routes/UI (later sessions)
- [In Progress] Agreement Engine
  - [Done] Agreement model + migration (HasTenant; type/status/billing constants; rate & bond_amount in cents; version + parent_agreement_id self-FK for immutable versioning; customer_id & parent_agreement_id restrictOnDelete; isSigned/isActive/latestVersion helpers; indexes on tenant_id+status and customer_id)
  - [Done] vehicle_id FK → vehicles.id (restrictOnDelete) attached in Fleet session; vehicle() belongsTo added
  - [ ] Versioning/signature/PDF/template workflow, services, controllers (later sessions)
- [ ] Invoice & Financial Engine
- [In Progress] Customer Management
  - [Done] Customer model + migration (HasTenant; licence_number/passport_number encrypted casts; blacklisted/active scopes; date_of_birth/licence_expiry date casts; index on tenant_id+is_blacklisted)
  - [Done] hasOutstandingBalance() stub returning false (ledger not built yet)
  - [ ] Blacklist / bond / ledger logic, services, controllers, portal (later sessions)
- [ ] Customer Portal
- [ ] Workshop & Mechanic Portal
- [ ] AI Assistant
- [ ] Notification System
- [ ] Reporting & Analytics
- [ ] Landing Website + CMS

