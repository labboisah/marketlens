# Security Policy

MarketLens handles business operations, sales records, stock movement history,
debt ledgers, user accounts, and branch-level reporting. Security and data
isolation are product requirements, not optional hardening.

## Reporting Security Issues

Do not open public issues for vulnerabilities. Report suspected security issues
privately to the project maintainers or business owner responsible for the
deployment.

Include:

- A concise description of the issue.
- Steps to reproduce when safe.
- Affected application or package.
- Potential impact.
- Any suggested mitigation.

## Core Security Principles

- Enforce tenant isolation in the Platform.
- Enforce branch isolation across local and synchronized data.
- Validate every external input at the application boundary.
- Authorize every protected action based on role and business context.
- Keep Business Server local APIs protected from unauthorized LAN clients.
- Never allow Client Apps to write directly to SQLite.
- Never allow the Monitoring App to connect directly to a Business Server.
- Keep transactional records immutable and auditable.
- Store secrets outside Git.

## Secrets and Environment Values

Never commit:

- Real `.env` files.
- API keys.
- Database credentials.
- Production URLs with embedded credentials.
- Private keys or certificates.
- Generated tokens.
- Customer data.
- Local SQLite databases.

Commit only documented example environment files such as `.env.example`.

## Dependency Security

Dependencies should come from trusted package ecosystems and be kept current.
Security updates should be prioritized when they affect authentication,
authorization, networking, storage, cryptography, or request parsing.

## Vulnerability Handling

When a vulnerability is confirmed:

1. Assess affected applications and deployments.
2. Prepare a focused fix.
3. Add regression tests where possible.
4. Rotate exposed credentials if needed.
5. Document operational mitigation steps.
6. Release the fix through the appropriate deployment channel.
