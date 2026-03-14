# Engine Roadmap

This document tracks engines beyond the current validated core.

## Validated Core
- MariaDB
- MySQL
- Percona Server

## In Progress

### TiDB
- real cluster validation started
- MCP tools validated on `v8.5.5`
- dedicated guards and full E2E matrix still incomplete

### Vitess
- partial support through `vttestserver:mysql80`
- some guards are meaningful, some are not on that runtime

### SingleStore
- partial support
- validated manually on `ghcr.io/singlestore-labs/singlestoredb-dev:0.2.30`
- `latest` image is not portable enough to be used as the default validation target

## Roadmap Candidate

### ClickHouse
- dedicated backend work started
- tested against a real cluster `26.2.4.23`
- current access model uses the HTTP API (`8123`), not the native protocol (`9000`)
- treat it as a dedicated integration effort, not as a drop-in MySQL engine
- next steps:
  - complete E2E guard coverage
  - document read-only account model
  - validate timeout / explain / process introspection behavior under load

## Other MySQL-Protocol Candidates
- StarRocks
- Apache Doris
- OceanBase
- PolarDB-X

## Selection Rules
Any future engine must satisfy all of the following:
- stable MySQL-compatible protocol
- safe read-only operating model
- enough metadata exposure for schema, indexes, explain/plan, and process inspection
- reproducible test infrastructure (Docker or real cluster)
