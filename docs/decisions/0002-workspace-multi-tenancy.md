# ADR-0002: Workspace Multi-Tenancy

**Status:** Accepted  
**Date:** November 2026  
**Deciders:** Project Engineering Team  
**Consulted:** `docs/internal/architecture.md`, `docs/internal/current-state.md`, `docs/internal/atlas-product-and-architecture.md`, `docs/internal/implementation-roadmap.md`  

---

## 1. Context

Atlas is a multi-tenant workflow automation Software-as-a-Service (SaaS) platform designed for business teams. A foundational architectural requirement is that data must remain strictly isolated across organizations: no workspace must ever view, modify, or execute another workspace's workflows, draft configurations, or execution history.

At the same time, users in Atlas must be able to collaborate in team environments, and individual users often belong to multiple distinct organizations (e.g. a consultant managing workflows for multiple client accounts, or an employee operating across separate organizational departments).

During early system design, the engineering team evaluated three primary multi-tenancy models:

1. **Database-per-Tenant:** Provisioning a separate, isolated PostgreSQL database instance or catalog for each tenant organization.
2. **Schema-per-Tenant:** Housing all tenants in a single PostgreSQL database, but segregating each tenant into an independent PostgreSQL schema namespace (`workspace_abc.workflows`, `workspace_xyz.workflows`).
3. **Shared-Database, Shared-Schema (Row-Level Tenancy):** Storing all tenants in a shared set of database tables, using explicit workspace foreign keys on root models, hierarchical relational scoping for child entities, application-level authorization, and scoped route model binding to isolate data.

---

## 2. Decision

Atlas adopts **Shared-Database, Shared-Schema Multi-Tenancy with Workspace as the Root Boundary**.

Specifically:

1. **Workspace as the Tenant Boundary:**
   - The `workspaces` table acts as the authoritative organizational root.
   - All tenant-owned domain resources belong directly or hierarchically to a specific workspace.
2. **Many-to-Many User Memberships:**
   - User identity (`users` table) is globally unique and decoupled from individual workspaces.
   - Users access workspaces through explicit `workspace_memberships` pivot records (`user_id`, `workspace_id`, `role`).
   - A unique composite index (`unique(['user_id', 'workspace_id'])`) ensures a user possesses at most one membership record per workspace.
   - A user may belong to multiple workspaces with independent roles in each.
3. **Workspace Roles & Owner Uniqueness:**
   - Memberships store one of four roles defined in `App\Enums\WorkspaceRole`: `owner`, `admin`, `member`, `viewer`.
   - A PostgreSQL partial unique index (`workspace_memberships_one_owner_per_workspace`) on `workspace_memberships (workspace_id) WHERE role = 'owner'` guarantees **at most one owner per workspace**. (It does not guarantee *at least one owner*; initial owner creation is guaranteed by application transaction logic in `CreateWorkspace`).
4. **Relational Tenant Scoping & Ownership Hierarchy:**
   - **Root Tenant Tables:** Root domain entities (`workflows`, `workspace_memberships`, and future `workflow_executions`) store a non-nullable `workspace_id` foreign key.
   - **Hierarchical Child Entities:** Child entities do not store a `workspace_id` column. Instead, they inherit tenant scoping hierarchically through parent relationships:
     - `workflow_versions` stores `workflow_id` (referencing `workflows.id`).
     - `workflow_nodes` stores `workflow_version_id` (referencing `workflow_versions.id`).
     - `workflow_edges` stores `workflow_version_id` (referencing `workflow_versions.id`).
5. **Database Deletion Behavior (Current Implementation vs. Future Execution):**
   - **Current Baseline:** All existing relational foreign keys enforce `ON DELETE CASCADE`:
     - Deleting a `workspace` cascades to its `workflows` and `workspace_memberships`.
     - Deleting a `workflow` cascades to its `workflow_versions`.
     - Deleting a `workflow_version` cascades to its `workflow_nodes` and `workflow_edges`.
     - Deleting a `workflow_node` cascades to any `workflow_edges` referencing it as source or target via composite foreign key constraints.
   - **Future Execution Deletion Behavior (Milestones M2–M4):** In alignment with the approved MVP cascade deletion policy, future execution persistence tables (`workflow_executions` and `workflow_execution_steps`) will also enforce `ON DELETE CASCADE`. Deleting a workspace cascades to its workflows, versions, executions, and execution steps. Execution history is not retained upon workspace or workflow deletion in the MVP; soft deletion and audit retention safeguards are deferred to Milestone M6.
