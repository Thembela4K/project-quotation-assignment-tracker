# Architecture

## High-Level Architecture

The app is a monolithic Laravel web application:

- HTTP requests enter through [public/index.php](public/index.php).
- Routes are defined in [routes/web.php](routes/web.php).
- Controllers in `app/Http/Controllers` handle requests and delegate workflow logic to services.
- Eloquent models in `app/Models` represent database tables and relationships.
- Blade views in `resources/views` render the UI.
- Vite compiles CSS/JS assets from `resources/css` and `resources/js`.
- MySQL/MariaDB stores all application data.
- Uploaded/generated documents are stored through Laravel storage.
- Scheduled reminders/notifications are defined in [routes/console.php](routes/console.php).

## Frontend Structure

- Main layout: [resources/views/layouts/app.blade.php](resources/views/layouts/app.blade.php)
- Module pages are grouped by folder:
  - `resources/views/clients`
  - `resources/views/sales_quotations`
  - `resources/views/job_cards`
  - `resources/views/delivery_notes`
  - `resources/views/invoices`
  - `resources/views/requisitions`
  - `resources/views/tender_proposals`
  - `resources/views/quotations`
  - `resources/views/tasks`
  - `resources/views/attendance`
  - `resources/views/documents`
  - `resources/views/reports`
  - `resources/views/admin`
- Shared assistant drawer: [resources/views/components/assistant-drawer.blade.php](resources/views/components/assistant-drawer.blade.php)
- Email templates: `resources/views/emails`
- CSS: [resources/css/app.css](resources/css/app.css)
- JS entrypoints:
  - [resources/js/app.js](resources/js/app.js)
  - [resources/js/assistant.js](resources/js/assistant.js)

The UI is server-rendered Blade with progressive JavaScript for interaction. Do not introduce a SPA framework without approval.

## Backend Structure

Controllers are resource-style where possible:

- Auth/profile/invitations: `AuthController`, `InvitationController`, `PasswordResetOtpController`, `ProfileController`
- Admin: `UserController`, `DepartmentController`, `SettingsController`
- Finance: `ClientController`, `CatalogItemController`, `SalesQuotationController`, `JobCardController`, `DeliveryNoteController`, `InvoiceController`, `PaymentController`, `ExpenseController`
- Operations: `TenderProposalController`, `QuotationController`, `AssignmentController`, `SubmissionController`, `ReminderController`, `RequisitionController`
- Work modules: `TaskController`, `AttendanceController`, `SupplierController`, `PurchaseRecordController`, `DocumentController`
- Dashboards/reports/approvals/notifications: `DashboardController`, `ReportController`, `ApprovalController`, `NotificationController`
- Assistant: `OperationsAssistantController`
- PDFs: `OfficialDocumentController`

Services centralize reusable workflow logic:

- `FinanceCalculatorService`: VAT, line totals, invoice/payment totals.
- `FinanceNumberService`: document numbering (`QUO`, `INV`, `REC`, `EXP`, `REQ`, `SUP`, `PUR`, `TSK`, `JOB`, `DN`).
- `ReminderService`: tender/quotation request due reminder logic.
- `AssignmentEmailService`, `RequisitionEmailService`, `LocalMailMirror`: email workflows.
- `UserInvitationService`, `PasswordResetOtpService`: user onboarding/reset.
- `AttendanceService`, `AuditLogService`, `CrmNotificationService`.
- `OfficialDocumentService`, `SimplePdfService`: generated official PDFs.
- `App\Services\Assistant\*`: MIS assistant pipeline.

## Database Structure

Migrations are in `database/migrations`. The schema has grown from the original tracker into a CRM-style operations portal.

### Identity And Access

- `departments`
- `users`
- `staff_members`
- `user_invitations`
- `password_reset_otps`
- `sessions`

### Clients And Finance

- `clients`
- `client_contacts`
- `client_activities`
- `catalog_items`
- `sales_quotations`
- `sales_quotation_items`
- `job_cards`
- `delivery_notes`
- `invoices`
- `invoice_items`
- `payments`
- `expenses`

### Operations

- `tender_proposals`
- `quotations` for quotation requests/incoming requests
- `assignments` polymorphic to tender proposals and quotation requests
- `important_dates`
- `submissions`
- `requisitions`
- `requisition_items`
- `reminder_logs`
- `email_logs`

### Work Tracking And Reporting

- `crm_tasks`
- `task_comments`
- `attendance_records`
- `crm_notifications`
- `audit_logs`
- `suppliers`
- `purchase_records`

### Documents And Assistant

- `documents`
- `document_texts`
- `ai_conversations`
- `ai_messages`
- `ai_action_logs`

### Settings/Infrastructure

- `app_settings`
- `jobs`, `job_batches`, `failed_jobs`
- `cache`, `cache_locks`

## Authentication And Authorization

### Login

- Login supports username or email through [app/Http/Controllers/AuthController.php](app/Http/Controllers/AuthController.php).
- Only active users can log in.
- Passwords are hashed by the `password` cast on `User`.

