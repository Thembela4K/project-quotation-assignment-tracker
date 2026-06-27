# Agent Instructions

## Project

**Name:** Datamatics Eswatini Business Operations Portal  
**Repository/package:** `operations-core-crm` / `thembela4k/operations-core-crm`  
**Purpose:** A Laravel + MySQL business operations portal for internal company work: clients, sales quotations, invoices, payments, expenses, tender proposals, quotation requests, department assignments, requisitions, tasks, attendance, suppliers, documents, approvals, notifications, reports, and the MIS assistant.

This repository is public. Treat all private organization data, credentials, uploaded documents, database exports, and local `.env` values as sensitive.

## Tech Stack

- PHP `^8.3`
- Laravel `^13.8`
- MySQL/MariaDB
- Blade server-rendered views
- Tailwind CSS v4 through Vite
- Vite v8
- PHPUnit 12 feature tests
- Laravel queues using the database driver by default
- Local development commonly uses Laragon on Windows
- PDF generation uses `App\Services\SimplePdfService`; `barryvdh/laravel-dompdf` exists in `composer.json` but package auto-discovery is disabled.

## Local Run

Install dependencies:

```powershell
composer install
npm install
copy .env.example .env
php artisan key:generate
```

Create and configure a MySQL/MariaDB database, then set `DB_*` values in `.env`.

Run migrations and seeders:

```powershell
php artisan migrate --seed
```

Start the app:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

Open:

- App: `http://127.0.0.1:8000`
- Local Mailpit inbox when Laragon Mailpit is running: `http://127.0.0.1:8025`

Composer has a convenience dev command:

```powershell
composer run dev
```

That starts Laravel, queue listener, logs, and Vite using `concurrently`.

## Build

```powershell
npm run build
```

The production build writes assets to `public/build`. That folder is ignored by Git and should be regenerated for deployments.

## Test

```powershell
php artisan test
npm run build
```

Composer wrapper:

```powershell
composer test
```

Existing feature tests are in `tests/Feature` and cover auth/access, tender workflow, reminders, finance workflow, requisitions, CRM core modules, MIS assistant, and user invitation/password reset.

## Important Commands

```powershell
php artisan migrate
php artisan migrate --seed
php artisan migrate:status
php artisan optimize:clear
php artisan config:clear
php artisan route:list
php artisan mail:test optional-recipient@example.com
php artisan reminders:send-due
php artisan crm:send-daily-notifications
php artisan assistant:index-documents
php artisan crm:import-old-data --database=datamatics_crm_old_import
```

## Structure

- `app/Http/Controllers`: HTTP controllers for each module.
- `app/Models`: Eloquent models and role/status constants.
- `app/Services`: domain services for finance calculations, numbering, reminders, notifications, documents, email, attendance, audit logging, and local mail mirroring.
- `app/Services/Assistant`: MIS assistant context, remote provider, action execution, local response fallback, and document indexing.
- `app/Mail`: system email mailables.
- `database/migrations`: schema history.
- `database/seeders/DatabaseSeeder.php`: non-sensitive starter records only.
- `resources/views`: Blade pages by module plus shared layout and email templates.
- `resources/css/app.css`: Tailwind/CSS for the app UI.
- `resources/js/app.js` and `resources/js/assistant.js`: frontend behavior, including assistant drawer.
- `routes/web.php`: web routes and route-level role restrictions.
- `routes/console.php`: scheduled commands and custom Artisan commands.
- `public/images`: committed image folder is intentionally empty except `.gitkeep`; private logos/assets should not be committed unless explicitly approved.
- `storage/app`: uploaded/generated files; not committed.

## Modules

