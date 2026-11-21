# ADR-0001: Modular Monolith Architecture

**Status:** Accepted  
**Date:** November 2026  
**Deciders:** Project Engineering Team  
**Consulted:** `docs/internal/architecture.md`, `docs/internal/atlas-product-and-architecture.md`, `docs/internal/implementation-roadmap.md`  

---

## 1. Context

Atlas is a multi-tenant workflow automation platform designed for business process orchestration. The core domain involves managing complex, versioned workflow graphs (nodes and edges), enforcing strict workspace isolation, preserving immutable published snapshots, and sequentially executing workflows with durable step audit trails.

During the architecture definition of Atlas, the engineering team had to choose an architectural style for the system's backend runtime, deployment topology, and data storage:

1. **Distributed Microservices:** Decomposing the system into independently deployed services (e.g. Identity Service, Workspace Service, Workflow Authoring Service, Execution Service) communicating via HTTP/gRPC or asynchronous message brokers, each with its own database.
2. **Decoupled REST API + Standalone SPA:** Building a decoupled Laravel JSON API backend and an independent React single-page application communicating over client-side REST or GraphQL.
3. **Modular Monolith:** Building a single, unified Laravel application and deployment unit with domain-oriented module separation, a shared PostgreSQL database, and server-driven React frontend via Inertia.js.

The primary technical requirements driving this choice are:
- **Strict Transactional Integrity:** Publishing a workflow draft involves locking the parent workflow, archiving the previously published version, validating the directed graph, and activating the new draft inside an atomic ACID transaction.
- **Relational Integrity & Tenant Isolation:** Workflow definitions rely heavily on relational constraints, including composite foreign keys (edges referencing nodes by compound version and node UUIDs) and partial unique indexes (at most one published version per workflow).
- **Developer Velocity & Operational Overhead:** Atlas is in an early implementation stage with a focused development team. Development friction, deployment overhead, and complex infrastructure must be minimized.

---

## 2. Decision

Atlas adopts a **Modular Monolith** architecture.

Specifically:
1. **Single Deployment Unit:** Atlas is built and deployed as a single, cohesive Laravel 13 application and PostgreSQL database service.
2. **Shared Relational Database:** All application domains share a single PostgreSQL 17 database, relying on schema constraints, foreign keys, and indexes for data integrity and tenant isolation.
3. **Domain-Oriented Organization:** Application code is organized into clear domain modules:
   - `Identity Domain` (`app/Actions/Fortify/`, user authentication, 2FA, passkeys).
   - `Tenancy Domain` (`app/Actions/Workspace/`, workspaces, memberships, role assignments).
   - `Workflow Definition Domain` (`app/Actions/Workflows/`, workflows, versions, nodes, edges, validation).
   - `Execution Domain` (`app/Services/Execution/`, pinned runs, step runners, history tracking — planned for M2–M4).
4. **Action-Based Business Logic:** Business operations are encapsulated in single-purpose Action classes (`app/Actions/{Domain}/`) with a uniform `execute()` method. Actions own database transactions and concurrency locking.
5. **Server-Driven Monolith Bridge:** The frontend is built with React 19 and integrated using Inertia.js v3, eliminating the need to maintain duplicate client-side API clients or decoupled REST plumbing.
6. **Code Reuse for Future API Endpoints:** When public APIs or webhook triggers are introduced in future milestones, they will call existing Action classes directly, reusing identical validation, locking, and business logic without code duplication or network hops.
7. **No Premature Microservice Decomposition:** Microservices, distributed databases, and inter-service remote procedure calls are explicitly rejected for the current product stage.

---

## 3. Rationale

The modular monolith style was selected because it aligns directly with Atlas's core technical requirements and operational reality:

### 3.1 Transactional Integrity Without Distributed Complexity
Workflow versioning and execution require strong transactional guarantees:
- `PublishWorkflowVersion` requires acquiring pessimistic row-level locks on the parent `Workflow` and draft `WorkflowVersion`, updating previous versions, validating graph acyclicity, and committing atomically.
- In a microservices architecture, operations spanning workspaces, workflows, and executions would require distributed transaction protocols (e.g. Saga patterns, two-phase commits, outbox tables), introducing eventual consistency bugs, partial failure states, and significant complexity.
- In a modular monolith, PostgreSQL handles multi-table locking and atomic state transitions natively and reliably within standard ACID database transactions (`DB::transaction`).

### 3.2 Unified Relational Model & Tenant Boundaries
Atlas relies heavily on PostgreSQL features:
- Composite foreign keys (e.g. `workflow_edges` enforcing that source and target nodes exist within the exact same `workflow_version_id`).
- Partial unique indexes (e.g. `workflow_versions_one_published_per_workflow`, partial index for `idempotency_key`).
- Relational scoping (`->scopeBindings()`) where child resources are queried strictly through parent relationships.
A shared database allows PostgreSQL to act as a robust integrity engine, guaranteeing that tenant isolation and graph consistency are enforced at the database layer.

