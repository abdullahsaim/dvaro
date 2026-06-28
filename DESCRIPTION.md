# DVARO — GitHub Repository Description

## Short description (GitHub "About" field — ≤ 350 chars)

> Multi-tenant SaaS for car rental companies. Laravel 11 + Vue 3 + Inertia + PostgreSQL. Fleet, customers, immutable rental agreements, append-only finance ledger, prorated invoicing, QR-based workshop portal, and multi-provider notifications — every tenant fully isolated. Built for the Australian market.

## One-liner (even shorter)

> Intelligent Fleet & Rental Operations Platform — a multi-tenant car-rental SaaS built on Laravel 11, Vue 3 & Inertia.

## Suggested topics / tags

```
laravel  php  vue  inertiajs  tailwindcss  postgresql  redis
multi-tenant  saas  car-rental  fleet-management  rental-management
invoicing  australia  domain-driven-design
```

## Website

dvaro.com.au

---

## Elevator pitch (for a wiki / landing / pitch deck)

DVARO runs the full operational lifecycle of a car rental business — fleet, customers, rental
agreements, invoicing, payments, workshop maintenance, and notifications — as a multi-tenant SaaS
where every rental company is a fully isolated tenant.

It is engineered for businesses handling real money and real contracts, so correctness is the
headline feature: the financial ledger is append-only, rental agreements are immutable and versioned,
plan limits are hard blocks, and prorated vehicle-change invoices always reconcile to the cent.
Mechanics, customers, and platform staff each get their own authentication guard and portal, and
every tenant chooses its own SMS, email, payment, and AI providers through a pluggable interface layer.

## What makes it different

- **Tenant isolation by default** — a global query scope means cross-tenant data leakage is
  structurally prevented, not just policed.
- **Financial integrity** — append-only ledger + immutable agreement versioning give a complete,
  tamper-resistant audit trail of every charge, payment, and contract change.
- **Workshop in the loop** — QR-coded vehicles let mechanics scan, log services, and track parts &
  labour costs from a dedicated portal.
- **Provider-agnostic** — swap SMS/email/payment/AI vendors per tenant without touching application code.
- **Built to scale down** — every heavy operation (PDFs, notifications, reminders) is queued and
  memory-conscious for a constrained VPS.
