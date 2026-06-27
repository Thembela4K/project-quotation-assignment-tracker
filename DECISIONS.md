# Architecture Decision Log

## ADR-001: Rebuild From Streamlit To Laravel + MySQL

**Decision:** Replace the original Streamlit implementation with a Laravel + MySQL/MariaDB app.

**Why:** The app needs to be hosted on Hostinger Custom PHP/HTML hosting, support persistent relational data, authentication, roles, document upload, email, reminders, and structured workflows.

**Alternatives considered:** Keep Streamlit; build with another stack. Streamlit was rejected because it was not appropriate for the desired hosted business portal and maintainable multi-module architecture.

**Tradeoffs:** Laravel is more setup-heavy than Streamlit but fits Hostinger/PHP hosting and long-term module growth.

**Do not reverse casually.**

## ADR-002: Use Blade + Tailwind, Not A SPA

**Decision:** Use Laravel Blade pages with Tailwind/Vite.

**Why:** Server-rendered pages are simpler for Hostinger shared PHP hosting, easier to maintain, and sufficient for a corporate internal tool.

**Alternatives considered:** SPA frameworks. Not chosen because there was no strong need and it would add deployment/runtime complexity.

**Tradeoffs:** Some interactivity is more manual, but the app remains straightforward to deploy and debug.

## ADR-003: Public Repo With Private Runtime Data

**Decision:** Keep repository public but exclude sensitive runtime data.

**Why:** The user explicitly wants the project public and safe from credential leakage.

**Rules:** Do not commit `.env`, passwords, API keys, real SMTP app passwords, private database exports, uploaded documents, logs, production secrets, or private files.

**Tradeoffs:** Seeders and docs must stay generic; real organization setup happens in the private database/admin UI.

**Do not reverse casually.**

## ADR-004: Rename Projects To Tender Proposals

**Decision:** Replace the “Project” domain with “Tender Proposal” under Operations.

**Why:** The business flow is about tenders/proposals received from tender portals, not generic project management.

**Alternatives considered:** Keep “Projects” and relabel in UI. Rejected because it would mislead users and the public.

**Tradeoffs:** Older migration names still show project history, but user-facing domain is tender proposals.

## ADR-005: Separate Quotation Requests From Sales Quotations

**Decision:** Keep incoming quotation requests in Operations and client-facing sales quotations in Finance.

**Why:** The business uses both terms differently. Quotation requests are assigned to departments; sales quotations are created for clients.

**Tradeoffs:** More modules/routes, but avoids confusion.

**Do not merge casually.**

## ADR-006: Department Assignment Workflow

**Decision:** Tenders and quotation requests are assigned to departments/users with due dates, instructions, unread state, documents, and submissions.

**Why:** The original core problem is that departments forget assigned tenders/quotations. The system exists to make assignments trackable.

**Tradeoffs:** More workflow tables (`assignments`, `submissions`, `documents`, `reminder_logs`) but better accountability.

## ADR-007: Reviewer Inbox For Returned Work

**Decision:** Department submissions remain tied to the original tender/quotation request and appear for reviewers; they do not become new requests.

**Why:** The user explicitly said returned work should not come back as a new submission disconnected from the original assignment.

**Tradeoffs:** Requires polymorphic submissions and document links, but preserves traceability.

## ADR-008: Remove Scores

**Decision:** Remove system emphasis on scores/ratings in the workflow UI.

**Why:** The system is for reminders, assignments, and tracking; the user did not want scoring language.

**Tradeoffs:** Some legacy DB fields may remain for migration/history compatibility, but UI should not center scoring.

## ADR-009: Roles Without Manager

**Decision:** Supported roles are `super_admin`, `director`, `reception`, `department_user`, and `business_analyst`. No `manager`.

**Why:** The business clarified there is no manager/operational management role needed.

**Tradeoffs:** Permissions are role-method based; future role changes must be deliberate.

**Do not reintroduce `manager` casually.**

## ADR-010: Invitation-Only Users

**Decision:** No public registration. Super admin invites users by email; invited users set passwords through an invitation link.

**Why:** The portal is internal and staff access must be controlled.

**Tradeoffs:** Requires email to onboard users. Local Mailpit mirror was added to make local demos reliable.

**Do not add public registration without approval.**

## ADR-011: OTP Password Reset For Invited Users Only

**Decision:** Forgot password sends a 6-digit OTP only to active users who accepted invitations.

**Why:** Keeps reset flow scoped to legitimate staff and avoids public enumeration.

**Tradeoffs:** Users without accepted invitations cannot use forgot password; admin must invite/resend first.

## ADR-012: One System Mailbox

**Decision:** One configured mailbox sends all CRM notifications, reminders, invitations, reset OTPs, and future system emails.

**Why:** The user asked whether all department mailboxes were needed and chose one sender mailbox. Department mailboxes are recipients only.

