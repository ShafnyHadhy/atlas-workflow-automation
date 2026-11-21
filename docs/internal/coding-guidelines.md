# Atlas — Coding Guidelines & Engineering Standards

**Document Version:** 1.0  
**Status:** Approved Engineering Standards  
**Baseline Implementation:** `docs/internal/current-state.md`  
**Architecture Reference:** `docs/internal/architecture.md`  
**Roadmap Reference:** `docs/internal/implementation-roadmap.md`  

---

## 1. General Engineering Principles

These guidelines define the engineering practices, naming conventions, and quality standards for developing Atlas. All contributors must adhere to these principles to maintain a clean, secure, and verifiable codebase.

### 1.1 Core Principles
1. **Readability Over Cleverness:** Code is read and audited far more frequently than it is written. Use clear, self-explanatory names, standard Laravel and React conventions, and explicit logic over cryptic abbreviations or esoteric language features.
2. **Explicit Behavior Over Unnecessary Abstraction:** Favor direct, understandable domain logic over speculative design patterns, multi-layered service wrappers, or premature micro-abstractions.
3. **Strict Separation of Concerns:**
   - **Controllers:** Thin HTTP orchestrators handling routing, authorization, and responses.
   - **Actions:** Self-contained domain operations encapsulating transactions and business rules.
   - **Models:** Eloquent persistence definitions, relationships, and property casts.
   - **Frontend Components:** Focused React presentation and canvas state management.
4. **Avoid Premature Optimization:** Implement synchronous, deterministic, and verifiable functionality first. Complex abstractions—such as distributed queues, streaming WebSockets, or dynamic expression compilers—must not be introduced until scheduled by the implementation roadmap.
5. **Fail Fast and Durably:** Validate data early at application boundaries. When errors occur during execution, persist failure states and error traces durably without corrupting prior audit history.

---

## 2. Backend — Laravel / PHP

The backend runtime is **PHP 8.3+** using **Laravel 13.x**.

### 2.1 Naming Conventions
- **Classes & Enums:** `PascalCase` matching file names exactly:
  - Models: `Workflow`, `WorkflowVersion`, `Workspace`.
  - Actions: Imperative verb-noun phrases (`CreateWorkflow`, `PublishWorkflowVersion`, `ExecuteWorkflowAction`).
  - Controllers: Singular noun + Controller (`WorkflowController`, `WorkspaceController`).
  - Policies: Singular model + Policy (`WorkflowPolicy`, `WorkspacePolicy`).
  - Enums: Singular PascalCase (`WorkflowNodeType`, `WorkflowVersionStatus`, `WorkspaceRole`).
- **Methods:** `camelCase` (`execute()`, `generateUniqueSlug()`, `validateConfiguration()`).
- **Variables & Properties:** `camelCase` (`$workspace`, `$publishedVersion`, `$selectedVersion`).
- **Database Columns & Foreign Keys:** `snake_case` (`workflow_version_id`, `node_key`, `published_at`).
- **Namespaces:** Match directory structure under `app/` (PSR-4 compliant).

### 2.2 Controller Responsibilities
Controllers must remain thin and focused:
- **Dependency Injection:** Inject required Action classes, Form Requests, and services directly into controller method parameters.
- **Route Model Binding:** Rely on scoped route model binding (`{workspace}`, `{workflow:slug}`, `{version}`).
- **No Direct Domain Transactions:** Controllers must **never** contain multi-table database transactions, complex graph traversal logic, or business mutation logic.
- **Delegation:** Always delegate state changes to Action classes.
- **Responses:**
  - View requests return `Inertia::render('domain/page', $props)`.
  - State mutations return redirect responses (`redirect()->route(...)` or `redirect()->back()`).
  - Execution API endpoints return JSON or Inertia responses with explicit HTTP status codes (201 Created, 200 OK, 422 Unprocessable Content, 403 Forbidden).

