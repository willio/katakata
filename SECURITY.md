# Security

## Supported versions

Katakata is pre-1.0. Only the `main` branch receives security fixes. There are no stable release lines to backport to.

## Reporting a vulnerability

Report vulnerabilities through a [private security advisory](https://github.com/willio/katakata/security/advisories/new) on the willio/katakata repository. Do not open public issues for unpatched vulnerabilities.

Include in a report:

- The affected component or route, and the commit or date of the code you tested.
- Steps to reproduce, with expected and actual behavior.
- The potential impact (what an attacker can read, modify, or disrupt).
- Any suggested mitigation, if you have one.

## Response expectations

Katakata is a maintainer-side project. Reports are handled on a best-effort basis, without a guaranteed response time. You will receive acknowledgement once the report has been read, and a note when the fix lands on `main`.

## Security model

The design documents below define the boundaries Katakata enforces. Read them before assessing a finding:

- Authentication: invite-only accounts and passkeys — [ADR 0008](docs/adr/0008-invite-only-authentication.md)
- Secrets: application-managed, stored outside Git and logs — [ADR 0011](docs/adr/0011-application-managed-secrets.md)
- Backup data sensitivity: archives contain credentials-derived material and personal data — [backups.md](docs/subsystems/backups.md)
- Deployment-only configuration: what is configuration versus content — [settings.md](docs/subsystems/settings.md)
