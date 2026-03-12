# Architectural Rules

### 1. Aggregate Roots Own Mutations
Only aggregate roots may mutate lifecycle state.

Examples:

- Image
- Gallery
- Organization
- Session
- DownloadArchive

---

### 2. Cross-Package Writes Use Events

Packages must not directly mutate other package aggregates.

Use events:

- AssetIngested
- ArchiveBuilt

---

### 3. Hard Sync Dependencies Must Be Rare

Hard sync dependencies create coupling.

Allowed examples:

- Image → Gallery
- ImageInteraction → Image

---

### 4. Soft Async Dependencies Are Preferred

Preferred communication:

- Domain events
- Async jobs
- Read-only queries

---

### 5. Storage Is Centralized

Filesystem concerns belong in:

Packages must not construct their own paths.

---

### 6. Public Contracts Must Be Stable

Nodes marked `public-contract` must be:

- versioned
- backward compatible
- documented

---

# Canonical Image Lifecycle

1. Upload creates **StagingImage**
2. **IngestJob** extracts metadata and derivatives
3. Asset promoted to **Image**
4. **AssetIngested** event emitted
5. Image attached to **Gallery**
6. Clients perform **Interactions**
7. Delivery built as **DownloadArchive**
8. **ArchiveBuilt** event emitted
9. Approved images feed **AIGeneration**
10. **GeneratedPortrait** produced
