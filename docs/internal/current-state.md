# Atlas — Current Implementation State

**Baseline Snapshot Date:** November 2026 (P0.1 / P0.2 Revalidation)  
**Status:** Foundation and Workflow Definition Stage (Pre-Execution Engine)

---

## 1. Project Overview

**Atlas** is a multi-tenant, AI-powered workflow automation platform for businesses. The core platform promise is:
> A business workspace can visually define an automation, publish a versioned workflow definition, and let Atlas execute that workflow reliably while recording step-by-step history.

The project is currently at the transition point between **Workflow Definition & Canvas Management** and the **Workflow Execution Engine**. Workspaces, authentication, workflow models, versioning, draft editing, and a React Flow canvas are implemented. However, execution tracking, asynchronous workers, and action node execution handlers have not yet been built.

### Technology Stack
- **Backend Framework:** Laravel 13 (PHP 8.3+)
- **Authentication:** Laravel Fortify (Registration, login, reset, 2FA, passkeys)
- **Client/Server Boundary:** Inertia.js v3
- **Frontend Framework:** React 19 with TypeScript 5.7
- **Workflow Canvas:** `@xyflow/react` v12 (React Flow)
- **Styling & UI:** Tailwind CSS v4, Radix UI primitives, Lucide icons
- **Primary Database:** PostgreSQL 17 (hosted locally via Docker Compose on port `5458`)
- **Queue System:** Database queue connection configured in Laravel; execution jobs not yet implemented.

---

## 2. Implemented Functionality

The following features have been verified in the codebase and confirmed by backend feature tests:

### Multi-Tenancy & Authentication
- **User Authentication:** Full user lifecycle managed via Laravel Fortify: registration, session login/logout, password reset, email verification, two-factor authentication, and passkeys (`app/Providers/FortifyServiceProvider.php`, `routes/settings.php`).
- **Workspace Modeling:** Workspaces with unique slugs (`workspaces` table, `app/Models/Workspace.php`).
- **Workspace Memberships:** Many-to-many relationship linking users to workspaces with a string role column (`app/Models/WorkspaceMembership.php`).
- **Workspace Ownership Invariant:** Enforced via a PostgreSQL partial unique index: at most one owner per workspace (`workspace_memberships_one_owner_per_workspace`).
- **Workspace Route Access:** Middleware `EnsureWorkspaceAccess` (`workspace.access`) verifies that the authenticated user belongs to the requested workspace (`app/Http/Middleware/EnsureWorkspaceAccess.php`).
- **Active Workspace Session:** Selected workspace tracked in user session and exposed via shared Inertia props (`HandleInertiaRequests.php`, `WorkspaceController.php`).

### Workflow Management & Lifecycle
- **Tenant-Scoped Workflows:** Workflows belong to a workspace via `workspace_id` foreign key with cascade deletion and a composite unique constraint `['workspace_id', 'slug']` (`app/Models/Workflow.php`).
- **Starter Workflow Creation:** Transactional creation of a workflow, an initial draft (Version 1), three starter nodes (Trigger, Action, End), and two connecting edges (`app/Actions/Workflows/CreateWorkflow.php`).
- **Workflow Versioning:** Version status tracking via `WorkflowVersionStatus` enum (`draft`, `published`, `archived`) (`app/Models/WorkflowVersion.php`).
- **Version Database Invariants:** Enforced via PostgreSQL partial unique indexes:
  - Exactly one draft version per workflow (`workflow_versions_one_draft_per_workflow`).
  - Exactly one published version per workflow (`workflow_versions_one_published_per_workflow`).
