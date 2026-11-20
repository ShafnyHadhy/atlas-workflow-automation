# Atlas Product and Architecture Vision

## Core Idea

Atlas is a multi-tenant, AI-powered workflow automation SaaS for businesses.

The product promise is:

> A business can visually build an automation, publish it, and let Atlas execute that automation automatically.

The primary customer is a business or team represented by a workspace (tenant). Each workspace must have strict data isolation from every other workspace.

## Product Model

```text
Atlas
├── Business workspaces
│   ├── Users and roles
│   ├── Workflows
│   ├── Executions
│   └── Documents
└── Atlas platform
    ├── React user interface
    ├── Workflow engine
    ├── Queue workers
    ├── PostgreSQL
    └── Redis, when introduced
```

A user registers and receives access to a workspace. The user can later invite other users and assign roles such as administrator, accountant, or manager.

## Workflow Definition

A workflow describes what should happen. It is not the same thing as an execution, which records what actually happened for one trigger.

```text
Workflow
├── Nodes
│   ├── Trigger
│   ├── OCR
│   ├── AI extraction
│   ├── Validation
│   └── Notification
└── Edges
    ├── Trigger -> OCR
    ├── OCR -> AI extraction
    └── AI extraction -> Validation
```

The visual builder will use React Flow. The editor creates and changes nodes and edges; Laravel stores a validated, versioned workflow definition.

Published workflow versions should remain immutable. This prevents a workflow edit from changing an execution that is already running.

## Example Workflow

```text
Trigger
  -> Receive invoice
  -> OCR
  -> AI extraction
  -> Validate
  -> Amount greater than $10,000?
       ├── No  -> Save invoice -> Notify
       └── Yes -> Manager approval -> Save invoice -> Notify
```

## Workflow Engine

The visual builder answers, "What should happen?" The workflow engine answers, "How do we execute it?"

The initial engine should support a small, deterministic set of node types and an explicit execution state. Later it can add retries, timeouts, branching, parallel work, idempotency, and resumability.

Important reliability questions include:

- What happens when a node fails?
- Which failures are retryable?
- What happens if a worker crashes?
- How are duplicate triggers prevented or handled?
- What definition does an existing execution use after a workflow is edited?
- Can independent nodes run in parallel?
- How can a failed execution be resumed safely?
- How are external side effects made idempotent?

## AI and OCR

AI is a capability inside the workflow engine, not the product boundary itself. Planned node types include OCR, AI extraction, AI classification, and AI validation.

Example:

```text
Invoice -> OCR -> extracted text -> AI -> structured invoice data -> validation
```

The platform should establish workflow storage, execution tracking, failure handling, and observability before adding AI. An AI node cannot compensate for an unreliable execution system.

## Human Approval

Some workflows must pause for a person:

```text
AI extracts $25,000
  -> Rule: amount greater than $10,000
  -> Manager approval
  -> Continue after approval
```

This requires a durable waiting state, authorization checks, an approval record, and a secure way to resume the correct execution exactly once.

## Execution History and Monitoring

An execution is one run of a published workflow. Its history should eventually show both the overall result and each node's state.

```text
Execution #1026
  Trigger       completed
  OCR           completed
  AI extraction failed
  Validation    not started
  Notification  not started
```

Overall execution states may include `running`, `completed`, `failed`, `waiting`, and `cancelled`. Each state transition should be durable and auditable.

## Tenant Isolation

Tenant isolation is a security requirement, not only an organizational feature.

Every tenant-owned record should have a workspace relationship, and application code should derive the current workspace from authenticated context rather than trusting a workspace ID submitted by the browser. Authorization policies, query scopes, and tests must verify that a user from one workspace cannot read or mutate another workspace's records.

## Planned Build Phases

1. Foundation
2. Authentication and multi-tenancy
3. Workflow management
4. Visual workflow builder
5. Basic workflow engine
6. Execution history
7. Asynchronous execution and workers
8. Reliability
9. AI and OCR
10. Human approval
11. Integrations
12. Retrieval-augmented knowledge
13. Testing, CI/CD, deployment, observability, and scaling

The first meaningful system milestone is:

```text
Define workflow -> Store workflow -> Execute workflow -> Track execution -> Handle failure
```

## Architectural Principle

Build the smallest reliable workflow lifecycle before adding integrations or AI. Each new capability should fit the same boundaries: workspace ownership, a versioned definition, an explicit execution state, durable history, and testable failure behavior.
