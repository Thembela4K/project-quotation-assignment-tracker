# Project Context

## What The System Does

The Datamatics Eswatini Business Operations Portal is an internal operations platform for managing company work across finance, operations, departments, approvals, documents, tasks, attendance, and reporting.

The system started as a Tender Proposal & Quotation Assignment Tracker and was expanded into a broader business operations portal. The original tender/quotation tracker remains under the Operations module.

The app is not a public client portal. It is for internal Datamatics staff, directors, reception/admin, departments, and business analysis/reporting.

## Users

Primary user groups:

- Super admin / business admin officer
- Directors
- Reception/admin
- Department users in operational departments
- Business analyst

Departments discussed in the project include IT, MIS, GIS, Admin/Reception, and Directors. Real staff names, individual emails, and department mailbox values should remain database-controlled/private and not hard-coded into public seeders.

## Main Roles

Defined in [app/Models/User.php](app/Models/User.php):

- `super_admin`: full system control, users, departments, settings, all modules.
- `director`: approve/reject finance/requisition workflows and view company-wide dashboards/reports.
- `reception`: client setup, invoicing, payments, expenses, operations intake/assignment, funds release where allowed.
- `department_user`: department sales quotation drafts, job cards, assigned tender/quotation request work, own/department tasks.
- `business_analyst`: read-only cross-company operational/reporting visibility.

There is no `manager` role. Earlier plans removed it to match the business structure.

## Main Modules

### Dashboard

Company-wide cards, deadline pressure, workload, finance summaries, operations summaries, and visual reports.

### Clients

Client register, billing details, contacts, and client follow-ups/activities.

### Finance

- Item catalog.
- Sales quotations for clients.
- Job cards created by departments after client approval/work progression.
- Delivery notes for IT-style delivery of consumables/devices.
- Invoices issued by reception/admin.
- Payments and receipts.
- Expenses.

### Operations

- Tender proposals from SPPRA/ESPPRA or similar sources.
- Quotation requests, including picture/image requests.
- Department assignments.
- Original request documents.
- Department submissions with technical/financial/supporting documents.
- Reminders and email notifications.
- Requisitions for department funding requests.

### Requisitions

Departments request funds. Directors/super admin approve or reject. Reception/directors/super admin can release funds according to current permissions.

### Tasks

Task assignment, comments, attachments, priorities, statuses, due dates, and workload visibility.

### Attendance

Clock in/out, daily records, duration, missing clock-out review, and admin/reception correction.

### Suppliers / Purchases

Supplier register and purchase records linked to procurement/requisition/expense workflows.

### Documents

Central document register with metadata, categories, preview/download, uploaded-by, and text indexing where supported.

### Approvals / Notifications

Approval inbox and persistent in-app notifications.

### Reports

Finance, operations, task/workload, attendance, requisitions, supplier/expense, and document activity summaries.

### Admin

Users, invitation/resend, departments, and settings.

### MIS Assistant

CRM-aware chat assistant in the header. It can answer from allowed CRM records and return safe navigation actions. It uses a remote NVIDIA OpenAI-compatible API when configured, plus local responder logic for basic answers/navigation and better resilience.

## Business Rules

- VAT is always 15% in V1.
- This is not full accounting: no chart of accounts, journals, ledgers, payroll, formal tax filing, or trial balance.
- Sales quotations and quotation requests are different:
  - Sales quotations are client-facing finance documents created by departments/reception within the Finance module.
  - Quotation requests are incoming work/request assignments under Operations.
- Departments create/download sales quotations but do not send them from the CRM. They send through their own department emails and may CC directors/reception outside the system.
- Reception does not send client quotations.
- Departments do not create invoices.
- Departments create job cards after client quotation approval/work completion readiness.
- Reception/admin prepares invoices from department quotations/job cards.
- IT/device/consumable delivery can require delivery notes from reception.
- One director approval is enough where approval is needed.
- User onboarding is invite-only. There is no public registration.
- Password reset is OTP-based and only works for active users who accepted an invitation.
- Department users see work scoped to their department/assignments; directors/reception/super admin/business analyst have broader visibility according to permissions.
- Assignment/reminder emails should include portal links and needed context, with local Mailpit mirroring for demos.
- Tender reminders: 5 days before due deadline.
- Quotation request reminders: 24 hours/1 day before due date; overdue marking applies when quotation request deadline passes without response.
- Operations assignments become unread badges on relevant nav tabs until viewed/opened.
- SPPRA/ESPPRA shortcut is for authorized roles and should not be presented as a user-editable setting UI unless explicitly requested.