- **Draft Editing:** Replace-all update of draft nodes and edges executed inside a database transaction (`app/Actions/Workflows/UpdateWorkflowDraft.php`).
- **Publishing Lifecycle:** `PublishWorkflowVersion` acquires pessimistic row locks (`lockForUpdate()`), validates the graph structure via `ValidateWorkflowGraph`, archives the previously published version, and marks the draft as published with `published_at` timestamp (`app/Actions/Workflows/PublishWorkflowVersion.php`).
- **Draft Creation from Published Version:** `CreateWorkflowDraft` acquires pessimistic row locks, verifies no active draft exists, clones nodes and edges from the currently published version with fresh UUID keys, and remaps edge references (`app/Actions/Workflows/CreateWorkflowDraft.php`).
- **Read-Only Version History:** Controller loads requested versions and passes them to Inertia; non-draft versions are rendered as read-only canvas snapshots (`app/Http/Controllers/WorkflowController.php`).

### Visual Workflow Builder
- **React Flow Canvas:** Interactive visual editor powered by `@xyflow/react` (`resources/js/pages/workflows/show.tsx`).
- **Custom Node Components:** Custom UI representations for `TriggerNode`, `ActionNode`, and `EndNode` (`resources/js/components/workflow/`).
- **Draft State Synchronization:** Node additions, edge connections, deletions, and canvas movement tracked in React state with dirty-snapshot detection.
- **Client Actions:** One-click operations to save draft, publish draft, create new draft, or open active draft.

### Graph Persistence
- **Node Persistence:** Nodes stored with UUID keys (`node_key`), enum type (`trigger`, `action`, `end`), `jsonb` configuration, and `jsonb` position (`workflow_nodes` table).
- **Edge Persistence:** Edges stored with UUID endpoints (`source_node_key`, `target_node_key`) and nullable `jsonb` conditions (`workflow_edges` table).
- **Composite Foreign Key Integrity:** PostgreSQL-specific foreign key constraints enforce that edges reference valid nodes within the exact same workflow version (`create_workflow_edges_table.php`).

---

## 3. Current Request & Architecture Flow

The existing application architecture follows a structured pipeline:

```text
HTTP Request
  └─► Middleware: auth, verified, workspace.access
        └─► Scoped Route Model Binding: {workspace} -> {workflow:slug} -> {version}
              └─► Form Request Validation: StoreWorkflowRequest / UpdateWorkflowDraftRequest
                    └─► Controller: WorkspaceController / WorkflowController
                          └─► Single-Purpose Action: CreateWorkflow / UpdateWorkflowDraft / PublishWorkflowVersion
                                └─► Database Transaction & Eloquent Model Operations (PostgreSQL)
                                      └─► Resource Transformation: WorkflowResource
                                            └─► Inertia Response -> React 19 Page
```

- **Routes (`routes/web.php`):** Define endpoints grouped by authentication and `workspace.access` middleware with `->scopeBindings()`.
- **Controllers (`app/Http/Controllers/`):** Thin orchestrators delegating business operations to Actions.
- **Actions (`app/Actions/Workflows/`):** Self-contained domain operations encapsulating database transactions and business invariants.
- **Validation (`app/Actions/Workflows/ValidateWorkflowGraph.php`):** Structural validation executing Kahn's algorithm for acyclicity and BFS for reachability.

---

## 4. Quality Gate Status (P0.1 Verification)

The project quality gates were evaluated in Milestone P0.1 across eight distinct checks: **two passing, five failing, and one unconfigured**. The failing and unconfigured checks must be remediated in Milestone M0.

