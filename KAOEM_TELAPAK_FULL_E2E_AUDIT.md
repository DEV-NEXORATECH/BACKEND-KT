# Kaoem Telapak ERP — Full E2E Audit

Tanggal audit: 2026-11-05 (Asia/Jakarta)  
Branch kerja: `codex/full-e2e-audit` pada Backend-KT, Frontend-KT, dan kaoem-telapak-pwa.

## Executive summary

- PWA fiscal-year scoping diperbaiki pada `kaoem-telapak-pwa/src/core/network/api-client.ts`. Endpoint global (login, auth, fiscal-year master) tidak diberi scope; endpoint transaksi, reporting, procurement, accounting, expense, timesheet, asset, tax, dan project diberi `fiscal_year_id` dari session bila belum ditentukan caller.
- Backend suite: **75 passed, 9 failed** (642 assertions). Karena itu release belum memenuhi Definition of Done.
- Frontend build: **PASS**. Frontend lint: **PASS dengan warning** (termasuk unused code, missing hook dependencies, unreachable code, duplicate object keys, dan indikasi dummy/demo rows).
- PWA build: **PASS**; existing PWA test: **PASS**. Browser E2E, UAT staging, production-like database, offline mobile, dan external DJP integration: **NOT TESTED** karena environment/credential tidak tersedia.
- Tidak ada data dummy atau perubahan destruktif yang dibuat.

## Evidence and known failures

| Area | Evidence | Status | Severity | Perbaikan / tindak lanjut |
|---|---|---|---|---|
| PWA fiscal-year scoping | `src/core/network/api-client.ts` had `shouldScopeFiscalYear()` hard-coded `false` | FIXED | High | Path allowlist implemented; verify with staging API contract tests |
| AR approval status | `AccountsReceivableCustomerInvoiceTest`: expected `approved`, actual `in_progress` | FAIL | High | Inspect approval matrix resolution and AR listing status projection |
| Approval center | `ApprovalCenterAndAttachmentTest`: pending item module was null | FAIL | High | Normalize module/source mapping in pending serializer |
| Expense two-level approval | `ApprovalWorkflowTest`: configured budget holder rejected as non-approver | FAIL | High | Fix approver stage/role resolution and add matrix regression test |
| Cash advance | 4 failures: submit response status was null | FAIL | High | Inspect cash-advance state transition and response resource contract |
| Dropdown fixture | `DropdownContractTest`: donor fixture not found | FAIL | Medium | Fix test fixture isolation/seed setup before judging endpoint behavior |
| Test encryption | `ExampleTest`: unsupported cipher/incorrect key length | FAIL | Medium | Generate valid APP_KEY in `.env.testing` or CI setup |
| Frontend quality | lint passes with warnings | PARTIAL | Medium | Remove demo rows/unused handlers and fix hook dependencies/duplicate keys |
| External integrations | No verified DJP/API credentials | NOT TESTED | High | Stage-only integration test required; export is not evidence of API integration |

## Audit matrix

Legend: PASS = tested and working; FAIL = proven failure; PARTIAL = only part verified; NOT IMPLEMENTED = not found; NOT APPLICABLE = not relevant; NOT TESTED = environment or scenario unavailable.

