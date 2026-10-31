# Expense & Approvals — Backend Integration Report

The backend now supports the 31 October 2026 Expense & Approvals revision: five document types, Transfer/Petty Cash controls, positive expense lines, CoA validation from budget lines, category mismatch reasons, Cash Advance activity dates with Holiday Calendar-based H+14 working-day due dates, Finance Manager override auditing, approval-chain enforcement, attachments, verification checklist, CDV assignment, Solo/Co-Funding validation, Loan receivable posting, and settlement refund/top-up flows.

Legacy reimbursement records without `document_type_id` remain submit-compatible when they contain a receipt attachment; typed new requests use the document-specific supporting-document checklist.

See the web report in `Frontend-KT/docs/EXPENSE_REVISIONS_2026-10-31_REPORT.md` for the cross-platform delivery matrix.
