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
- [ ] Fleet Management
- [ ] Agreement Engine
- [ ] Invoice & Financial Engine
- [ ] Customer Management
- [ ] Customer Portal
- [ ] Workshop & Mechanic Portal
- [ ] AI Assistant
- [ ] Notification System
- [ ] Reporting & Analytics
- [ ] Landing Website + CMS

