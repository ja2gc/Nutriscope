# Architecture

Verified against committed source: `53230ce3f4e471b243d0fe0a8ef0b5fbfa89a891` (2026-10-23).

Purpose: locate system boundaries before changing behavior.

Browse the [domain index](../README.md) before implementation details. NCP and food service each link their backend rules, clients, tests and data meaning; shared role features have their own map. These are documentation groups, not new application module boundaries. [Data semantics](data-semantics.md) explains how to trace a field end to end.

- [API routes](../../../backend/routes/api.php) connect role-gated controllers, requests, services and models. Laravel owns validation, authorization, workflow transitions and persisted calculations.
- [Web routes](../../../frontend/app) use [services](../../../frontend/services) and Next API handlers. [laravelProxy](../../../frontend/lib/laravelProxy.ts) forwards the HttpOnly-cookie token to Laravel as bearer authentication.
- [Mobile layout](../../../mobile/app/_layout.tsx), [auth](../../../mobile/lib/auth.ts) and [API client](../../../mobile/lib/api.ts) connect Expo navigation, query state and SecureStore bearer tokens.
- [AppServiceProvider](../../../backend/app/Providers/AppServiceProvider.php) wires services/events; [bootstrap/app.php](../../../backend/bootstrap/app.php) wires exceptions, middleware and schedules.
- [StoredObjectStorage](../../../backend/app/Services/StoredObjectStorage.php), [reports](../../../backend/app/Services/Reports) and [backup services](../../../backend/app/Services/Backup) have different file responsibilities.

For a change, follow page → client → Next handler (web only) → Laravel route → request/authorization → service → model/Resource → reports/jobs and both clients. File effects need separate compensation; a database rollback does not undo them.

[Compose](../../../docker-compose.prod.yml) declares the current container roles. Host Nginx setup is described in [VPS setup](../../operations/vps-setup.md); an actual running topology is not established by the repository.

Related: [backend](backend.md), [web](web.md), [mobile](../food-service/mobile.md), [database](database.md), [operations](../operations/README.md).