### 3.3 High Developer Velocity & Low Operational Overhead
- **Single Runtime:** Developers run one application container and one database container (`Docker Compose` on port 5458).
- **Single Test Suite:** The entire system—from authentication to workflow graph validation—is verified locally in seconds using `php artisan test` against a single PostgreSQL test database.
- **Zero API Boilerplate:** Inertia.js passes backend controller data directly to React page props as typed contracts, eliminating the need to write custom REST endpoints, client-side HTTP clients, state caches, or duplicate TypeScript data mappers for internal UI flows.

### 3.4 Future-Proof Architecture Through Actions
By encapsulating business logic inside single-purpose Actions (`CreateWorkflow`, `PublishWorkflowVersion`, `ExecuteWorkflowAction`) rather than embedding logic inside HTTP controllers, the modular monolith remains cleanly decoupled:
- If a workflow needs to be triggered from an HTTP controller, an Artisan CLI command, an automated cron job, or a future public API endpoint, all consumers invoke the same Action class.
- If a domain ever needs to be extracted into an independent service in the distant future, the boundaries are already organized by domain rather than tangled across global controllers.

---

## 4. Alternatives Considered

### Alternative 1: Distributed Microservices
- **Description:** Splitting Atlas into independent services (Auth Service, Workspace Service, Workflow Service, Execution Engine Service) communicating via REST/gRPC and Kafka/RabbitMQ.
- **Why Rejected:**
  - Introduces massive operational friction: managing multiple CI/CD pipelines, container registries, service meshes, distributed tracing, and network latency.
  - Destroys ACID transactional guarantees. Operations like publishing a workflow while checking workspace membership would require network calls and complex distributed error recovery.
  - Premature optimization: Atlas's current scale and single-team development model do not warrant distributed infrastructure.

### Alternative 2: Decoupled REST API with Independent SPA
- **Description:** A purely headless Laravel JSON API backend and an independent standalone React SPA (e.g. Vite SPA deployed to S3/Cloudflare Pages) communicating over REST or GraphQL.
- **Why Rejected:**
  - Introduces substantial development overhead: defining, versioning, and documenting internal API endpoints; managing client-side authentication tokens/cookies; writing duplicate TypeScript fetch clients; and managing client-side caching states.
  - Inertia.js provides the exact same rich, client-side React single-page application experience without the overhead of an intermediate REST API layer.

### Alternative 3: Unstructured Traditional Monolith
- **Description:** A standard MVC monolith where business logic, database queries, and transactions are placed directly inside Controller methods or bloated Eloquent models ("fat models, fat controllers").
- **Why Rejected:**
  - Causes rapid architectural decay: logic cannot be reused across Web, CLI, and API entry points.
  - Leads to tight, untestable coupling between HTTP request handling and domain business rules.

---

## 5. Consequences

### 5.1 Positive Consequences
- **ACID Integrity:** Reliable pessimistic locking and multi-table transactions for workflow publishing and execution initialization.
- **Rapid Feature Delivery:** Fast feedback loops during development; single migration pipeline; single test runner.
- **Code Reusability:** Business logic in Action classes is directly consumable by controllers, CLI commands, and future API endpoints.
- **Simplified Observability:** Unified application logs, centralized database metrics, and cohesive error tracking without distributed trace reconstruction.

### 5.2 Negative Consequences & Known Limitations
- **Logical Rather Than Physical Boundaries:** Module boundaries are enforced by team discipline and directory conventions (`app/Actions/{Domain}/`), not by physical package boundaries or separate compilation units. Without architectural vigilance, developers could introduce cross-domain coupling (e.g. querying child tables directly rather than respecting domain boundaries).
- **Unified Scaling:** All domains scale together. An intensive execution run consumes resources on the same application instance as web UI requests until background worker separation is introduced in Milestone M5.
- **Shared Database Coupling:** All domains share database connection pools and database resources. A slow query in one domain could impact database throughput for other domains.

---

## 6. Future Reconsideration Conditions

This architecture decision will remain in effect through Milestones M0–M4 and the initial execution engine rollout. The decision to maintain a single deployment unit may be reconsidered only if one or more of the following trigger conditions occur:

1. **Independent Team Scaling:** The engineering organization grows to multiple autonomous, cross-functional teams whose deployment schedules create severe release contention on a single codebase.
2. **Asymmetric Compute Scaling:** Workflow execution workloads require massive, highly specialized compute resources (e.g. dedicated GPU worker pools for high-volume local AI inference) that cannot be cost-effectively managed within the monolithic deployment topology.
3. **Data Sovereignty / Compliance Mandates:** Enterprise regulatory requirements mandate that execution data and customer tenant data reside in physically isolated database instances across distinct legal jurisdictions.

*(Note: Introducing background queue workers in Milestone M5 does **not** constitute microservices; background workers will run as worker processes of the same monolithic codebase, sharing the same models and Action classes).*