## Important Workflows

### Tender Proposal Assignment

1. Reception/admin receives/downloads a tender proposal/request from SPPRA/ESPPRA or another source.
2. Reception/admin creates a tender proposal in the Operations module.
3. Original tender/request document is uploaded.
4. Brief and dates are entered:
   - due/closing date
   - optional site visit date
   - optional clarification date/window
5. Reception/admin assigns to a department/user with due date/instructions.
6. Department receives in-app notification and email.
7. Department views the record, previews/downloads documents, works on response.
8. Department uploads response documents, commonly technical proposal and financial proposal.
9. Department submits as Draft or Finished.
10. Returned submission stays tied to the original tender/quotation request and appears for reviewers/directors/reception.

### Quotation Request Assignment

Similar to tender proposal assignment, but for incoming quotation requests. Documents may be PDFs or images.

### Sales Quotation To Job Card To Invoice

1. Department creates a client sales quotation draft in Finance.
2. Sales quotation can be submitted/approved according to current status workflow.
3. Department downloads/prints quotation and sends via its own email outside CRM.
4. When accepted/work progresses, department creates a job card from the quotation.
5. If delivery is required, delivery note workflow is used.
6. When ready, department marks job card ready for invoice.
7. Reception/admin creates/prepares invoice from the quotation/job card.
8. Reception records payments against invoice.

### Requisition

1. Department creates requisition for requested funds/items.
2. Requisition can include items, payment type totals, supplier, attachments, needed-by date, priority, and purpose.
3. Submitted requisition appears for directors/super admin approval.
4. Approved funds can be released by allowed users.
5. Email notifications and logs track submitted/decision events.

### User Invitation And Password Reset

1. Super admin enters invited user email, role, and department.
2. User receives invitation link by email.
3. User accepts invite, sets name/username/password.
4. Accepted users can change password inside portal.
5. Forgot password sends a 6-digit OTP to the invited/accepted email only.

## Current Project Status

The app is implemented as a Laravel + MySQL portal with the main modules above. Tests exist and currently cover the core implemented workflows.

Recent completed work preserved in this repo includes:

- Laravel rebuild from Streamlit.
- Tender Proposal & Quotation Request operations workflow.
- Department assignments, unread badges, document preview/download, submissions.
- SMTP email templates, logs, reminders, and local Mailpit mirror.
- Corporate UI with sidebar navigation and module subnav.
- CRM finance modules: clients, sales quotations, invoices, payments, expenses, item catalog.
- Requisitions.
- Tasks, attendance, suppliers, purchases, document registry, approvals, reports, audit logs.
- Job cards and delivery notes.
- MIS assistant with conversation history, remote NVIDIA provider, local fallback, and safe navigation rules.
- User invitations and OTP password reset.
- Old CRM import command for selected useful records.

## Stakeholder Requirements From Thread

- Corporate, clean, non-childish UI.
- Desktop/large-monitor first, but responsive; avoid overlapping cards/filters.
- Sidebar module navigation with sub-navigation per module.
- White/corporate visual language aligned with Datamatics logo colors.
- Charts/visuals in dashboards and reports; hover stats where possible.
- Tables should have pagination.
- Navbar/header and sidebar should feel modern and professional.
- Public repo must not contain secrets, real `.env`, database exports, uploaded docs, private documents, passwords, or API keys.
- Local demo must show email behavior; use Mailpit if Gmail is delayed/filtered.
- Hostinger deployment target is Custom PHP/HTML website.
- Users should be invited rather than self-registering.
- Business operations should be easy to filter and track.
- The app should replace some QuickBooks-style operational work, but not become full accounting.

## Unconfirmed / Needs Verification

- Exact production Hostinger folder name/path must be verified per hosting account.
- Exact real user/department email data should be confirmed in private database/admin screens, not public docs.
- Whether remote MIS assistant API should remain enabled in production depends on API key/quota/cost decisions.
- Whether `barryvdh/laravel-dompdf` should eventually replace or supplement `SimplePdfService` is not finalized.
- Whether all old CRM data needed for import has been imported should be verified against the old database source.