| Modul | Fitur | Backend | Frontend | PWA | API | Database | E2E | Status | Severity | Perbaikan |
|---|---|---|---|---|---|---|---|---|---|---|
| Dashboard | Dashboard/KPI/financial/budget/approval/activity/notification | PASS | PARTIAL | PARTIAL | PASS | PASS | NOT TESTED | PARTIAL | Medium | Browser/UAT filters pending |
| Master Finance | Currencies, FX rates, fiscal years, periods, holidays, tax, banks, payment methods | PASS | PARTIAL | NOT TESTED | PASS | PASS | NOT TESTED | PARTIAL | Medium | UI and cross-year staging test |
| Master People | Members/Anggota, staff, positions, departments, organization | PASS | PARTIAL | NOT TESTED | PASS | PASS | NOT TESTED | PARTIAL | Medium | Verify record-level scope |
| Master Expenses | Expense categories, document types | PASS | PARTIAL | NOT TESTED | PASS | PASS | NOT TESTED | PARTIAL | Medium | Verify active/inactive and references |
| Master Assets | Asset categories | PASS | PARTIAL | NOT TESTED | PASS | PASS | NOT TESTED | PARTIAL | Medium | Verify deletion/reference rules |
| Master Procurement | Suppliers, vendor categories, items/services, UOM, procurement categories, delivery methods, payment terms | PASS | PARTIAL | NOT TESTED | PASS | PASS | NOT TESTED | PARTIAL | Medium | Verify uniqueness/import/export |
| Donor & Grant | Integrated donor & grant dashboard | PASS | PASS | NOT TESTED | PASS | PASS | PASS | PASS | Low | Continue browser regression |
| Donor & Grant | Project timeline/workplan | PASS | PARTIAL | NOT TESTED | PASS | PASS | PARTIAL | PARTIAL | High | Add PWA and approval E2E |
| Donor & Grant | Project frameworks | PASS | PARTIAL | NOT TESTED | PASS | PASS | NOT TESTED | PARTIAL | Medium | Validate target/actual boundaries |
| Donor & Grant | Grant agreements | PASS | PARTIAL | NOT TESTED | PASS | PASS | PARTIAL | PARTIAL | High | Verify grant period and ceiling |
| Donor & Grant | Grant reporting/donor reporting | PASS | PARTIAL | NOT TESTED | PASS | PASS | PARTIAL | PARTIAL | High | Validate source traceability/export |
| Budget | Planning | PASS | PARTIAL | NOT TESTED | PASS | PASS | PASS | PARTIAL | High | Browser approval validation |
| Budget | Monitoring/commitment/actual | PASS | PARTIAL | NOT TESTED | PASS | PASS | PASS | PARTIAL | High | Verify no commitment double count |
| Budget | Forecast | PASS | PARTIAL | NOT TESTED | PASS | PASS | NOT TESTED | PARTIAL | Medium | Document source and formula |
| Budget | Reallocation | PASS | PARTIAL | NOT TESTED | PASS | PASS | NOT TESTED | PARTIAL | High | Approval and audit regression |
| Expenses | Requests & verification | PASS | PARTIAL | NOT TESTED | PASS | PASS | PASS | PARTIAL | High | Resolve approval failures |
| Expenses | Advances & settlements | FAIL | PARTIAL | NOT TESTED | FAIL | PASS | FAIL | FAIL | High | Fix cash advance transition/response |
| Expenses | Monitoring/staff aging | PASS | PARTIAL | NOT TESTED | PASS | PASS | NOT TESTED | PARTIAL | Medium | Verify totals against GL |
| Accounting | Dashboard, COA, journal, GL | PASS | PASS | NOT TESTED | PASS | PASS | PASS | PARTIAL | High | Add browser and closed-period paths |
| Accounting | AP/AR workflows and invoices | PARTIAL | PARTIAL | NOT TESTED | PARTIAL | PASS | FAIL | FAIL | High | AR approval projection failure |
| Accounting | Bank reconciliation | PASS | PARTIAL | NOT TESTED | PASS | PASS | PASS | PARTIAL | High | Add duplicate/concurrent matching test |
| Accounting | Fixed asset/depreciation | PASS | PARTIAL | NOT TESTED | PASS | PASS | PASS | PARTIAL | High | Verify precision and period edge cases |
| Accounting | Prepaid expense | PASS | PARTIAL | NOT TESTED | PASS | PASS | NOT TESTED | PARTIAL | Medium | Verify amortization schedule |
| Accounting | Period closing | PASS | PARTIAL | NOT TESTED | PASS | PASS | PASS | PARTIAL | High | Add authorization exception test |
| Procurement | PR → RFQ → CBA → PO | PASS | PARTIAL | NOT TESTED | PASS | PASS | PASS | PARTIAL | High | Browser workflow and duplicate submit |
| Procurement | GRN → supplier invoice → AP | PASS | PARTIAL | NOT TESTED | PASS | PASS | PASS | PARTIAL | High | Verify partial delivery/invoice boundaries |
| Procurement | Waiver/contracts/SCN | PASS | PARTIAL | NOT TESTED | PASS | PASS | NOT TESTED | PARTIAL | Medium | Verify attachment and notification |
| Taxes | Setup/dashboard/transactions | PASS | PARTIAL | NOT TESTED | PASS | PASS | PASS | PARTIAL | High | Add rounding/effective-date cases |
| Taxes | PPh/PPN/e-Bupot/e-Faktur | PASS | PARTIAL | NOT IMPLEMENTED | PASS | PASS | NOT TESTED | PARTIAL | High | Treat current output as export only; verify DJP API separately |
| Taxes | Calendar/reports/calculator | PASS | PARTIAL | NOT TESTED | PASS | PASS | PASS | PARTIAL | Medium | Browser export and traceability |
| Timesheet | Internal dashboard/my/team/approval | PASS | PARTIAL | NOT TESTED | PASS | PASS | PASS | PARTIAL | High | Add overlap/concurrent edit tests |
| Timesheet | External/verification | PASS | PARTIAL | NOT TESTED | PASS | PASS | PARTIAL | PARTIAL | High | Verify assignment and approval lock |
| Timesheet | Project timesheet/reports | PASS | PARTIAL | NOT TESTED | PASS | PASS | PARTIAL | PARTIAL | High | Verify no double cost posting |
| Reports | Financial/budget/donor/project/procurement/tax/management/GL | PASS | PARTIAL | NOT TESTED | PASS | PASS | PASS | PARTIAL | High | Browser filters and drilldown evidence |
| Administration | Staff/org/department/location/positions | PASS | PARTIAL | NOT TESTED | PASS | PASS | NOT TESTED | PARTIAL | Medium | Verify permissions by record |
| Administration | Repository/contracts/attachments/audit log | PASS | PARTIAL | NOT TESTED | PASS | PASS | PARTIAL | PARTIAL | High | Upload/security and versioning tests |
| Settings | Users, roles, permissions, workflows | PASS | PARTIAL | PARTIAL | PASS | PASS | PARTIAL | PARTIAL | Critical | Resolve approval matrix failures; URL/API authorization regression |
| Settings | System settings/integrations/notifications | PASS | PARTIAL | PARTIAL | PASS | PASS | NOT TESTED | PARTIAL | High | Verify delivery, revocation, and external integrations |
| PWA | Login/logout/session/cache cleanup | PASS | NOT TESTED | PASS | PASS | NOT APPLICABLE | PARTIAL | PARTIAL | High | Add device/browser tests |
| PWA | Offline/install/service worker | PASS | NOT TESTED | PARTIAL | PASS | NOT APPLICABLE | NOT TESTED | NOT TESTED | High | Test standalone Android/iOS and sensitive-cache behavior |
| PWA | Web ↔ backend ↔ PWA synchronization | PASS | NOT TESTED | PARTIAL | PASS | PASS | NOT TESTED | NOT TESTED | Critical | Requires staging credentials and multi-client E2E |

## Changed files

- `kaoem-telapak-pwa/src/core/network/api-client.ts`: implemented fiscal-year path scoping.
- Existing local changes were preserved in all repositories; they are not attributed to this audit.

## Validation commands

- `kaoem-telapak-pwa`: `npm run build` PASS; `npm test` PASS after removing incompatible ad-hoc test.
- `Frontend-KT`: `npm run build` PASS; `npm run lint` PASS with warnings.
- `Bakcend-KT`: `php artisan test` 75 passed / 9 failed.

## Remaining blockers and regression risks

1. Do not release until the 9 backend failures are fixed and rerun on a clean test database.
2. Browser E2E/UAT staging is still required for every critical workflow and all listed viewport sizes.
3. Fiscal-year allowlist must be compared against the complete PWA datasource inventory in staging; new endpoint families must be added explicitly.
4. External DJP integration, offline queue semantics, concurrent posting, rate limiting, backup/recovery, and production deployment were not testable here.
5. No commit or pull request was created yet; changes are isolated on the audit branches and remain uncommitted to avoid bundling pre-existing local work.
