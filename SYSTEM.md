# ProPhoto System Architecture (Authoritative)

## Overview

ProPhoto is a **disciplined modular monolith** built on Laravel.

It uses:

- **event-driven boundaries**
- **explicit package ownership**
- **one-directional dependencies**
- **Eloquent-aware pragmatism**

This is NOT abstract “clean architecture.”  
This is a **real Laravel system that embraces Eloquent, migrations, and relationships** while enforcing strict domain boundaries.

---

## Core Principle

> Packages represent **real stages in the asset lifecycle**.  
> Dependencies must follow **time and ownership**, not convenience.

---

# Core Event Loop (Authoritative)

This is the **center of the entire system**.
prophoto-ingest
→ emits: SessionAssociationResolved

prophoto-assets
→ consumes: SessionAssociationResolved
→ emits: AssetSessionContextAttached
→ emits: AssetReadyV1

prophoto-intelligence
→ consumes: AssetSessionContextAttached
→ consumes: AssetReadyV1

### Meaning

- **Ingest decides**
- **Assets attach canonical truth**
- **Intelligence derives outputs**

---

## Supporting Core Packages

### prophoto-contracts

Shared kernel:

- DTOs
- Enums
- Events
- Interfaces

Rules:

- No dependencies
- No Eloquent models
- Defines all cross-package contracts

---

### prophoto-booking

Owns:

- Session truth
- Operational scheduling context

Used by:

- ingest (matching context)
- intelligence (via snapshot only)

Rules:

- No direct access from intelligence
- Must not be mutated outside booking

---

# Package Roles

## 1. CORE PACKAGES (non-negotiable)

These power the event loop.

### prophoto-ingest

Owns:

- ingest item lifecycle
- session matching
- scoring + classification
- decision persistence

Emits:

- SessionAssociationResolved

Rules:

- does not mutate assets
- does not perform intelligence
- owns decision truth

---

### prophoto-assets

Owns:

- canonical asset truth
- asset projections

Consumes:

- SessionAssociationResolved

Emits:

- AssetSessionContextAttached
- AssetReadyV1

Rules:

- does not perform matching
- does not perform intelligence
- does not mutate booking

---

### prophoto-intelligence

Owns:

- intelligence planning
- orchestration
- generator execution
- intelligence persistence

Consumes:

- AssetSessionContextAttached
- AssetReadyV1

Rules:

- MUST NOT query booking directly
- MUST use SessionContextSnapshot
- MUST NOT mutate assets or ingest

---

### prophoto-contracts

Owns:

- system contracts

---

### prophoto-booking

Owns:

- session truth

---

## 2. DOMAIN PACKAGES (active secondary)

These are **real features**, but NOT part of the core loop.

They may depend on core, but never control it.

### prophoto-access

Owns:

- RBAC
- identity
- Studio / Organization

---

### prophoto-gallery

Owns:

- gallery presentation
- image grouping

---

### prophoto-ai

Owns:

- AI portrait workflows

---

### prophoto-interactions

Owns:

- image interactions (ratings, approvals, etc.)

---

### prophoto-invoicing

Owns:

- billing / invoices

---

### prophoto-notifications

Owns:

- messaging / email

---

## 3. ARCHIVED / FUTURE

These are NOT part of runtime:

- audit
- downloads
- payments
- permissions (replaced by access)
- security
- settings
- storage
- tenancy

Rules:

- live in `_archive/`
- READMEs preserved in `docs/architecture/future/`
- NOT installed via composer
- NOT referenced in runtime

---

# Dependency Rules

## Hard Rules

### 1. Event-driven boundaries

- Cross-package communication must use events or contracts
- No direct service calls across domains

---

### 2. No backward dependencies

- Packages may only depend on earlier lifecycle stages

---

### 3. No cross-domain mutation

- Only the owning package may mutate its data

---

### 4. Intelligence isolation

- Intelligence MUST NOT query booking directly
- Must rely on:
  - SessionContextSnapshot
  - canonical metadata

---

### 5. Asset ownership

- Assets own canonical truth
- Other packages reference by ID only

---

## Eloquent Reality (Important)

Allowed:

- foreign keys across packages
- upstream relationships

Not allowed:

- downstream relationships

---

# Event Rules

- Events are immutable
- Events are versioned if changed
- Events carry IDs, not models
- Events live in prophoto-contracts

---

# Package Creation Rules (Prevent Future Drift)

A new package must be ONE of:

### CORE

- participates in event loop
- owns canonical truth

### DOMAIN

- feature-level package
- depends on core

### FUTURE

- spec only
- not installed

If it doesn’t fit one of these:
→ it should not exist

---

# Anti-Patterns (Strict)

- ❌ circular dependencies
- ❌ direct DB access across domains
- ❌ “god packages”
- ❌ intelligence querying booking
- ❌ asset mutation outside assets
- ❌ ingest doing anything except decisions

---

# Current Priorities

1. Maintain strict event boundaries
2. Prevent cross-package mutation
3. Keep intelligence fully decoupled
4. Avoid adding new packages without classification
5. Remove dead dependencies before adding new ones

---

# Mental Model

Think in flow:
capture → ingest → decision → asset truth → intelligence
If something breaks that flow:
→ it is wrong

---

# Summary

ProPhoto is:

- event-driven
- lifecycle-aligned
- package-disciplined
- Laravel-realistic

Not:

- abstract
- over-engineered
- dependency-chaotic

---

This file is **authoritative**.

If code contradicts this document:
→ the code is wrong