**Tradeoffs:** SMTP setup is simpler. Sender reputation/delivery depends on that mailbox.

## ADR-013: Local Mailpit Mirror For Demos

**Decision:** In `APP_ENV=local`, mirror outgoing system emails to Laragon Mailpit when available.

**Why:** Gmail can accept SMTP but delay/filter inbox delivery. The user needs reliable local demonstrations.

**Tradeoffs:** Local sends may appear both in Mailpit and external Gmail. Production is unaffected.

**Do not remove without replacing local demo email strategy.**

## ADR-014: Fixed VAT At 15%

**Decision:** VAT is fixed at 15% in V1, read from `app_settings` default/fallback but not exposed for normal user editing.

**Why:** The user stated VAT is always 15%.

**Tradeoffs:** Easy and consistent. Future VAT changes require a deliberate settings/admin decision.

## ADR-015: Operational Finance, Not Full Accounting

**Decision:** The system replaces QuickBooks-style operational document work, not formal accounting.

**Scope:** Clients, quotations, invoices, payments, expenses, receipts, requisitions, PDFs, reports.

**Out of scope:** Chart of accounts, journals, ledgers, payroll, tax filing, trial balance.

**Do not expand into full accounting without a new plan.**

## ADR-016: Departments Own Client Quotations; Reception Owns Invoices

**Decision:** Departments create/download client sales quotations and send via their own email outside CRM. Reception creates invoices from quotations/job cards.

**Why:** This matches current business workflow. Reception currently uses QuickBooks for invoices; CRM replaces that operational invoicing work.

**Tradeoffs:** CRM tracks quotation documents/statuses but does not send them to clients directly.

**Do not make reception send client quotations or departments create invoices without approval.**

## ADR-017: Job Cards Between Quotation And Invoice

**Decision:** Departments create job cards instead of invoices. Job cards can be marked ready for invoice.

**Why:** The business clarified departments should not invoice; job cards represent department work readiness.

**Tradeoffs:** Adds another workflow entity but models reality better.

## ADR-018: Delivery Notes For IT/Device Delivery

**Decision:** Add delivery notes linked to job cards/sales quotations.

**Why:** IT sometimes delivers consumables/devices and requires reception-issued delivery notes.

**Tradeoffs:** Extra module and numbering (`DN-{YEAR}-{0001}`), but clearer operations trail.

## ADR-019: Requisitions As Approval Workflow

**Decision:** Add requisitions for departments requesting funds from directors.

**Why:** Requisitions were previously sent by email and needed tracking/approval in CRM.

**Tradeoffs:** Requires approval, release, item totals, supplier/document links, and email notifications.

## ADR-020: Use Existing Old CRM Only As Import Reference

**Decision:** Do not reuse the old CodeIgniter/Rise CRM codebase. Import useful data only.

**Why:** The old CRM had useful features but too much generic jargon and poor adoption fit.

**Tradeoffs:** Import command maps selected records only; not every old CRM feature is recreated.

## ADR-021: MIS Assistant Uses Hybrid AI

**Decision:** MIS assistant uses local responder/navigation logic first and remote NVIDIA OpenAI-compatible AI when configured.

**Why:** The user wanted human-like conversation and CRM-aware answers, but the app also needs predictable local behavior and clear API failure messages.

**Alternatives considered:** Fully local hard-coded bot; Gemini free tier; full remote AI takeover.

**Tradeoffs:** More complex pipeline, but safer and more reliable. Remote AI can answer naturally; local logic protects common CRM actions and fallbacks.

## ADR-022: Assistant Actions Are Vetted Navigation, Not Mutations

**Decision:** Assistant can answer and navigate but should not create/edit/delete/approve/send/release funds.

**Why:** Earlier assistant behavior was too eager to act. Mutation workflows need explicit safe UX and permission checks.

**Tradeoffs:** Users may ask it to perform actions; it should open the correct module/workflow rather than silently changing data.

**Do not grant mutation authority casually.**

## ADR-023: Hostinger Custom PHP/HTML Deployment

**Decision:** Use Hostinger Custom PHP/HTML website option.

**Why:** Laravel is a PHP app. WordPress/Website Builder/Node.js options are not appropriate for this codebase.

**Tradeoffs:** Manual upload requires careful public folder mapping and writable `storage`/`bootstrap/cache`.

## ADR-024: Commit And Push After Each Completed Change

**Decision:** Future agents should commit and push completed changes to `main`.

**Why:** The user explicitly requested commits after every change.

**Tradeoffs:** Requires discipline to verify before committing and avoid bundling unrelated changes.

## Unconfirmed / Needs Verification

- Whether production should keep remote MIS AI enabled long term.
- Whether DomPDF should replace current `SimplePdfService`.
- Whether Hostinger will provide cron/SSH or require phpMyAdmin/manual scheduler alternatives.