### Invitation Flow

- Super admin creates users through [app/Http/Controllers/UserController.php](app/Http/Controllers/UserController.php).
- New users are inactive until they accept an invitation.
- Invitation records live in `user_invitations`.
- Accepting invitation activates user and sets `invitation_accepted_at`.

### Password Reset

- Forgot password uses 6-digit OTP records in `password_reset_otps`.
- OTP reset is allowed only when the user is active and accepted an invitation.
- OTP expiry is 10 minutes via `PasswordResetOtpService::EXPIRES_IN_MINUTES`.

### Role Enforcement

- Route middleware: [app/Http/Middleware/EnsureRole.php](app/Http/Middleware/EnsureRole.php)
- Role helper methods: [app/Models/User.php](app/Models/User.php)
- Model scopes such as `scopeVisibleTo()` enforce department/user visibility in module queries.

## Routing / API Structure

The app does not expose a separate public REST API. Most endpoints are authenticated web routes returning Blade pages or redirects.

Assistant endpoints are JSON-style routes under auth:

- `POST /assistant/message`
- `POST /assistant/conversation`
- `GET /assistant/conversations`
- `GET /assistant/history`

Document endpoints:

- `POST /documents`
- `GET /documents`
- `GET /documents/{document}/preview`
- `GET /documents/{document}/download`
- `DELETE /documents/{document}`

Guest auth endpoints:

- `GET/POST /login`
- `GET/POST /forgot-password`
- `GET/POST /reset-password`
- `GET/POST /invitations/{token}`

## Integration Points

### SMTP / Mailpit

- Real SMTP settings are read from `.env`.
- Local development mirrors outgoing mail to Laragon Mailpit when `APP_ENV=local`.
- Mailpit UI: `http://127.0.0.1:8025`
- Mailpit SMTP: `127.0.0.1:1025`

### SPPRA / ESPPRA

- Public tender shortcut URL lives in `config/services.php` under `services.sppra.url`.
- It should be shown only to permitted roles via `User::canAccessSppra()`.

### MIS Assistant Remote AI

- Remote provider: [app/Services/Assistant/RemoteAssistantProvider.php](app/Services/Assistant/RemoteAssistantProvider.php)
- OpenAI-compatible NVIDIA endpoint configured through `.env`.
- API key must remain private.
- Assistant receives scoped CRM context and must not bypass user visibility rules.
- Navigation actions are vetted by [app/Services/Assistant/AssistantActionExecutor.php](app/Services/Assistant/AssistantActionExecutor.php).

### Old CRM Import

- Command: `php artisan crm:import-old-data --database=datamatics_crm_old_import`
- Source expected to be an old RISE CRM-style database.
- Imports useful records only: catalog items, invoices/payments where client links are valid, expenses, tasks, attendance.

## File Storage

- Laravel default disk is `local`, rooted at `storage/app/private`.
- Public disk is available at `storage/app/public` and can be linked with `php artisan storage:link`.
- Uploaded files are represented by `documents` rows.
- Generated PDFs are stored under `documents/generated/...` through `OfficialDocumentService`.
- `DocumentTextExtractor` indexes supported document text into `document_texts`.
- Uploaded/generated files are not committed.

## Reporting

Reports are generated from live database queries in [app/Http/Controllers/ReportController.php](app/Http/Controllers/ReportController.php) and rendered in [resources/views/reports/index.blade.php](resources/views/reports/index.blade.php). Dashboards similarly query live records in [app/Http/Controllers/DashboardController.php](app/Http/Controllers/DashboardController.php).

## Scheduling And Queues

Defined in [routes/console.php](routes/console.php):

- `reminders:send-due` daily at `08:00`
- `crm:send-daily-notifications` daily at `08:15`

Default queue connection is database. The `composer run dev` script starts a queue listener.

## Important Dependencies

Composer:

- `laravel/framework`
- `laravel/tinker`
- `barryvdh/laravel-dompdf` present but discovery disabled
- PHPUnit, Laravel Pint, Collision, Faker in dev

NPM:

- `vite`
- `laravel-vite-plugin`
- `tailwindcss`
- `@tailwindcss/vite`
- `concurrently`

## Technical Constraints

- Hostinger shared/custom PHP hosting may not offer full shell access. Deployment must support manual upload and phpMyAdmin database import.
- The app must work locally for presentations, including email demonstration through Mailpit.
- Public repository constraints require private settings in `.env` or database, not committed.
- The UI should remain Blade/Tailwind and not become a SPA without approval.
- Migrations should preserve existing tender/CRM data unless the user explicitly requests database reset.
- The assistant must remain permission-aware and should not silently mutate records.

## Unconfirmed / Needs Verification

- Whether DomPDF should be enabled for richer PDFs or the current `SimplePdfService` should remain the official generator.
- Whether all frontend interactions are fully accessible with keyboard/screen reader usage.
- Whether production Hostinger cron is available; if not, manual reminder buttons remain important.
