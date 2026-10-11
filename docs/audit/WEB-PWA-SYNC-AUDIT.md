# Web–PWA Synchronization Audit

## Shared backend

Both clients target the Laravel `/v1` API family and expose expense, approval, timesheet, fiscal-year, and reporting screens. This supports a single backend workflow in principle.

## Findings

| Area | Status | Evidence |
|---|---|---|
| API namespace | PASS | Web and PWA source use the shared `/v1` backend convention |
| Fiscal-year context | NOT TESTED | Web API context and PWA client carry fiscal-year context; live cross-client query not tested |
| Expense/approval IDs | NOT TESTED | Both clients call backend workflow concepts; identical record/status behavior needs runtime test |
| Authentication storage | NOT TESTED / High | Web API code supports token fallbacks while PWA uses `kt_token`; login interoperability was not verified against an isolated staging auth session |
| Approval configuration | PARTIAL | Backend `ApprovalWorkflowService` and approval-center routes are authoritative; PWA behavior not fully exercised |
| Attachments/audit log | NOT TESTED | Backend attachment route exists; cross-client upload/display not executed |
| Offline/cache | NOT TESTED | PWA cache behavior requires device/browser test |
| Hardcoded transaction options | PASS | PWA timesheet form now uses API options only and shows an empty state |

## Required sync test

Create one expense and one timesheet in Web, read and approve in PWA, then pay/post in Web. Repeat in the opposite direction. This remains a staging/browser test. The available browser reached the deployment login page, but no isolated staging URL/session and test credentials were available, so no transaction or sensitive data was submitted.

