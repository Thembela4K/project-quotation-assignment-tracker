# Datamatics Eswatini

A Laravel + MySQL/MariaDB business operations portal for managing clients, sales quotations, invoices, payments, expenses, tender proposals, quotation requests, assignments, reminders, documents, approvals, and reporting.

## Stack

- Laravel 13
- PHP 8.3+
- MySQL or MariaDB
- Blade + Tailwind CSS
- Vite

## Modules

- Clients: client register, billing details, VAT/tax number, notes, and primary contacts.
- Finance: item catalog, sales quotations, director approval, invoices, manual payments, expenses, print-ready documents, and email notifications.
- Operations: tender proposals, quotation requests, requisitions for funds approval, department assignments, documents, submissions, reminders, and SPPRA shortcut setting.
- Reports: finance totals, outstanding balances, sales quotation pipeline, expenses, department workload, and operations summaries.
- Admin: users, departments, staff members, and private settings.

VAT is fixed at 15% in V1. This is stored as an internal setting/default for maintainability, but normal users do not edit it.

## Roles

- `super_admin`: full system control.
- `director`: approve/reject sales quotations and view company-wide dashboards/reports.
- `reception`: create clients, issue invoices, record payments/expenses, and manage operations intake/assignments.
- `department_user`: create department sales quotation drafts and work on assigned operations records.
- `business_analyst`: read-only company and operations reporting visibility.

There is no `manager` role in the CRM permission model.

## Security Note

This is a public repository. Do not commit real `.env` files, passwords, production URLs, SMTP credentials, exported databases, logs, or private documents.

The committed seed data is intentionally generic. Use your private local database or production database for real organization data.

## Local Setup

Install PHP 8.3+, Composer, Node.js, and MySQL/MariaDB.

```powershell
composer install
npm install
copy .env.example .env
php artisan key:generate
```

Create a local MySQL database:

```sql
CREATE DATABASE operations_core_crm;
```

Update `.env` with your private local values, then run:

```powershell
php artisan migrate --seed
npm run build
php artisan serve --host=127.0.0.1 --port=8000
```

## Admin User

Set private admin values in your local `.env` before seeding:

```env
ADMIN_NAME=
ADMIN_EMAIL=
ADMIN_PASSWORD=
```

The app does not expose public registration. Admins create users from inside the application.

## Email

The CRM uses one outgoing mailbox for every system email: assignment notifications, reminders, client quotation emails, invoice emails, password resets, and future verification/OTP messages. Department mailboxes are recipients only; they do not need SMTP passwords in the CRM.

Set SMTP values privately in `.env`:

```env
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-system-mailbox@example.com
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=your-system-mailbox@example.com
MAIL_FROM_NAME="Datamatics Eswatini Notifications"
```

For Gmail, `MAIL_PASSWORD` must be the private Google app password for that sender mailbox:

```env
MAIL_PASSWORD=your-private-app-password
```

The email signature is controlled through private `.env` values so real contact details are not committed:

```env
MAIL_SIGNATURE_COMPANY=
MAIL_SIGNATURE_PHONE=
MAIL_SIGNATURE_LANDLINE=
MAIL_SIGNATURE_EMAIL=
MAIL_SIGNATURE_WEBSITE=
MAIL_SIGNATURE_ADDRESS=
MAIL_SIGNATURE_IMAGE_PATH=
```

Send a development test email with:

```powershell
php artisan mail:test
```

When `APP_ENV=local`, the app also mirrors sent system emails to Laragon Mailpit when it is available. This keeps presentations reliable even if Gmail delays or filters delivery. Open the local inbox at `http://127.0.0.1:8025`; Mailpit listens on SMTP port `1025`.

## MIS AI Assistant

MIS uses an OpenAI-compatible NVIDIA API endpoint as the primary assistant engine. The model receives CRM context from Laravel, answers from the available records, and may return vetted navigation actions that Laravel validates before the UI opens a page.

Set the AI values privately in `.env`; never commit the real key:

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

Use `AI_HTTP_VERIFY=false` only on a local development machine when PHP cURL is configured with a broken certificate path. Keep it `true` on Hostinger/production.

If the API key is missing, the API is unavailable, or quota is exceeded, MIS shows a clear retry-later message instead of falling back to hard-coded answers.

## Reminders

Manual reminder sending is available from the Reminders page.

For scheduled reminders, configure cron to run Laravel's scheduler:

```bash
* * * * * php /path/to/artisan schedule:run >> /dev/null 2>&1
```

The app schedules `reminders:send-due` daily at 08:00.

## Hostinger Manual Upload

1. Run `composer install --no-dev --optimize-autoloader`.
2. Run `npm install` and `npm run build`.
3. Upload the Laravel project files to Hostinger outside `public_html`.
4. Copy Laravel's `public` folder contents into `public_html`.
5. Update `public_html/index.php` to point to the Laravel folder.
6. Create a Hostinger MySQL/MariaDB database.
7. Configure production `.env` privately on Hostinger.
8. Run migrations and cache commands through SSH/terminal where available:

```bash
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

If SSH is unavailable, import the database through phpMyAdmin and make sure `storage` and `bootstrap/cache` folders exist and are writable.

## Tests

```powershell
php artisan test
npm run build
```
