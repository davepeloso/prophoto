## ProPhoto development process:

Define the first product slice as a package-level README brief

Specifically:

Slice

EXAMPLE
Upload Recognition Moment

The system recognizes an upload as a likely shoot/session and offers the next best actions.

That should happen before code.

Package it should live in

Primary package: prophoto-ingest

Why:
• the trigger begins at upload
• matching/scoring already lives there
• it is the first place that can recognize “this looks like Alma Mater Footwear Lifestyle”

But it will likely depend on existing context from:
• prophoto-booking for session/calendar context
• prophoto-contracts for events/DTOs later
• possibly prophoto-assets only after the ingest decision is finalized

So the brief belongs in:
• prophoto-ingest/README.md if you are evolving the package README
ProPhoto — Development Workflow

Purpose

This document defines how all new features are designed, specified, implemented, and validated across the ProPhoto system.

The goal is to:
• Prevent premature coding
• Maintain architectural integrity
• Ensure every feature maps to real workflow value
• Keep all packages aligned with the core system loop

⸻

Core Principle

No feature implementation begins until the owning package, boundaries, and expected behavior are clearly defined.

⸻

Roles

Product Owner
• Defines real-world problems and workflow needs
• Approves feature direction
• Validates that output matches real usage

⸻

Architect
• Determines the next development step
• Identifies owning package
• Defines boundaries and system impact
• Reviews specs and implementation

⸻

Implementation Engineer
• Writes code based on approved spec
• Follows package boundaries strictly
• Does not invent architecture

⸻

Reviewer / Strategist)
• Reviews high-level design decisions
• Identifies gaps or inconsistencies
• Helps refine product direction (not implementation)

⸻

Required Feature Development Workflow

Every feature MUST follow this sequence:

1. Identify the Slice

Define a real user moment, not a technical task.

Example:
 • “Upload recognition after a shoot”

⸻

2. Identify the Owning Package

Assign the feature to ONE primary package.

Rules:
    • Ingest → upload-time behavior
 • Assets → projections / media truth
 • Intelligence → orchestration / AI decisions
 • Booking → session/calendar truth
 • Contracts → shared DTOs/events only

⸻

3. Write the Package-Level Spec (README or docs/)

Create or update a spec BEFORE coding.

Location:
 • prophoto-<package>/docs/<feature>.md

Must include:
 • Purpose
 • User moment
 • Inputs
 • Behavior
 • Boundaries (what it does NOT do)
 • Dependencies on other packages
 • Future contracts/events (no code yet)

⸻

4. Review the Spec

Checklist:
 • Is this solving a real workflow problem?
 • Is the owning package correct?
 • Are boundaries respected?
 • Does it violate event-driven architecture?
 • Is anything premature or over-engineered?

Only proceed when the spec is clean.

⸻

5. Define Architecture Boundary (NEW)

Before implementation, define the architecture boundary for the slice.

This includes:

- recognition/result structure (architecture-level, not DTO code)
- owning package responsibilities
- service boundary
- trigger point in lifecycle
- whether events are required or should remain internal
- what is explicitly NOT wired in v1

Rules:

- no DTOs yet
- no migrations
- no UI hooks
- no tests
- no implementation planning
- do not assume new events are required

Purpose:
Prevent premature contract design and preserve package boundaries.

---

6. Generate Implementation Planning Prompt

Only after the boundary is approved:

Define:

- DTOs / enums (now allowed)
- services and class structure
- where logic is invoked in the package
- minimal UI hook (if needed)
- tests (unit + feature)

Still do NOT write code.

---

7. Implement (Codex)

Codex writes code strictly from the approved plan.

---

8. Review Code & Test

Checklist:
 • Does code match spec exactly?
 • Any cross-package violations?
 • Any hidden mutations?
 • Are events used correctly?
 • Are tests meaningful?

Run full package test suite before moving on.

⸻

Architecture Rules (Non-Negotiable)
 • Event-driven communication only
 • No cross-package mutation
 • No direct querying across domain boundaries
 • Intelligence consumes snapshots, never booking directly
 • Assets store projections, not source truth
 • Contracts contain only shared DTOs/events/enums

⸻

Spec Before Code Rule

If any step jumps directly to:
 • DTOs
 • migrations
 • services
 • events

👉 STOP and go back to writing the spec.

⸻

Package Ownership Rule

Every feature must answer:

“Where does this live?”

If the answer is unclear:
 • the feature is not ready
 • or the architecture is wrong

⸻

Testing Rule
 • Every new feature must include:
 • unit tests (logic)
 • feature tests (flow)
 • Full package suite must pass before moving forward

⸻

“Do Not Skip” List

Never skip:
 • Package selection
 • Spec writing
 • Spec review
 • Code review
 • Test validation

Skipping any of these leads to architectural drift.

⸻
 7. Owning package: which package owns the behavior?
 8. Package wiring: what does it consume, emit, and explicitly not touch?

Summary

ProPhoto is not built by writing code.

It is built by:
 1. Defining real workflow moments
 2. Mapping them to the correct package
 3. Designing behavior clearly
 4. Then implementing with discipline

This process is what produced the core packages.

It must be followed for all future development.
:::

Use this exact sequence every time:

 1. Determine the next slice
 2. Choose the owning package
 3. Write the README/spec prompt
 4. Proofread the README/spec
 5. Define architecture boundary
 6. Generate implementation planning prompt
 7. Implement (Codex)
 8. Proofread code and test