- Dashboard: company overview cards, charts, workload and deadline summaries.
- Clients: client register, contacts, follow-ups.
- Finance: sales quotations, job cards, delivery notes, invoices, payments, expenses, item catalog.
- Operations: tender proposals, quotation requests, assignments, submissions, requisitions, reminders.
- Tasks: task assignment, comments, deadlines, attachments.
- Attendance: clock in/out, admin correction, reports.
- Suppliers/Purchases: supplier register and purchase records.
- Documents: central document registry, preview/download, document text indexing where supported.
- Approvals: sales quotation approvals, requisition approvals, notifications.
- Reports: finance, operations, workload, attendance, supplier/expense summaries.
- Admin: users, invitations, departments, settings.
- MIS assistant: chat drawer with CRM-aware answers and safe navigation actions.

## Roles And Access

Roles are defined in [app/Models/User.php](app/Models/User.php):

- `super_admin`: full control.
- `director`: approval authority and company-wide dashboards/reports.
- `reception`: client/finance operation, invoices, payments, expenses, operations intake and assignment.
- `department_user`: department quotation drafts, job cards, assigned operations work, own/department records.
- `business_analyst`: read-only operational/reporting visibility.

There is intentionally no `manager` role.

Use `User` helper methods such as `canManage()`, `canManageFinance()`, `canApproveFinance()`, `canViewReports()`, `canApproveRequisitions()`, `canReleaseRequisitionFunds()`, and `canViewPortfolio()` before adding new permission branches.

## Database

- Primary database is MySQL/MariaDB.
- The app uses Eloquent migrations; do not hand-edit production schema manually unless deployment constraints require it.
- Existing migrations include original tender/quotation tables, CRM finance tables, requisitions, core CRM modules, assistant tables, job cards/delivery notes, and user invitation/password OTP tables.
- Important tables include:
  - `users`, `user_invitations`, `password_reset_otps`, `departments`, `staff_members`
  - `clients`, `client_contacts`, `client_activities`
  - `catalog_items`, `sales_quotations`, `sales_quotation_items`
  - `job_cards`, `delivery_notes`
  - `invoices`, `invoice_items`, `payments`, `expenses`
  - `suppliers`, `purchase_records`
  - `tender_proposals`, `quotations`, `assignments`, `submissions`, `important_dates`
  - `requisitions`, `requisition_items`
  - `documents`, `document_texts`, `email_logs`, `reminder_logs`
  - `crm_tasks`, `task_comments`, `attendance_records`, `crm_notifications`, `audit_logs`
  - `ai_conversations`, `ai_messages`, `ai_action_logs`
  - `app_settings`, `jobs`, `failed_jobs`, `sessions`, `cache`

## Environment Variables

Use `.env.example` for placeholders. Never commit real `.env`.

Core:

```env
APP_ENV=local
APP_KEY=
APP_URL=http://127.0.0.1:8000
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
```

Seeder admin:

```env
ADMIN_NAME=
ADMIN_EMAIL=
ADMIN_PASSWORD=
```

Mail:

```env
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME="Datamatics Eswatini Notifications"
MAILPIT_HOST=127.0.0.1
MAILPIT_PORT=1025
```

Email signature:

```env
MAIL_SIGNATURE_COMPANY=
MAIL_SIGNATURE_PHONE=
MAIL_SIGNATURE_LANDLINE=
MAIL_SIGNATURE_EMAIL=
MAIL_SIGNATURE_WEBSITE=
MAIL_SIGNATURE_ADDRESS=
MAIL_SIGNATURE_IMAGE_PATH=
```

MIS assistant:

```env
AI_PROVIDER=nvidia
AI_REMOTE_ENABLED=true
AI_HTTP_VERIFY=true
NVIDIA_API_BASE_URL=https://integrate.api.nvidia.com/v1
NVIDIA_API_KEY=
NVIDIA_AI_MODEL=nvidia/llama-3.3-nemotron-super-49b-v1
AI_TEMPERATURE=0.2
AI_TOP_P=0.7
AI_MAX_TOKENS=1024
AI_TIMEOUT_SECONDS=30
AI_CONTEXT_RECORD_LIMIT=120
```

## Email Notes