### 2.3 Action-Class Architecture and the `execute()` Convention
Domain operations must be encapsulated in dedicated single-purpose Action classes under `app/Actions/{Domain}/`:
- **The `execute()` Method:** Every Action class must expose a single primary public method named `execute()`.
- **Parameter Types:** Define explicit scalar, object, or strongly typed array parameters.
- **Calling Convention:** Callers should use PHP 8 named arguments when invoking actions for clarity:
  ```php
  $workflow = $createWorkflow->execute(
      workspace: $workspace,
      name: $request->string('name')->toString(),
      description: $request->input('description'),
  );
  ```
- **Transaction Ownership:** When an operation mutates multiple tables, the Action class owns the `DB::transaction(...)` boundary and any pessimistic row-level locking.
- **Exceptions:** Actions must throw domain-specific exceptions or `Illuminate\Validation\ValidationException` (HTTP 422). Never throw unhandled raw `\LogicException` or cause unintended 500 errors.

### 2.4 Model Responsibilities & Relationship Conventions
Eloquent models represent database tables and must remain focused on data structure:
- **Mass Assignment:** Declare an explicit `$fillable` array on every model. Do not use unconstrained `$guarded = []`.
- **Property Casts:** Define casts using the Laravel 11/13 standard `protected function casts(): array` method:
  ```php
  protected function casts(): array
  {
      return [
          'type' => WorkflowNodeType::class,
          'configuration' => 'array',
          'position' => 'array',
          'published_at' => 'datetime',
      ];
  }
  ```
- **Explicit Relationship Types:** Declare strict PHP return types on all relationship methods to satisfy Larastan Level 7:
  ```php
  /**
   * @return BelongsTo<Workspace, $this>
   */
  public function workspace(): BelongsTo
  {
      return $this->belongsTo(Workspace::class);
  }
  ```
- **No Orchestration in Models:** Models must not orchestrate multi-model workflow transitions or trigger external side effects.

### 2.5 Form Requests and Validation
- All HTTP request payloads must be validated via Form Request classes (`app/Http/Requests/{Domain}/`).
- Validation rules must be comprehensive: enforce string lengths, UUID formats, JSON structure, and array elements.
- Form Requests must integrate with authorization policies in their `authorize()` method:
  ```php
  public function authorize(): bool
  {
      return $this->user()->can('update', $this->route('workflow'));
  }
  ```

### 2.6 Authorization and Policies
- All domain authorization rules must be centralized in Policy classes (`app/Policies/`).
- Workspace role permissions (`WorkspaceRole`: `owner`, `admin`, `member`, `viewer`) must be formally enforced via `WorkflowPolicy`:
  - `owner` & `admin`: Full management (create, edit, publish, execute, delete).
  - `member`: Create, edit drafts, execute workflows.
  - `viewer`: Read-only access; strictly barred from creating, editing, publishing, or executing workflows.
- Policy checks must be evaluated in Form Requests (`$this->authorize(...)`) and controllers (`$this->authorize(...)`).

### 2.7 Database Transactions & Concurrency Locking
- **Consistent Lock Hierarchy:** Where operations race (e.g. draft updates vs. publishing vs. execution creation), actions must serialize against the **parent `Workflow` row** using `lockForUpdate()`:
  ```php
  $workflow = Workflow::query()
      ->whereKey($workflowId)
      ->lockForUpdate()
      ->firstOrFail();
  ```
- **Lock Ordering:** Always lock parent resources first and child resources second to prevent database deadlocks.
- **No Monolithic Execution Transactions:** Workflow execution runs must **never** be wrapped in a single long transaction. Use discrete transactions for step lifecycle transitions to ensure step audit history is durably preserved even when an action fails.

### 2.8 Type Declarations and Static Analysis Standards
- **Strict Typing:** All classes, methods, and functions must have explicit parameter types and return type declarations.
- **Generics & Array Shapes:** Provide PHPDoc annotations for collections, maps, and JSON payloads:
  ```php
  /**
   * @param array<string, mixed> $input
   * @return array<string, mixed>
   */
  public function handle(array $input, array $configuration, array $context): array;
  ```
- **Zero New Errors:** All new or modified PHP code must pass Larastan Level 7 static analysis (`composer run types:check`) with zero new baseline violations.

---

## 3. Frontend — React / TypeScript