6. **Multi-Layered Application Isolation:**
   - **Access Boundary Middleware (`workspace.access`):** The `EnsureWorkspaceAccess` middleware is registered as an alias in `bootstrap/app.php` and applied to all workspace-scoped routes. It resolves `{workspace}` from the route (using `Workspace::getRouteKeyName()`, which returns `slug`), checks whether an active membership exists (`$workspace->memberships()->where('user_id', $user->id)->exists()`), and calls `abort(403)` if the user is not a member.
   - **Scoped Route Model Binding (`->scopeBindings()`):** Nested resource routes enforce Laravel's `->scopeBindings()`. Under routes such as `/workspaces/{workspace}/workflows/{workflow:slug}/versions/{version}`, Laravel automatically scopes queries through parent Eloquent relationships (`$workspace->workflows()` and `$workflow->versions()`). If a workflow does not belong to the workspace, or a version does not belong to the workflow, Laravel throws a `ModelNotFoundException` (HTTP 404).
   - **Tenant ID Derivation:** Controllers and domain Actions derive `workspace_id` exclusively from verified route models (e.g. passing `$workspace` to `CreateWorkflow::execute()`). Client-supplied `workspace_id` fields in request bodies are ignored rather than used for tenancy resolution. (Form Requests currently validate specific fields like `name` and `description` rather than defining explicit `prohibited` rules for `workspace_id`).
7. **Current Implementation vs. Intended Security Guarantees:**
   - **Enforced:** Tenant membership boundaries (`EnsureWorkspaceAccess` rejects non-members with HTTP 403) and relational route scoping (`scopeBindings` rejects cross-tenant child models with HTTP 404).
   - **Pending Enforcement (Milestone M1):** Role-based authorization differences. While roles are stored in `workspace_memberships`, Form Requests currently return `authorize(): bool { return true; }` and no `WorkflowPolicy` exists; any workspace member (including `viewer`) can currently mutate workflows. Formal policy enforcement is scheduled for Milestone M1.
   - **Database-Level RLS Status:** PostgreSQL Row-Level Security (RLS) is **not** currently implemented. Isolation is currently guaranteed by application middleware, relational route scoping, and foreign key constraints.

---

## 3. Rationale

The shared-database, shared-schema tenancy model was selected because it delivers the optimal balance of developer velocity, operational simplicity, and resource efficiency for Atlas's current stage:

### 3.1 Operational Simplicity & High Velocity
- **Unified Migration Pipeline:** Schema migrations execute once across a single database (`php artisan migrate`). In contrast, managing multiple databases or separate schemas introduces significant migration orchestration complexity and schema drift risks.
- **Fast Local Testing:** Automated feature tests run against a single PostgreSQL database (`atlas_test` on port 5458) using Laravel's standard `RefreshDatabase` trait, completing comprehensive test suites in seconds.
- **Simple Infrastructure Provisioning:** Atlas runs on standard PostgreSQL infrastructure without custom connection-switching middleware, tenant routing proxies, or dynamic pool balancers.

### 3.2 Seamless Multi-Workspace User Experience
In workflow automation SaaS platforms, users frequently collaborate across multiple workspaces:
- Storing users globally and memberships as a pivot table allows users to authenticate once (via password, 2FA, or WebAuthn passkey) and switch between workspace contexts seamlessly via a simple session switch (`POST /workspaces/{workspace}/select`).
- Separate database or schema models would require complex cross-database authentication tokens, distributed user profiles, or multiple credentials.

### 3.3 Resource Pooling & Cost Efficiency
- Shared-schema multi-tenancy maximizes hardware utilization by pooling PostgreSQL buffer pools, memory, and database connections.
- Atlas avoids the connection and memory overhead associated with provisioning and maintaining multiple idle database instances or catalogs for small or trial workspaces.

---

## 4. Alternatives Considered

The engineering team evaluated alternative multi-tenancy models in the context of Atlas's specific requirements, architecture, and team stage:

