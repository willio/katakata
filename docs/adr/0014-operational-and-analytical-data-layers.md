# ADR-0014: Operational and Analytical Data Layers

- Status: Accepted
- Date: 2026-07-29

## Context

Katakata stores a growing article corpus as plain Markdown files. Markdown must remain durable, portable, inspectable, and independent of any database engine.

As the corpus grows, some workloads benefit from specialized storage:

- full-text search
- metadata and archive lookup
- incremental builds
- related-content indexes
- publication and readership reports
- newsletter performance
- Threads engagement analysis

A single database should not be forced to serve both operational and analytical workloads. Neither workload justifies moving canonical content out of Markdown.

## Decision

Katakata adopts separate supporting data layers:

1. Markdown remains canonical.
2. SQLite provides the operational index and transactional application state.
3. DuckDB provides analytical queries over open datasets, primarily Parquet.
4. Published outputs remain derived and reproducible.

## Canonical Layer: Markdown

Markdown files under `content/` are the authoritative record for:

- articles
- drafts
- authors
- durable editorial metadata

No SQLite or DuckDB table may become the only durable copy of canonical content.

## Operational Layer: SQLite

SQLite is approved for workloads that require indexed lookup, full-text search, compact local state, or transactions.

Approved uses include:

- FTS5 full-text search
- article, author, tag, collection, and archive indexes
- metadata caches
- content fingerprints
- incremental build manifests
- backlink and related-content indexes
- sessions and queues if required later

Derived SQLite data must be reconstructable from Markdown and configuration. Operational state that is not derived, such as a future session or queue, must still remain outside canonical editorial content.

The operational index should live under `storage/index/` and must not be committed.

## Analytical Layer: DuckDB

DuckDB is approved for aggregation, reporting, and historical analysis.

Approved uses include:

- publication cadence
- article and author performance
- tag and topic trends
- readership events
- newsletter delivery and engagement
- Threads engagement
- dashboard datasets
- cohort and historical analysis

Analytics should be captured as append-only events and materialized into open formats such as Parquet. DuckDB queries those datasets and may generate disposable summaries.

DuckDB must not participate in the critical publishing path.

## Failure Boundaries

- Publishing must continue when DuckDB is unavailable.
- A missing or corrupt SQLite index must trigger a rebuild, not content loss.
- Analytics ingestion or export failures must not block publication.
- Deleting generated databases must not delete canonical articles.

## Data Flow

```text
Markdown (canonical)
        │
        ▼
Content Engine
        │
        ├──────────────► Renderer / Published Outputs
        │
        ├──────────────► SQLite Operational Index
        │                  ├── Search
        │                  ├── Metadata cache
        │                  └── Build state
        │
        └──────────────► Append-only Events
                           │
                           ▼
                        Parquet
                           │
                           ▼
                        DuckDB
                           │
                           ▼
                     Reports / Insights
```

## Consequences

### Positive

- Markdown ownership and portability remain intact.
- SQLite provides mature FTS5 and indexed operational access.
- DuckDB provides efficient analytical SQL without introducing a separate database server.
- Open Parquet datasets reduce vendor lock-in.
- Operational and analytical failure domains remain isolated.

### Negative

- Two database engines increase implementation and maintenance surface.
- Derived schemas require versioning and rebuild procedures.
- Event and Parquet pipelines add storage-management responsibilities.
- Developers must respect the boundary between canonical, operational, and analytical data.

## Alternatives Considered

### SQLite for all workloads

Rejected. SQLite can support modest reporting, but it is not optimized for large analytical scans and columnar datasets.

### DuckDB for all workloads

Rejected. DuckDB is not the preferred engine for frequent small operational transactions or application state.

### Database-backed canonical content

Rejected. It conflicts with Katakata's plain-Markdown ownership and portability principles.

### No supporting databases

Rejected as a permanent rule. A large article corpus benefits from full-text indexing, incremental builds, and analytical reporting, provided those systems remain derived and optional.

## Implementation Sequence

Phase 6 introduces SQLite:

1. schema and migration policy
2. deterministic index builder
3. FTS5 search
4. metadata and archive indexes
5. incremental build state
6. related-content indexes

Phase 7 introduces DuckDB:

1. append-only event schema
2. event recorder
3. Parquet export and compaction
4. DuckDB query service
5. publication and engagement reports
6. dashboard datasets

## Rule

Markdown owns the work. SQLite accelerates the application. DuckDB explains the history.
