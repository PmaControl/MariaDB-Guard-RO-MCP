# 7011 - Install Feedback

## Description
This variant adds an installation feedback endpoint so MCP clients can report deployment metadata back to the server.

## Branch
`feature/7011-install-feedback`

## Port
`7011`

## VHost
`/etc/apache2/sites-available/mcp-mariadb-7011.conf`

## Deployment Directory
`/var/www/mcp-7011`

## Implementation Details
- New endpoint: `POST /install-feedback`
- Accepted payload:
  - `version` (string)
  - `client` (string)
  - `metadata` (object)
- Stored fields:
  - source IP
  - user-agent
  - timestamp
  - payload content
- Log format: JSONL

## Files Changed
- `src/InstallFeedback.php`
- `src/App.php`
- `public/index.php`
- `tests/bootstrap.php`
- `tests/InstallFeedbackTest.php`
- `install.sh`
- `.env.sample`
- `docs/security_hardening_progress.md`
- `docs/7011-feedback.md`

## Configuration
```bash
./install.sh \
  --install-dir /var/www/mcp-7011 \
  --http-port 7011 \
  --db-host 10.68.68.111 \
  --db-port 3306 \
  --db-name pmacontrol \
  --db-user cline \
  --db-pass cline \
  --mcp-token my_token \
  --install-feedback-log /var/www/mcp-7011/install_feedback_7011.log \
  --allow-cidr 10.68.68.0/24
```

## Test Procedure
1. Send a JSON payload to `POST /install-feedback`.
2. Verify HTTP `200` response.
3. Verify JSONL entry contains source IP, version, client, metadata.

## Test Results
Deployment host: `10.68.68.73`

1. Endpoint call:
   - `POST /install-feedback` with JSON payload:
     - `version=1.0.0`
     - `client=mcp-inspector`
     - `metadata={os, transport}`
   - Response: `{ "ok": true, "message": "Install feedback recorded" }`

2. Persistence check:
   - Verified JSONL entry in `/var/www/mcp-7011/install_feedback_7011.log`
   - Stored fields include source IP, user-agent, version, client, metadata.

## Logs
- Feedback log: `/var/www/mcp-7011/install_feedback_7011.log`
- Apache logs:
  - `/var/log/apache2/mcp_mariadb_7011_access.log`
  - `/var/log/apache2/mcp_mariadb_7011_error.log`

## Success/Failure
Status: `success`

## Limitations
- Feedback endpoint is append-only and unauthenticated by default; add auth/rate-limit if needed.