### Alternative 1: Database-per-Tenant
- **Description:** Provisioning an isolated PostgreSQL database for every workspace.
- **Trade-Offs and Architectural Considerations for Atlas:**
  - *Operational Complexity:* Requires coordinating migrations and schema rollbacks across multiple distinct databases, managing separate connection pools, and automating database provisioning for new tenant registrations.
  - *User Experience Trade-Off:* Cross-workspace navigation and user authentication become significantly more complex, requiring centralized auth services or cross-database session tokens when a single user belongs to multiple organizations.
  - *Infrastructure Efficiency:* For an early-stage SaaS product with trial and low-volume workspaces, provisioning dedicated database resources introduces substantial idle cost and connection overhead.

### Alternative 2: Schema-per-Tenant
- **Description:** Storing all workspaces in one database, but giving each workspace its own PostgreSQL schema (`workspace_123.workflows`, `workspace_456.workflows`).
- **Trade-Offs and Architectural Considerations for Atlas:**
  - *PostgreSQL Catalog Overhead:* High tenant counts result in thousands of schema tables, increasing PostgreSQL catalog table bloat, slow DDL execution times, and memory consumption.
  - *Framework Support:* Dynamic schema search-path switching (`SET search_path TO ...`) is not natively supported by standard Laravel migrations and database tooling without specialized third-party packages, increasing maintenance risk.
  - *Migration Management:* Schema migrations cannot execute within a single standard transaction across all schemas, complicating atomic rollbacks upon migration failures.

### Alternative 3: User as the Tenant Boundary (Single-User Tenancy)
- **Description:** Scoping workflows directly to `user_id` without a workspace concept.
- **Trade-Offs and Architectural Considerations for Atlas:**
  - Incompatible with business workflow automation requirements: organizational customers require multi-user collaboration, shared workflow ownership, and differentiated permissions (`owner`, `admin`, `member`, `viewer`).

---

## 5. Consequences

### 5.1 Positive Consequences
- **Rapid Feature Development:** Developers write standard Eloquent models, migrations, and queries without managing dynamic connection switching.
- **Instant Tenant Provisioning:** Creating a new workspace is a standard database insert (`CreateWorkspaceAction`), requiring zero DDL execution or infrastructure provisioning.
- **Efficient Query Optimization:** Global indexes, vacuuming, and caching apply universally across all tenant data.
- **Flexible Team Membership:** Users can easily be invited, assigned roles, and removed from workspaces using standard relational associations.

### 5.2 Negative Consequences & Known Limitations
- **Risk of Developer Omission (Cross-Tenant Leakage):**
  Because all tenant data resides in shared tables, a developer writing an un-scoped direct query (e.g. `Workflow::find($id)`) without scoping to `workspace_id` could theoretically leak or mutate another tenant's data.  
  *Mitigation:* Atlas strictly mandates `->scopeBindings()` on all routes, derives `workspace_id` exclusively from route bindings, enforces policy checks in M1, and requires cross-tenant boundary tests for every endpoint.
- **Noisy Neighbor Problem:**
  A high-volume workspace triggering extensive workflow executions could consume database I/O, CPU, or connection pools, impacting adjacent tenants.  
  *Mitigation:* Execution throttling, rate limiting, and background queue isolation will be introduced in Milestones M5–M6.
- **Shared Failure Domain:**
  Database maintenance, unindexed slow queries, or schema migrations affect all tenants simultaneously.

---

## 6. Future Reconsideration Conditions

The shared-database, shared-schema model is authoritative for Milestones M0–M4 and the initial execution engine rollout. This decision may be revisited or augmented if the following conditions arise:

1. **Defense-in-Depth Database RLS (Milestone M6+):**
   PostgreSQL Row-Level Security (RLS) policies may be evaluated in future hardening phases to enforce `workspace_id` tenant isolation directly at the database connection layer, providing defense-in-depth beneath application scoping without altering the shared-schema model.
2. **Enterprise Dedicated Database Tiers:**
   High-value enterprise customers with strict regulatory compliance mandates (e.g. HIPAA, FedRAMP, dedicated data residency) may justify offering a dedicated database-per-tenant tier alongside the standard shared-schema platform.
3. **Horizontal Tenant Sharding:**
   If Atlas scales to millions of workflows and executions where a single PostgreSQL primary node cannot sustain aggregate write throughput, workspace groups may be horizontally sharded across multiple PostgreSQL clusters using consistent hashing on `workspace_id`.
