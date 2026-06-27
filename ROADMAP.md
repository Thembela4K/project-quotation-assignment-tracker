# Roadmap

## Completed

### Foundation

- Rebuilt the app as Laravel + MySQL/MariaDB.
- Added Blade + Tailwind + Vite frontend.
- Added authentication with no public registration.
- Added module sidebar and sub-navigation.
- Added corporate Datamatics Eswatini branding and layout.
- Added public-repo safety rules in README and `.gitignore`.

### Operations Module

- Tender proposal workflow.
- Quotation request workflow.
- Assignment to departments/users.
- Due dates, instructions, unread/viewed tracking.
- Important tender dates.
- Original request/tender document uploads.
- Department submissions with draft/finished statuses.
- Technical/financial/supporting document categories.
- Reviewer access for directors/reception/admin.
- SPPRA/ESPPRA shortcut support.
- Deadline reminders and manual Send Due Reminders button.

### Finance Module

- Clients and contacts.
- Client follow-ups.
- Item catalog.
- Sales quotations with 15% VAT calculation.
- Director/super admin approval flow for sales quotations.
- Invoices linked to sales quotations/job cards.
- Payments and invoice balance/status recalculation.
- Expenses.
- Generated PDFs/print views for sales quotations, invoices, receipts/payments, and requisitions.

### Job Cards And Delivery Notes

- Departments create job cards instead of invoices.
- Job cards link to sales quotations, clients, departments, and invoices.
- Job card statuses include Draft, In Progress, Completed, Ready for Invoice, Invoiced, Cancelled.
- Reception/admin can create invoice from ready job card.
- Delivery notes link to job cards/sales quotations and support issue/delivered/cancelled statuses.

### Requisitions

- Department requisition workflow for funds requests.
- Requisition line items and totals by payment type.
- Director/super admin approval/rejection.
- Funds release flow.
- Requisition emails and logs.
- Requisition print/PDF generation.

### CRM Core

- Tasks with comments, statuses, priorities, due dates, and department/user scoping.
- Attendance clock in/out and correction workflow.
- Suppliers and purchase records.
- Central document registry with preview/download.
- Audit logs.
- Persistent CRM notifications.
- Reports and visual dashboards.

### Email

- SMTP configuration via `.env`.
- Branded assignment, reminder, requisition, test, invitation, and OTP email templates.
- Local Mailpit mirroring in `APP_ENV=local` for reliable presentations.
- `php artisan mail:test` command.

### Users And Security

- Roles: super admin, director, reception, department user, business analyst.
- No manager role.
- Invite-only user onboarding.
- User invitation resend.
- Username or email login.
- Profile password change.
- Forgot password OTP for accepted invited users only.

### MIS Assistant

- Header chat widget.
- Conversation history and new chat.
- Remote NVIDIA OpenAI-compatible provider.
- Local responder/navigation fallback.
- CRM context builder.
- Safe navigation action executor.
- Document indexing command.
- Guardrails against unnecessary navigation and mutation.

### Legacy Import

- `crm:import-old-data` command for selected old RISE CRM records:
  - catalog items
  - invoices/payments with valid client links
  - expenses
  - tasks
  - attendance

## Currently In Progress

- Documentation/memory preservation for future Codex chats.
- Continued refinement of MIS assistant behavior as presentation issues are discovered.
- Continued CRM module polish and workflow fit based on user testing.

## Remaining Work

### High Priority

- Verify all module pages at common desktop widths for overflow/overlap.
- Review all table/filter panels for responsive wrapping and pagination.
- End-to-end manual demo script:
  - invite user
  - accept invitation from Mailpit
  - reset password with OTP
  - create tender proposal and assign
  - department submission
  - create sales quotation
  - create job card
  - create delivery note where needed
  - create invoice and payment
  - create requisition and approve/release
  - test MIS assistant queries
- Confirm Hostinger deployment path and public folder mapping with actual account.
- Confirm production cron/SSH availability for scheduler. If unavailable, improve manual reminder/notification controls.

### Medium Priority

- Improve official PDF layout beyond compact text PDFs if needed.
- Add stronger automated tests for job card and delivery note workflows if not already covered deeply.
- Add more tests around user invitation resend and expired invitation handling.
- Add tests around Mailpit mirror behavior with config fakes.
- Improve reports with more chart breakdowns/trends if stakeholders request.
- Review audit logging coverage across all important mutations.
- Improve document text extraction coverage for DOCX/PDF/image OCR if needed and feasible on Hostinger.
- Expand old CRM import report/logging so skipped records are clearly recorded.

### Lower Priority

- Optional richer dashboard widgets per role.
- Optional export/report downloads.
- Optional better AI prompt tuning and assistant evaluations.
- Optional UI accessibility pass.
- Optional queue dashboard/failed job admin view.

## Open Bugs / Issues To Watch

- Remote MIS assistant can fail with timeout/429 depending on API/key/quota/network; UI should keep showing clear messages.
- Gmail SMTP can accept mail but delay/filter inbox delivery; use Mailpit during local presentation.
- Hostinger deployment can fail if `public_html/index.php` points to wrong Laravel folder or if `storage`/`bootstrap/cache` are not writable.
- Laravel config cache can preserve old `.env` values; run `php artisan optimize:clear` after env changes.
- Document preview depends on MIME/browser support; unsupported files should still download.
- The current PDF generator is intentionally simple; complex official document formatting may need improvement.

## Technical Debt

- Some older migration names still mention projects because the app evolved from a project tracker.
- Some legacy fields such as rating/risk/progress/budget remain for migration compatibility even though score-focused UI should not be emphasized.
- `barryvdh/laravel-dompdf` is required but auto-discovery is disabled; current generated PDFs use `SimplePdfService`.
- The assistant system has both local and remote layers; this is useful but needs ongoing tests to prevent over-eager navigation/regressions.
- UI CSS is concentrated in `resources/css/app.css`; continued UI expansion may require better organization.
- Old CRM import is useful but narrow; skipped/unmatched records need careful manual review.

## Suggested Next Tasks In Priority Order

1. Run a full local presentation rehearsal using Mailpit and document every failing step.
2. Add/adjust tests for job cards, delivery notes, invitation resend/expiry, and Mailpit mirror.
3. Polish responsive layouts for Finance, Operations, Requisitions, Attendance, Reports, and Assistant drawer.
4. Improve PDF templates for sales quotations/invoices/requisitions if stakeholders need official polished documents.
5. Verify Hostinger deployment with clean database and production `.env`.
6. Review and harden permissions in every controller for department users/business analyst.
7. Continue MIS assistant evaluation prompts and prevent unwanted navigation/actions.
8. Improve audit trail coverage and reporting views.

## Unconfirmed / Needs Verification

- Whether all current local database records are demo-safe.
- Whether production will use real SMTP directly or another transactional mail provider.
- Whether business wants client-facing email sending inside CRM later; current decision says no.
- Whether official PDFs must match a branded Word/QuickBooks-like template exactly.
