# MarketLens Security Foundations

Security in MarketLens is built around strict ownership boundaries: the Business Server
owns local operations, the Platform owns cloud administration and monitoring
authorization, Client Apps never open SQLite, and the Monitoring App connects
only to the Platform.

## Required Gates

- No merge with failing CI.
- No untested critical business rules.
- No production migrations without rollback review.
- No breaking API changes without versioning.
- No synchronization changes without compatibility tests.
- No secrets, production tokens, Firebase credentials, customer data, private
  keys, local databases, or generated credentials in Git.

## Multi-Tenant Isolation

- Every Platform tenant-owned query must scope by `business_id` or
  `business_uuid`.
- Authorization policies must check business membership before returning data.
- Background jobs, reports, notifications, exports, and sync processing must
  preserve tenant scope.
- Tests must prove users cannot access another business by changing IDs.

## Branch Isolation

- Branch-owned records must scope by `branch_id` or `branch_uuid`.
- Users with branch-limited access must only see allowed branches.
- Business Server data is branch-local, but multi-branch summaries on the Platform must
  still enforce branch authorization.

## Password Hashing

- Use modern framework password hashing for Platform users.
- Use strong local password hashing for Business Server operational users.
- Never synchronize password hashes.
- Never log passwords, password hashes, password reset tokens, or local session
  tokens.

## Authentication

### Local User Authentication

- Business Server authenticates operational users locally.
- Business Server remains usable for daily operations without the Platform.
- Session tokens must expire and be revocable.
- Client Apps authenticate through the Business Server; they must never authenticate by
  reading local storage or SQLite.

### Platform User Authentication

- Platform authenticates business owners, support users, and Monitoring App
  users.
- Monitoring users are not operational Business Server users unless explicitly
  synchronized as limited profiles for reporting.
- Platform APIs must use policies, validation, and rate limits.

### Device Authentication

- Business Server devices require Platform activation before cloud sync.
- Client App devices require Business Server pairing before local operational access.
- Device credentials must be revocable.
- Lost or stolen devices must be revoked promptly.

## Sessions

- Sessions must expire.
- Sensitive actions should require re-authentication where appropriate.
- Business Server and Client App local sessions must be tied to authorized devices.
- Monitoring App tokens must be stored with secure storage.

## Role-Based Permissions

- Business Server enforces operational permissions for local users.
- Client App UI may hide features, but the Business Server API must enforce permission
  checks server-side.
- Platform enforces owner, support, and monitoring permissions.
- Permission checks require automated tests for critical workflows.

## Audit Logs

Audit important actions:

- Login success/failure.
- User, role, permission, and device changes.
- Sales cancellation, returns, refunds, and reprints.
- Stock adjustments and approvals.
- Payment changes and ledger corrections.
- Sync conflicts, rejections, and device failures.
- Platform subscription, branch, business, and monitoring-user changes.

Audit logs should be append-only and protected from ordinary user edits.

## Approval Workflows

- Risky operations should support approval workflows.
- Approval actions must record actor, time, decision, reason, and target record.
- Approved operations should still create immutable business records or
  correction records.

## Electron Security

Business Server and Client App must use:

- `contextIsolation: true`
- `nodeIntegration: false`
- `sandbox: true`
- `webSecurity: true`

Renderer processes must not have unrestricted Node.js access. Native access,
printing, scanner access, secure session storage, and filesystem operations must
go through controlled preload APIs.

## Secure Preload APIs

- Expose the smallest possible typed API.
- Validate IPC payloads.
- Do not expose arbitrary filesystem, shell, SQLite, or network primitives.
- Do not store secrets in renderer local storage.

## SQL Injection Prevention

- Use query builders, ORMs, prepared statements, and parameter binding.
- Do not concatenate user input into SQL.
- Validate sorting and filtering fields against allow-lists.
- Add tests for high-risk filters and report endpoints.

## API Validation

- Validate every request at the boundary.
- Use stable error formats and trace IDs.
- Reject unknown or dangerous fields for write operations.
- Never return stack traces or SQL errors to clients.

## Rate Limiting

Apply rate limits to:

- Login endpoints.
- Device activation and pairing.
- Synchronization uploads.
- Monitoring APIs.
- Password reset and support-facing actions.

## Backup Encryption

- Business Server backups should be encrypted where they may leave the local machine.
- Backup metadata must not expose secrets.
- Restore operations must be audited.
- Backup retention must be configurable.

## Secret Management

- Use environment variables, secret managers, or OS-protected credential stores.
- Commit only `.env.example` files.
- Rotate secrets if exposure is suspected.
- Do not place secrets in logs, documentation examples, screenshots, or test
  fixtures.

## Dependency Scanning

- Run dependency audits in CI.
- Review high and critical vulnerabilities before release.
- Pin or lock dependencies for release builds.
- Treat Electron, native modules, Laravel, Flutter, and Firebase dependencies as
  security-sensitive.

## Log Redaction

Logs must redact:

- Passwords.
- Tokens.
- Device credentials.
- Authorization headers.
- Customer financial details where not required for support.
- Full payment identifiers.

## Secure Updates and Installers

- Desktop installers must be signed before production distribution.
- Update packages must be signed and delivered over HTTPS.
- Automatic updates must verify authenticity before install.
- Release channels should separate development, staging, and production.

## Monitoring App Security

- Store authentication tokens in `flutter_secure_storage`.
- Sensitive screens may require biometric re-authentication.
- Push notifications must not expose sensitive financial information on locked
  screens by default.
- Lost devices must be revocable through Platform user/session management.

## Data Export and Deletion

- Data export must be tenant-scoped and branch-aware.
- Export actions should be audited.
- Deletion requests must distinguish master records, immutable transactions,
  replicated records, backups, and legal retention requirements.
- Immutable business transactions should be archived or retained according to
  policy rather than deleted casually.
