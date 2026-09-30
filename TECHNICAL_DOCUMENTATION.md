# Technical Documentation - Backend KT

Updated: 2026-09-29

## Architecture

Laravel JSON API using Sanctum, permission middleware, controllers, services, Eloquent, and MySQL. Routes are in routes/api.php; controllers are in app/Http/Controllers; models are in app/Models; RBAC assembly is in app/Services/Rbac/RbacPayloadBuilder.php. The web frontend and Kaoem Telapak PWA use this API.

## Authentication and RBAC

Authentication: POST /login, GET /rbac/me, POST /logout.

The RBAC response contains user, employee_id, worker_type, role, permissions, menus, and timesheet_access. User management uses GET /users, PUT /users/{id}/classification, POST /users/{id}/password/generate, and PUT /users/{id}/active. These require user.manage.

## Schema

Migration 2026_09_29_000003_add_user_worker_classification adds users.worker_type, users.external_party_name, and users.contract_reference. Values are internal, external, or consultant. External and consultant users require party/firm and contract reference; internal users clear these fields.

Timesheet entries include employee_id, user_id, worker_type, entry_date, hours, description, work_area, workstream, donor_id, program_id, project_id, activity_id, department_id, vendor_name, contract_reference, invoice_reference, billing_mode, fee_total, work_days, rate_per_day, rate_per_hour, payable_amount, prepared_signature, approved_signature, and approval metadata.

Timesheet migrations are 2026_09_29_000001_add_consultant_billing_to_timesheet_entries and 2026_09_29_000002_add_external_and_signature_fields_to_timesheet_entries.

## Timesheet API

Base path: /v1/timesheets.

- GET /entries (timesheet.view)
- POST /entries (timesheet.create)
- PUT /entries/{id} (timesheet.update)
- POST /entries/{id}/submit (timesheet.submit)
- POST /entries/{id}/approve and /reject (timesheet.approve)
- POST /entries/post-labor-cost (timesheet.approve)

GET supports worker_type, scope=mine, status, project_id, program_id, donor_id, employee_id, start_date, and end_date. Create supports project/activity, external references, consultant billing, and prepared signature fields.

Self-service requests resolve employee_id from the authenticated user or matching employee email and enforce the account worker_type. Managers and administrators can use permitted broader scopes. Consultant daily billing caps payable hours at 8; hourly billing pays overtime.

Master-data options: /v1/master/employees?options=true, /projects?options=true, /activities?options=true, /donors?options=true, and /programs?options=true.

## Configuration and operation

Required environment values: APP_URL, DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD, and CORS_ALLOWED_ORIGINS.

    php artisan serve
    php artisan migrate
    php artisan queue:work

Run php artisan migrate after deployment. Never commit production secrets or generated passwords.

## Module map

| Module | Main backend responsibility |
|---|---|
| Dashboard | Summary KPIs, fiscal year context, charts, and recent activity |
| Master Data | Organizations, employees, departments, projects, donors, accounts, vendors, tax setup, and reusable options |
| Accounting | Chart of accounts, journal, general ledger, AP, AR, banking, reconciliation, fixed assets, period closing, and payments |
| Donor and Grant | Donors, grants, programs/projects, budgets, monitoring, and reporting |
| Expenses and Approvals | Requests, reimbursements, cash advances, settlement, approvals, verification, payment, and monitoring |
| Procurement | Purchase requests, RFQ/CBA, purchase orders, contracts, goods receipts, supplier invoices, waivers, and workflow review |
| Taxes | Transactions, PPh, VAT, e-Bupot, e-Faktur, calendar, reports, and calculator |
| Timesheet | Internal, external, consultant, project, approval, labor cost posting, and reports |
| Reports | Financial, budget, donor/grant, project, procurement, tax, management, aging, cash/bank, and custom reports |
| Administration and Settings | Audit logs, contracts, approval matrices, notifications, users, roles, integrations, and system settings |

Each module is protected by module-specific permissions such as accounting.view, tax.view, expense.view, procurement.view, and timesheet.view.