| Quality Gate | Command | Result | Details / Root Cause |
|---|---|---|---|
| **Backend Test Suite** | `php artisan test` | **PASS** | 127 tests passed (422 assertions, 65.34s) running against PostgreSQL `atlas_test` on port 5458. |
| **Frontend Production Build** | `npm run build` | **PASS** | Client assets compiled successfully via Vite in 33.27s (2,473 modules transformed). |
| **TypeScript Checks** | `npm run types:check` (`tsc --noEmit`) | **FAIL** | Exit code 1: `tsconfig.json(111,31): error TS5103: Invalid value for '--ignoreDeprecations'`. Commit `eeb18cf` introduced `"ignoreDeprecations": "6.0"`, which is not supported by TypeScript 5.7. |
| **PHP Static Analysis** | `composer run types:check` (`phpstan analyse`) | **FAIL** | Exit code 1: **76 errors** under Larastan Level 7 (generic collection types, missing relation return types, strict comparison mismatches). |
| **PHP Code Style** | `composer run lint:check` (`pint --test`) | **FAIL** | Exit code 1: **26 files** failed code style standards (braces, import order, spacing). |
| **Frontend Linter** | `npm run lint:check` (`eslint .`) | **FAIL** | Exit code 1: **7 errors** across 2 files (`workflows/show.tsx` and `workspaces/show.tsx`), including 1 React compiler error (`react-hooks/set-state-in-effect`) and import order/type issues. |
| **Frontend Formatting** | `npm run format:check` (`prettier --check resources/`) | **FAIL** | Exit code 1: **7 files** require formatting fixes. |
| **PostgreSQL CI Setup** | GitHub Actions (`.github/workflows/tests.yml`) | **UNCONFIGURED** | CI does not provision a PostgreSQL container, while `.env.example` defaults to SQLite and `phpunit.xml` points to PostgreSQL on port 5458. |

---

## 5. Known Issues and Technical Debt

### Confirmed Security & Authorization Issues
1. **Workspace Roles Stored but Unenforced:** The `WorkspaceRole` enum (`owner`, `admin`, `member`, `viewer`) is stored on `workspace_memberships`, but no permission matrix is enforced. `EnsureWorkspaceAccess` only checks for membership existence. Form Requests return `authorize(): bool { return true; }`. There is no `WorkflowPolicy`. Any member, including a `viewer`, can create, modify, and publish workflows.
2. **Resource Existence Leaking:** Accessing an unauthorized workspace returns HTTP 403 (`EnsureWorkspaceAccess`), while accessing a workflow not belonging to a workspace returns HTTP 404 (`scopeBindings`), leaking workspace slug existence.

### Confirmed Concurrency & Immutability Issues
3. **Draft Update Concurrency Vulnerability:** `UpdateWorkflowDraft` wraps operations in a transaction but does **not** lock records with `lockForUpdate()`. It checks `$version->status !== WorkflowVersionStatus::Draft` on an in-memory instance without refreshing from the database. A concurrent update request can race with `PublishWorkflowVersion`, creating a vulnerability where draft changes overwrite nodes/edges of a version that is actively being published. (Once an execution engine is introduced, this concurrency gap would extend to in-flight executions pinning a version, but currently the risk is between concurrent editing and publishing).
4. **Draft Update Unhandled 500 Error:** When `UpdateWorkflowDraft` encounters a non-draft status, it throws a raw `\LogicException`, producing an unexpected HTTP 500 error instead of a validated HTTP 422/409 response.
5. **Lack of Enforced Immutability for Published and Archived Versions:** The system relies on application-level checks to prevent editing non-draft versions, but published and archived graph definitions (`workflow_versions`, `workflow_nodes`, and `workflow_edges`) lack an explicit immutability guarantee. The architectural mechanism to enforce immutability (whether through repository boundaries, action guards, or database constraints) remains an open decision to be resolved before execution begins.

### Confirmed Serialization & Validation Issues
6. **Lossy Node Configuration Serialization:** In `resources/js/pages/workflows/show.tsx`, `mapNodes` extracts only `{ label }` from `configuration`, and `serializeGraph` saves `configuration: { label: node.data.label }`. Any actual executable node configuration is permanently erased upon save.
7. **Lossy Edge Condition Serialization:** In `resources/js/pages/workflows/show.tsx`, `serializeGraph` hardcodes `condition: null` on all edges, discarding condition payloads.
8. **Graph Validator Permits Non-Linear Graphs:** `ValidateWorkflowGraph` checks for a connected DAG with 1 Trigger and 1 End, but does not enforce node degrees. It permits fan-out/forking, branching, and diamond graphs.
9. **Incomplete Request Validation:** `UpdateWorkflowDraftRequest` does not prevent duplicate `node_key` values, does not reject duplicate edge tuples, and lacks maximum array size limits.
10. **Workspace Slug Collision Risk:** `CreateWorkspace` uses raw `Str::slug($name)` without a collision resolution loop or empty-slug fallback.

