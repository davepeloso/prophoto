# ProPhoto Contracts

This package contains shared interfaces, DTOs, enums, and contracts for the ProPhoto ecosystem.

## Purpose

The contracts package serves as the single source of truth for:

- **Interfaces**: Service boundaries and abstractions
- **DTOs**: Data transfer objects for cross-package communication
- **Enums**: Shared vocabulary and constants
- **Exceptions**: Common exception types

## Dependency Rule

**Domain packages may depend on prophoto-contracts, but prophoto-contracts must never depend on domain packages.**

This ensures:
- Clean architecture
- No circular dependencies
- Clear package boundaries
- Easy testing and mocking

## What Belongs Here

### ✅ Include:
- Service interfaces
- Data transfer objects
- Enums and constants
- Event contracts
- Shared exception types

### ❌ Exclude:
- Eloquent models
- Migrations
- Controllers/routes
- Service providers with business logic
- Implementation details
- Database-specific code

## Versioning

Treat this package as slow-moving and stable. Avoid breaking changes when possible.