- One configured system mailbox sends all system emails.
- Departments are recipients; department mailbox credentials are not stored in the CRM.
- User invitation and password reset OTP emails are supported.
- In `APP_ENV=local`, `App\Services\LocalMailMirror` mirrors system emails into Mailpit if Mailpit is available. This is important for local presentations because Gmail may accept SMTP but delay/filter inbox delivery.

## Deployment / Hosting Notes

Target hosting discussed for this project is Hostinger Custom PHP/HTML website, not WordPress, Website Builder, or Node.js Web App.

Manual Hostinger pattern:

1. Put the Laravel application folder outside `public_html`.
2. Copy Laravel `public` folder contents into `public_html`.
3. Edit `public_html/index.php` so `require __DIR__.'/../laravel-folder/bootstrap/app.php'` points to the real app folder.
4. Put private `.env` inside the Laravel app folder, not in Git.
5. Configure Hostinger MySQL/MariaDB values.
6. Run or import migrations/seeded database.
7. Ensure `storage` and `bootstrap/cache` are writable.
8. Run where SSH is available:

```bash
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

The user may export the local database via phpMyAdmin and import it to Hostinger. If doing that, ensure no local demo records, private documents, or test credentials are unintentionally moved.

## Coding Conventions

- Follow existing Laravel controller/service/model patterns.
- Keep domain logic in services when it is shared or workflow-heavy.
- Use Eloquent relationships and query scopes rather than ad hoc SQL unless necessary.
- Use existing constants on models for roles/statuses/categories.
- Keep Blade views server-rendered; avoid introducing a SPA framework without approval.
- Keep UI corporate, clean, dense, desktop-first, and responsive for large monitors. Avoid playful gradients/orbs and card nesting.
- Pagination should be used for tables/lists.
- Preserve the sidebar/module navigation and subnav structure.
- Uploaded files and generated PDFs belong in storage, not Git.
- Use `apply_patch` for manual edits.
- Do not introduce unrelated refactors while fixing a specific workflow.

## Security Rules

- Never commit `.env`, passwords, API keys, SMTP app passwords, production URLs if private, exported databases, uploaded documents, logs, or private logo/source assets.
- This is a public repository. Seeders must stay generic.
- Real staff names, real user emails, and department mailbox values should live in the database or private `.env`, not public seeders/docs, unless explicitly approved.
- MIS assistant must respect existing permissions and must not bypass `visibleTo()` scopes.
- MIS assistant should not create/edit/delete/approve/send/release funds unless a safe explicit workflow is implemented and reviewed.
- Password reset OTPs are only for active users who accepted invitations.
- Invitation links and OTPs must not be exposed in logs or public docs.

## Do Not Change Without Asking

- Do not reintroduce Streamlit or replace Laravel/Blade/Tailwind without approval.
- Do not rename roles or re-add `manager` casually.
- Do not remove invitation-only user onboarding.
- Do not expose public registration.
- Do not remove the local Mailpit mirror without replacing the demo email strategy.
- Do not change VAT away from 15% unless the business rule changes.
- Do not turn the app into full accounting with ledgers/journals/payroll/tax filing without a new scope decision.
- Do not make departments send emails to clients from CRM; current rule is departments download documents and send through their own email.
- Do not let reception send client quotations; departments own client quotation sending outside CRM.
- Do not make departments create invoices; departments create job cards, reception creates invoices.
- Do not expose SPPRA link in settings UI casually; it is currently a configured shortcut.
- Do not commit private logo images or uploaded/generated files.

## Definition Of Done

For code changes:

- Requirements are implemented in the smallest coherent scope.
- Role/department permissions are preserved.
- Tables/lists remain responsive and paginated where applicable.
- Emails are logged or mirrored/testable where relevant.
- Migrations run without destroying existing data unless explicitly requested.
- `php artisan test` passes.
- `npm run build` passes.
- `git diff --check` passes.
- Public repo safety is checked for secrets/private files.
- Changes are committed and pushed to `main` because the project owner asked that every change be committed after completion.

For documentation-only changes:

- Docs reflect code and clearly mark unconfirmed items.
- No private credentials or sensitive data are added.
- Commit and push after verification.