The frontend stack uses **React 19.x**, **TypeScript 5.7+**, **Inertia.js v3**, **Tailwind CSS v4**, and **Radix UI**.

### 3.1 Component Organization & File Structure
```text
resources/js/
├── components/
│   ├── ui/               # Reusable primitive UI components (buttons, dialogs, inputs)
│   └── workflow/         # Domain-specific components (trigger-node, action-node, end-node)
├── hooks/                # Custom React hooks (use-appearance, use-two-factor-auth)
├── layouts/              # Inertia page layouts (app-layout, auth-layout)
├── pages/                # Inertia page components matching route hierarchy
│   ├── workflows/        # Workflow show, canvas, execution views
│   └── workspaces/       # Workspace dashboard and settings
└── types/                # Shared TypeScript contracts and interfaces
```
- **Component Naming:** File names and component functions use `kebab-case` for files and `PascalCase` for React components (`TriggerNode`, `ActionNode`, `ExecutionInspector`).

### 3.2 TypeScript Strictness & Type Definitions
- `tsconfig.json` has `strict: true` enabled.
- **No `any` Types:** Use explicit interfaces, strict union types, or `unknown` with type guards.
- **Type-Only Imports:** Use `import type { ... } from '...'` for types to satisfy ESLint and compiler optimizations.
- **Domain Interfaces:** Maintain shared models in `resources/js/types/` (e.g. `workflow.ts`, `workspace.ts`). Frontend types must accurately mirror backend resource schemas.

### 3.3 Props and Component Responsibilities
- Define an explicit `type Props = { ... }` for every page component.
- Keep presentational components modular. Avoid monolithic files that mix canvas dragging, execution polling, complex forms, and dialog states.
- Deconstruct complex pages into small, focused sub-components.

### 3.4 Hooks and State Management Rules
- Use standard React hooks: `useState`, `useCallback`, `useMemo`, `useRef`.
- **React 19 Compiler Compliance:**  
  > [!IMPORTANT]
  > **No Synchronous `setState` in `useEffect`:** Never invoke synchronous state setters directly inside `useEffect` on mount (`react-hooks/set-state-in-effect`). Compute initial state during `useState` initialization or derive state during render.
- Keep local canvas viewport state in React Flow, and domain state in Inertia page props.

### 3.5 API and Server Data Handling via Inertia.js
- Use Inertia's `router` (`router.post`, `router.put`, `router.delete`) or `useForm()` for server interactions.
- Use Laravel Wayfinder route helpers (`route('workspaces.workflows.show', ...)`) for type-safe URL generation.
- **No Direct `fetch` or `axios` for Standard Mutations:** Maintain server-driven Inertia state rather than managing a separate decoupled REST API client.

### 3.6 Form Handling, Loading, and Error States
- Use Inertia's `useForm` hook for form state, submission handling, and validation errors.
- Always display validation errors returned by the backend under the respective form fields.
- Provide accessible loading indicators (spinners, disabled buttons) during form submissions.
- Supply clean, meaningful empty states when lists or tables contain no records (e.g. "No execution history recorded yet").

### 3.7 React Flow Workflow Editor Conventions
- Canvas operations use `@xyflow/react`.
- **Node Configuration Preservation:**  
  > [!WARNING]
  > **Canvas Serialization Rule:** Node configuration must **never** be stripped during canvas state changes or save serialization. The full `configuration` JSON object must be preserved when nodes are edited and saved.
- **Read-Only Mode:** Respect the `isEditable` flag: when inspecting published or archived versions, disable node dragging, edge connections, and canvas mutations.

---

## 4. Database & Data Integrity

The persistence layer is **PostgreSQL 17.x**.

### 4.1 Schema & Naming Conventions
- **Tables:** Plural `snake_case` (`workflows`, `workflow_versions`, `workflow_nodes`, `workflow_executions`, `workflow_execution_steps`).
- **Primary Keys:** Standard auto-incrementing `id` (`bigint`).
- **Foreign Keys:** `{singular_table}_id` (`workspace_id`, `workflow_id`, `workflow_version_id`).
- **UUID Keys:** `node_key` on `workflow_nodes` is a UUID string unique within its `workflow_version_id`.
- **Timestamps:** `created_at`, `updated_at`, `published_at`, `started_at`, `finished_at` (`timestamp with time zone`).

