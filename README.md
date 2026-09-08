# Atlas

Atlas is a multi-tenant, AI-powered workflow automation platform for businesses.
It will allow a team to visually build an automation, publish it, and let Atlas execute it while recording the result.

## Product Direction

The central Atlas flow is:

```text
Business workspace
    -> Workflow definition
    -> Visual workflow builder
    -> Workflow engine
    -> Execution
    -> Execution history and monitoring
```

Workflows are composed of nodes and edges. Nodes represent actions such as triggers, OCR, AI processing, validation, storage, notifications, or human approval. Edges describe how execution moves from one node to another.

Atlas is multi-tenant. A workspace owns its workflows, executions, documents, and configuration. Every request and database query that accesses tenant-owned data must enforce the current workspace boundary so one business cannot access another business's data.

## Technology

- Laravel 13 and PHP 8.3+
- React 19 with TypeScript
- Inertia.js for the Laravel and React boundary
- PostgreSQL for application data
- Docker Compose for local PostgreSQL
- Laravel queues for asynchronous work as the execution system grows
- React Flow for the visual workflow builder

## Current Status

The project is currently at the foundation stage. The Laravel application, React/Inertia frontend, authentication foundation, database migrations, and local PostgreSQL container are configured. Workflow, workspace, execution, AI, OCR, and integration features are planned but are not implemented yet.

## Development Setup

Requirements:

- PHP 8.3 or newer
- Composer
- Node.js and npm
- Docker Desktop

Start PostgreSQL:

```bash
docker compose up -d postgres
```

Copy `.env.example` to `.env` if needed, configure the PostgreSQL connection, and then install dependencies, generate the application key, run migrations, and build the frontend:

```bash
composer run setup
```

Run the application:

```bash
composer run dev
```

The `.env` file contains local configuration and must not be committed. Use `.env.example` as the starting point for a new environment.

## Roadmap

1. Foundation
2. Authentication and multi-tenancy
3. Workflow management
4. Visual workflow builder
5. Basic workflow engine
6. Execution history
7. Asynchronous execution and workers
8. Reliability and retries
9. AI and OCR nodes
10. Human approval steps
11. External integrations
12. Retrieval-augmented knowledge features
13. Testing, CI/CD, deployment, observability, and scaling

AI is intentionally planned after the basic workflow lifecycle is reliable:

```text
Define -> Store -> Execute -> Track -> Recover
```

## Documentation

The detailed product and architecture vision is maintained locally in `docs/internal/` and is intentionally excluded from Git. The public project contract, setup instructions, and high-level roadmap belong in this README.

## License

Atlas is currently an internal project. Licensing and contribution guidelines will be added when the project is ready for external collaboration.