### Confirmed Testing & CI Gaps
11. **Missing HTTP Endpoint Tests:** Feature tests for draft creation (`CreateWorkflowDraftTest`) and publishing (`PublishWorkflowVersionTest`) only test domain Actions in isolation. There are zero HTTP integration tests for `POST /drafts` and `POST /publish`.
12. **Missing Role Authorization Tests:** No tests verify that `viewer` or unauthorized roles are denied mutation access.
13. **CI Database Mismatch:** GitHub Actions runs `composer setup` without a PostgreSQL service container. The raw composite foreign key SQL in `create_workflow_edges_table.php` fails under SQLite.

---

## 6. Execution Readiness Assessment

### Status: NOT READY FOR EXECUTION ENGINE

Atlas is not yet ready to implement the workflow execution engine. Implementing execution now would build upon insecure and unstable assumptions:
1. **Unenforced Roles:** Viewers would be able to trigger executions and external side effects.
2. **Non-Linear Graph Risks:** Users can currently publish branched or diamond graphs that a linear execution engine cannot deterministically run.
3. **Action Node Undefined:** Action nodes have no executable subtype (e.g. `Log`, `HttpRequest`) or validated parameter schema.
4. **Configuration Loss:** Node configuration is stripped on save by the frontend canvas.
5. **Concurrency & Future Mutation Hazards:** Unlocked draft updates currently risk overwriting versions during publishing races; once execution is introduced, this unaddressed vulnerability would risk mutating definitions while executions are in flight.
6. **Broken Quality Gates:** Five of eight quality gate checks are failing and one is unconfigured, removing the safety net required for new development.

---

## 7. Phased Implementation Roadmap (Approved Direction)

Execution development will proceed strictly in phased milestones. Initial execution will support **only linear workflows**:

$$\text{Trigger} \longrightarrow \text{zero or more Actions} \longrightarrow \text{End}$$

All non-linear capabilities (**branching, conditions, parallel branches, loops, AI/OCR nodes, and human approval steps**) are explicitly deferred to later milestones.

### Milestone M0: Foundation Hardening & Quality Gates
- Restore all quality gates to clean green status:
  - Fix `tsconfig.json` `--ignoreDeprecations`.
  - Fix ESLint errors (`react-hooks/set-state-in-effect`, type imports) and Prettier formatting.
  - Fix Pint code style and Larastan Level 7 type errors.
- Implement `WorkflowPolicy` and enforce `WorkspaceRole` authorization on all workflow endpoints.
- Harden `UpdateWorkflowDraft` with pessimistic locking (`lockForUpdate()`) and validation error responses (HTTP 422).
- Establish an architectural enforcement mechanism for published and archived workflow-version immutability (leaving the specific mechanism open for a future architecture decision).
- Add HTTP feature tests for `POST /drafts`, `POST /publish`, and role authorization boundaries.
- Configure PostgreSQL 17 service in `.github/workflows/tests.yml`.
- Add collision resolution to `CreateWorkspace` slug generation.

### Milestone M1: Workflow Definition Reliability
- Enforce strict linear graph validation in `ValidateWorkflowGraph`:
  - Trigger: in-degree = 0, out-degree = 1.
  - End: in-degree = 1, out-degree = 0.
  - Action: in-degree = 1, out-degree = 1.
  - Edge count invariant: $E = V - 1$.
- Refactor React Flow editor (`mapNodes`, `serializeGraph`) to preserve arbitrary node configurations without data loss.
- Define initial executable Action node contract (e.g. `Log` / `Echo`) with schema validation.

### Milestone M2+: Execution Domain Implementation
- Database migrations and models for `workflow_executions` and `workflow_execution_steps`.
- Pinned published version execution.
- Execution state machine and synchronous runner.