### 4.2 Foreign Keys, Constraints, and Cascade Rules
- **Explicit Foreign Keys:** All relational links must be formally constrained in migrations.
- **Tenant Root Cascades:** Child tables directly owned by a tenant cascade on delete when the workspace or workflow is deleted:
  `$table->foreignId('workspace_id')->constrained()->cascadeOnDelete();`
- **Execution Deletion Cascading (MVP):** Foreign keys for workflow executions and steps enforce `ON DELETE CASCADE` during MVP (M0–M4); soft deletion and audit retention are deferred to M6:
  `$table->foreignId('workflow_version_id')->constrained()->cascadeOnDelete();`
- **Composite Foreign Keys:** Child entities referencing compound parent keys must declare composite foreign key constraints in raw SQL migrations (e.g. `workflow_edges` referencing `(workflow_version_id, node_key)` on `workflow_nodes`).

### 4.3 Indexes and Partial Unique Indexes
- Index all foreign keys and frequently filtered columns.
- Use PostgreSQL partial unique indexes for conditionally unique states:
  - At most one published version per workflow:
    `CREATE UNIQUE INDEX workflow_versions_one_published_per_workflow ON workflow_versions (workflow_id) WHERE status = 'published';`
  - Idempotency key uniqueness per workflow:
    `CREATE UNIQUE INDEX uq_executions_idempotency ON workflow_executions (workspace_id, workflow_id, idempotency_key) WHERE idempotency_key IS NOT NULL;`

### 4.4 Migration Practices
- Migrations must be strictly reversible: always provide complete `up()` and `down()` implementations.
- Never edit an existing migration that has already been merged or committed. Create a new migration for schema changes.
- Ensure all migration DDL statements execute cleanly on PostgreSQL 17.

---

## 5. Testing Standards

Backend tests use **PHPUnit 12.x** with Laravel testing utilities (`Tests\TestCase`, `RefreshDatabase`).

### 5.1 Test Structure & Organization
```text
tests/
├── Feature/
│   ├── Auth/                  # Fortify authentication and security tests
│   ├── Workflows/             # Workflow CRUD, draft editing, publishing tests
│   ├── WorkflowNodeEdge/      # Node and edge composite constraint tests
│   └── Workspace/             # Workspace tenancy and membership tests
├── Unit/                      # Isolated pure unit tests
└── TestCase.php               # Base test case
```

### 5.2 Test Class and Method Conventions
- Test classes extend `Tests\TestCase` and use `Illuminate\Foundation\Testing\RefreshDatabase`.
- Test classes are named `{Subject}Test.php` in `tests/Feature/{Domain}/`.
- Test method names must be descriptive snake_case starting with `test_`:
  ```php
  public function test_workspace_member_can_create_a_workflow(): void
  public function test_viewer_role_cannot_publish_workflow(): void
  public function test_duplicate_idempotency_key_returns_existing_execution(): void
  ```

### 5.3 PostgreSQL Test Environment Requirements
- **PostgreSQL Compatibility:** Tests run against a dedicated PostgreSQL database (`atlas_test`, port 5458).
- **No SQLite Shortcuts:** Never write SQLite-specific test assertions or rely on SQLite in-memory databases. Database features (JSONB columns, composite foreign keys, partial unique indexes) require real PostgreSQL testing.

### 5.4 Test Case Coverage Matrix
Every feature must include tests covering:
1. **Positive Cases (Happy Path):** Valid inputs, successful state transitions, expected return payloads.
2. **Negative Cases (Validation & Failures):** Missing required fields, invalid JSON, payload size violations ($>1$ MB), malformed graph structures.
3. **Authorization & Security Boundaries:** Verify that `viewer` members and non-members receive HTTP 403 Forbidden.
4. **Concurrency & Race Conditions:** Concurrent draft saves, duplicate idempotency key submissions.
5. **Regression Tests:** Add regression tests whenever an issue is identified and resolved.

