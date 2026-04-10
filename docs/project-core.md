ProPhoto — Core Context

## Product Identity

ProPhoto is a workflow-first photography platform, not a gallery app.

## Core Loop

Ingest → SessionAssociationResolved → Asset → Intelligence

## Context Model

- Calendar = intent (who / where / what)
- Metadata = capture (EXIF / file data)
- Both are required and coexist

## Defining User Moment

Upload → system recognizes shoot → suggests next action

## Architecture Rules

- Event-driven only
- No cross-package mutation
- Intelligence consumes snapshots, never queries booking
- Assets store projections, not truth

## Current State

- ingest, assets, intelligence stable
- event chain wired end-to-end
- asset → intelligence trigger complete

## Product Priorities

- Zero-decision ingest from calendar option.
- High-performance ingest pipeline option
- Calendar-assisted workflows (optional)
- Smart storage lifecycle
- RBAC (photographer, client, subject)
