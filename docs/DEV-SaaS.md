# #prophoto/Order-of-Operations

This is a *great* question — and you’re asking it at exactly the right time. The answer isn’t “finish packages in some abstract order”, it’s **build in dependency + learning order**, so each thing you build *unlocks* the next instead of forcing rewrites.

Below is a **realistic order of operations** for *you*, given:

* ingest is already strong
* access exists but is early
* 9–12 month build window
* modular monolith, not fantasy architecture

I’ll also tell you *why* each step comes next, so you don’t second-guess yourself.

---

# **ProPhoto — Recommended Order of Operations**

## **Phase 0 (Now – 1 week): Lock the ingest foundation**

**Goal:** Make ingest boring and unbreakable.

### **Do THIS now (no new features):**

1. **Finish ingest FIXES**

   * Namespace casing
   * PreviewState enum + transitions
   * Metadata normalization purity
   * Server-side file validation
   * Optimistic UI state correctness
2. **Add ingest tests**

   * Preview state transitions
   * Metadata normalization fixtures
   * Job idempotency (retry safe)

⠀
👉 **Stop when ingest is “boring”** 

If you don’t trust ingest, everything else will feel fragile.

---

## **Phase 1 (Next): Make**

## **prophoto-gallery**

## **real (2–3 weeks)**

**Why next?** 

Gallery is the **center of gravity** of the whole product. Everything points *toward* it.

### **What to build:**

* Create **real composer package** for prophoto-gallery
* Gallery + Image models
* Policies (read/write/download)
* Magic-link access (simple version)
* Minimal gallery UI (even ugly is fine)

### **Do NOT do yet:**

* AI
* Downloads
* Invoicing

👉 **Stop when:** 

You can ingest → select → promote images → view gallery via URL.

---

## **Phase 2: Finish Access (Authorization you can trust) (1–2 weeks)**

**Why now?** 

Once galleries exist, *permission mistakes become catastrophic*.

### **Focus areas:**

* Clean up prophoto-access
* Lock roles + permission constants
* Contextual permission checks (gallery/org scoped)
* Policy patterns you’ll repeat everywhere

### **Explicitly ignore:**

* Fancy admin UI
* Role editing UX polish

👉 **Stop when:** 

You can confidently answer “who can see this gallery?”

---

## **Phase 3: Interactions (Small, high leverage) (1 week)**

**Why?** 

Interactions sit directly on top of galleries and validate your permission model.

### **Build:**

* Ratings
* Approvals
* Notes
* Audit trail (light)

This forces:

* user ↔ image ↔ gallery relationships
* policy enforcement
* optimistic UI patterns

👉 **Stop when:** 

A subject can rate + approve images and you trust the data.

---

## **Phase 4: Booking → Gallery creation (2 weeks)**

**Why now?** 

Booking creates galleries automatically — this is your first **event-driven win**.

### **Build:**

* Session model
* BookingRequest model
* Simple admin booking confirmation
* Event: SessionConfirmed → CreateGallery

### **Don’t do yet:**

* Google Calendar sync
* Client self-service booking UI polish

👉 **Stop when:** 

Confirming a session reliably creates a gallery.

---

## **Phase 5: Notifications (Thin but necessary) (1 week)**

**Why?** 

At this point things happen “in the background” — users need feedback.

### **Build:**

* Email notifications for:

  * gallery ready
  * booking confirmed
* Message model
* Template system (simple Blade)

👉 **Stop when:** 

Key actions send correct emails.

---

## **Phase 6: Billing (Invoices first, payments later) (2–3 weeks)**

**Why later?** 

Money logic is high-risk and touches everything.

### **Step 1 — Invoicing:**

* Invoice + InvoiceItem
* Link sessions → invoice
* PDF generation
* Status flow (draft → sent → paid)

### **Step 2 — Payments:**

* Stripe checkout
* Webhooks
* Payment reconciliation

👉 **Stop invoicing when:** 

You can generate and send invoices without payments.

👉 **Stop payments when:** 

A Stripe webhook can safely mark an invoice as paid.

---

## **Phase 7: Downloads (1 week)**

**Why so late?** 

Bulk downloads are expensive, slow, and easy to overbuild.

### **Build:**

* ZIP job
* Progress tracking
* Authorization checks
---

## **Phase 8: AI (Only now) (optional, 3–4 weeks)**

**Why last?** 

AI multiplies complexity; it should sit on solid foundations.

At this point you’ll:

* trust metadata
* trust galleries
* trust permissions
* trust async jobs

That’s when AI feels *fun*, not terrifying.

---

# **The Golden Rule (Read This Twice)**

> **Never build a package whose correctness depends on a package you don’t trust yet.** 

That’s why:

* ingest first
* gallery second
* access third
* money last
---

## **TL;DR Cheat Sheet**

1. ✅ Ingest (finish + harden)
2. ➜ Gallery
3. ➜ Access
4. ➜ Interactions
5. ➜ Booking
6. ➜ Notifications
7. ➜ Invoicing → Payments
8. ➜ Downloads
9. ➜ AI

⠀
If you follow this order, you won’t have to rip anything out later.

If you want, next I can:

* turn this into a **checklist you can pin in the repo**, or
* help you define **“done” criteria** for each phase so you know when to move on.