### 5.5 Frontend Testing Standards Based on Existing Tooling
> [!IMPORTANT]
> **No Unauthorized Test Frameworks:** Atlas currently does **not** install or configure a standalone frontend test runner (such as Vitest or Jest). Contributors must **not** invent or install an unauthorized test runner.
> 
> Frontend verification is performed through:
> 1. **Static Type Checking:** `npm run types:check` (`tsc --noEmit`).
> 2. **Linter & Style Verification:** `npm run lint:check` (`eslint .`) and `npm run format:check` (`prettier --check resources/`).
> 3. **Production Compilation:** `npm run build` (`vite build`).
> 4. **Inertia Integration Tests:** Laravel feature tests asserting Inertia component names and passed props contracts:
>    ```php
>    $response->assertInertia(fn (Assert $page) => $page
>        ->component('workflows/show')
>        ->has('workflow')
>        ->has('selected_version.nodes', 3)
>    );
>    ```

---

## 6. Code Quality & Quality Gate Verification

All code submitted to Atlas must pass the eight authoritative quality gates before merging.

### 6.1 The Eight Quality Gate Verification Commands
Contributors must verify their changes locally using the exact repository commands:

```bash
# 1. Backend Automated Tests (PostgreSQL test database on port 5458)
php artisan test

# 2. Frontend Production Asset Build
npm run build

# 3. TypeScript Compilation Checks
npm run types:check

# 4. PHP Static Analysis (Larastan Level 7)
composer run types:check

# 5. PHP Code Style (Laravel Pint)
composer run lint:check

# 6. Frontend Linter (ESLint)
npm run lint:check

# 7. Frontend Formatting (Prettier)
npm run format:check

# 8. Full CI Suite Runner
composer run ci:check
```

### 6.2 Quality Standards for Contributions
- **Zero New Errors:** New or modified code must not introduce additional static-analysis errors or quality gate violations.
- **No Blanket Suppressions:** Do not disable linters or analyzers with `@ts-ignore`, `/* eslint-disable */`, or `@phpstan-ignore` without documented architectural authorization.
- **Pre-Commit Linting:** Run `vendor/bin/pint` and `npm run format` prior to submitting code.

---

## 7. Security & Multi-Tenancy Standards

### 7.1 Workspace Tenant Isolation
- **Derivation Rule:** `workspace_id` must **always** be derived from the verified route parameter resolved by `EnsureWorkspaceAccess`. Never accept or trust a `workspace_id` provided in request bodies or query strings.
- **Relational Route Scoping:** Always apply `->scopeBindings()` to routes so that nested parameters (`{workflow:slug}`, `{version}`) are scoped strictly to the parent workspace relationship.
- **Cross-Tenant Assertions:** Test suites must explicitly assert that users in Workspace A cannot read, update, or execute workflows in Workspace B.

### 7.2 Safe Database Access
- Use Eloquent or parameterized query bindings. Raw string interpolation inside `DB::raw(...)` or `DB::statement(...)` is strictly forbidden.
- Always escape and sanitize dynamic values.

### 7.3 Logging & Sensitive Data Protection
- Use Laravel's structured logger: `Log::info('Action executed', ['workflow_id' => $id])`.
- **Never Log Secrets:** Never log passwords, API tokens, session cookies, TOTP seeds, WebAuthn credentials, or sensitive business payload data.

---

## 8. Practical Development Rules

### 8.1 Scope Discipline
- **Focused, Reviewable Commits:** Keep pull requests focused on a single milestone deliverable.
- **No Unrelated Refactoring:** Do not reformat or reorganize unrelated files in the same branch or commit.
- **Follow Existing Patterns:** Adhere to established Controller $\to$ Action $\to$ Model conventions unless an explicit architectural change has been approved.

### 8.2 Change Verification Protocol
Before considering any task or milestone complete:
1. Run `git status --short -uall` to confirm only expected files were modified.
2. Inspect the complete `git diff` to ensure no debug statements, temporary scripts, or stray files remain.
3. Execute all relevant quality gate commands locally against PostgreSQL.
4. Update corresponding test suites and internal documentation when domain behavior changes.
