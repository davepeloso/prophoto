# #prophoto/ingest/upgrades/new

### **FIX 1 — Namespace Casing Consistency**

**Problem** 

Mixed namespaces:

* prophoto\Ingest\…
* ProPhoto\Ingest\…

This *will* break on Linux / stricter autoload environments.

**Action** 

* Pick **one canonical namespace** → ProPhoto\Ingest
* Update:

  * all PHP files
  * composer.json PSR-4 mapping
* Enforce going forward

- - -

### **FIX 2 — Server-Side File Type Validation ❗**

*(You already identified this — correct)* 

**Problem** 

Backend only validates:
```php
file' => 'required|file|max:102400
```

**Action** 

* Enforce MIME / extension validation server-side
* Put accepted types in config so UI + backend share it

- - -

### **FIX 3 — Optimistic UI State Drift ❗**

*(You identified this; I’m tightening the language)* 

**Problem** 

* Cull toggle sends { is_culled: true } instead of the intended state
* Rotate appears UI-only unless persisted elsewhere

This causes **client/server divergence** over time.

**Action** 

* Either:

  * send the actual desired state, or
  * provide true “toggle” endpoints that return authoritative state
* After every mutation, return updated DTO and replace client state

- - -

### **FIX 4 — Preview Pipeline Idempotency & State Guarantees ❗**

*(You identified this conceptually; needed formalization)* 

**Problem** 

* Preview jobs assume single execution
* No strict state transitions
* Failures can leave UI polling forever

**Action** 

* Introduce a strict **PreviewState enum**
* Enforce allowed transitions only
* Store failure reason + timestamps
* Make job safe to retry
- - -

### **FIX 5 — Metadata Normalization Pollution ❗❗**

*(This was NOT fully explicit in your review — this is the most important gap)* 

**Problem** 

Your metadata column currently mixes:

* normalized fields
* UI convenience fields
* duplicated raw EXIF keys

This defeats:

* contracts
* querying
* long-term correctness

**Action** 

* metadata_raw = raw ExifTool JSON only
* metadata = **strict normalized whitelist only**
* No raw EXIF keys, no display strings, no legacy fields
* DTO/UI derives anything “pretty”

- - -
- - -
—————UPGRADES——————

### **UPGRADE A — Formal Ingest DTO Contract**

*(You hinted at this; now fully specified)* 

* Stable DTO shape for React
* Versioned (dto_version)
* Single transformation point (ProxyImageDto)

**Benefit** 

* UI stability
* Easier refactors
* Future prophoto-contracts extraction

**Priority:** Medium

**Status:** ✅ Now clearly defined

---

### **UPGRADE B — Ingest “Doctor” Diagnostics Panel**

*(Your idea — very good one)* 

Expose:

* ExifTool availability
* Queue health
* Storage writeability
* Preview latency
* Last failure reason

**Benefit** 

* Saves days of debugging later
* Essential for production support

**Priority:** Medium

**Status:** ✅ Your idea, endorsed

---

### **UPGRADE C — Smarter Polling Strategy**

*(You identified this correctly)* 

* Stop polling when ready
* Backoff after X seconds
* Retry button for failed previews

**Priority:** Low–Medium

**Status:** ✅ Good polish

---

## **🔎 Summary: Coverage Check**

---

### **UPGRADE B — Ingest “Doctor” Diagnostics Panel**

*(Your idea — very good one)* 

Expose:

* ExifTool availability
* Queue health
* Storage writeability
* Preview latency
* Last failure reason

**Benefit** 

* Saves days of debugging later
* Essential for production support

**Priority:** Medium

**Status:** ✅ Your idea, endorsed

---

### **UPGRADE C — Smarter Polling Strategy**

*(You identified this correctly)* 

* Stop polling when ready
* Backoff after X seconds
* Retry button for failed previews

**Priority:** Low–Medium

**Status:** ✅ Good polish

## **🔎 Summary: Coverage Check**

|  **Area**  |  **Covered by you?**  |  **Status**  | 
|---|---|---|
|  Async ingest pipeline  |  ✅  |  Correct  |
|  UI architecture  |  ✅  |  Correct  |
|  Tag evolution  |  ✅  |  Correct  |
|  Namespace casing  |  ✅  |  Must fix  |
|  Server-side validation  |  ✅  |  Must fix  |
|  UI state drift  |  ✅  |  Must fix  |
|  Preview state machine  |  ⚠️  |  Now locked  |
|  Metadata normalization purity  |  ❌  |  **Critical gap (now fixed)**  | 
|  DTO contract  |  ⚠️  |  Now formalized  |
|  Diagnostics  |  ✅  |  Upgrade  |
|  Polling polish  |  ✅  |  Upgrade  |
---

## **Final, Honest Assessment**

This ingest system is **not a prototype**.

You already built:

* the correct async model
* the right UX shape
* a scalable pipeline

What you were missing was **data discipline**, not architecture.

Once you:

* purify metadata
* enforce preview state transitions
* lock the DTO contract

…this becomes a **foundation you can safely build the rest of ProPhoto on**.

If you want, next we should **apply this same Fix vs Upgrade pass** to:

* prophoto-gallery, or
* prophoto-booking, or
* the ingest → gallery handoff (events + contracts)

Just tell me which one you want to tackle next.