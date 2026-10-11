# Go-Live Readiness

## Decision: NOT READY

The isolated backend, frontend, PWA, migration, seeder, dropdown contract, accounting, and cross-module tests are green. Go-live approval is blocked by:

1. Web↔PWA browser E2E has not been executed against an isolated staging session.
2. External e-Bupot/e-Faktur integration has not been validated.
3. Production database engine/version and collation still need explicit confirmation.

## Release checklist before approval

- Provide isolated staging URL and test users for Staff, Supervisor, Finance, and Approver.
- Execute the Web↔PWA scenarios in `WEB-PWA-SYNC-RESULT.md`.
- Confirm production MySQL version, charset, collation, decimal, timezone, and transaction settings.
- Re-run migration, seed, PHPUnit, frontend, PWA, and browser E2E evidence in the release environment.
- Approve rollback plan and release window.
