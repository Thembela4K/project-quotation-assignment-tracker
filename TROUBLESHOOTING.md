# Troubleshooting

## Quick Checks

```powershell
php artisan optimize:clear
php artisan migrate:status
php artisan test
npm run build
php artisan route:list
php artisan mail:test your-email@example.com
```

Check local app:

```powershell
Invoke-WebRequest -Uri http://127.0.0.1:8000/login -UseBasicParsing
```

Check Mailpit:

```powershell
Invoke-RestMethod -Uri http://127.0.0.1:8025/api/v1/messages
```

## Email Not Received Locally

### Symptom

Admin sends invitation or notification, record says `Pending`/`Sent`, but Gmail inbox does not show the message.

### Cause

Gmail SMTP can accept the send without immediate inbox delivery. Mail can be delayed, filtered, or land in Spam/All Mail.

### Fix / Demo Strategy

- In local environment, open Mailpit at `http://127.0.0.1:8025`.
- The app mirrors local system emails into Mailpit when `APP_ENV=local` and Mailpit is running.
- If Mailpit is empty:
  - Ensure Laragon Mailpit is running.
  - Check `127.0.0.1:1025` and `127.0.0.1:8025`.
  - Run `php artisan optimize:clear`.
  - Run `php artisan mail:test your-email@example.com`.

### Gmail SMTP Configuration

Use private `.env` values:

```env
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=
```

Gmail requires an app password, not the normal Gmail login password.

## Mail Config Changes Not Taking Effect

### Symptom

`php artisan tinker` or the app still shows old mail settings after `.env` edit.

### Fix

```powershell
php artisan optimize:clear
php artisan config:clear
```

Restart `php artisan serve` if it is already running.

## Invitation Link Invalid Or Expired

### Causes

- User is already accepted.
- Invitation expired.
- Admin resent an invite, which expires older pending invite tokens.
- User opened old email link.

### Fix

- Admin uses `Resend` on Admin > Users.
- User opens newest invite email in Mailpit/Gmail.

## Forgot Password OTP Not Sent

### Business Rule

OTP reset only sends for users who are:

- active
- have an email
- were invited
- accepted invitation

### Fix

- Confirm the user accepted invitation.
- If not, resend invitation first.
- Use Mailpit locally to verify OTP email.

## Login Fails

### Checks

- User must be active.
- Login field accepts username or email.
- Pending invited users cannot log in until invitation acceptance.
- Check password was changed/reset successfully.

### Useful Commands

```powershell
php artisan tinker
```

Then inspect user status without exposing credentials.

## Hostinger 500 Error: Invalid Cache Path

### Symptom

Symfony/Laravel error: `Please provide a valid cache path.`

### Causes

- Missing or unwritable `storage/framework/views`
- Missing or unwritable `bootstrap/cache`
- Incorrect folder copy/extraction structure

### Fix

Ensure these directories exist and are writable:

```text
storage/framework/cache
storage/framework/sessions
storage/framework/views
bootstrap/cache
```

Then run if shell is available:

```bash
php artisan optimize:clear
php artisan view:clear
```

## Hostinger 500 Error After Manual Upload

### Common Causes

- `public_html/index.php` points to the wrong Laravel folder.
- `.env` missing or invalid.
- `APP_KEY` missing.
- Database credentials incorrect.
- `vendor` not uploaded/installed.
- `storage` or `bootstrap/cache` not writable.

### Correct Structure

Recommended:

```text
domains/example.com/
  app-folder/
    app/
    bootstrap/
    config/
    database/
    resources/
    routes/
    storage/
    vendor/
    .env
  public_html/
    index.php
    build/
    ...
```

In `public_html/index.php`, point to the real app folder:

```php
$app = require_once __DIR__.'/../app-folder/bootstrap/app.php';
```

Adjust path to match the actual Hostinger folder.

## APP_KEY Missing

### Symptom

Laravel complains about missing application key or encrypted data/session issues.

### Fix

Generate locally or on server:

```powershell
php artisan key:generate
```

If using a copied production `.env`, keep the generated `APP_KEY` private.

## Database Import / Migration Problems

### Local Setup

```sql
CREATE DATABASE operations_core_crm;
```

Then:

```powershell
php artisan migrate --seed
```

### Production Import

If using phpMyAdmin export/import:

- Export the correct local database.
- Do not include test/demo data unless intended.
- Ensure production `.env` points to the imported database.
- If migrations were already run, avoid duplicate imports without clearing the database intentionally.

## Migrations Must Not Destroy Data

Do not use destructive commands like:

```powershell
php artisan migrate:fresh
```

unless the user explicitly asks to wipe the database.

Use:

```powershell
php artisan migrate
```

for normal schema updates.

## MIS Assistant Timeout / Quota / Bad Key

### Symptoms

- “live AI quota is currently exhausted”
- “MIS cannot connect to the configured AI key”
- timeout or unavailable messages

### Causes

- NVIDIA API quota/rate limit.
- Wrong/expired API key.
- Network/cURL certificate issue.
- Slow model response and `AI_TIMEOUT_SECONDS` too low.

### Fix

Check private `.env`:

```env
AI_REMOTE_ENABLED=true
NVIDIA_API_KEY=
NVIDIA_AI_MODEL=
AI_TIMEOUT_SECONDS=30
AI_HTTP_VERIFY=true
```

Use `AI_HTTP_VERIFY=false` only for local certificate problems, not production.

The assistant should fall back to local responder for some CRM questions, but 429/quota errors intentionally show a clear retry-later message.

## MIS Assistant Navigates Too Aggressively

### Known Issue Encountered

Earlier assistant versions navigated when the user asked analysis questions such as “how are our sales?”.

### Fix Already Applied

`OperationsAssistantService::allowsNavigation()` now blocks navigation for analysis/capability questions and only allows navigation on explicit open/show/list/view style requests or clear short confirmations.

### If It Regresses

Check:

- [app/Services/Assistant/OperationsAssistantService.php](app/Services/Assistant/OperationsAssistantService.php)
- [app/Services/Assistant/AssistantActionExecutor.php](app/Services/Assistant/AssistantActionExecutor.php)
- `tests/Feature/OperationsAssistantTest.php`

Add regression tests before changing assistant navigation behavior.

## Assistant Shows Raw JSON Or Prompt Text

### Known Issue Encountered

Remote model sometimes returned JSON or included prompt reminder text inside chat bubbles.

### Fix Already Applied

`RemoteAssistantProvider::sanitizeReply()` strips known prompt markers, markdown noise, and embedded JSON artifacts.

### If It Regresses

Improve parsing/sanitization and add tests for representative model outputs.

## UI Overlap / Filter Panel Problems

### Known Issues Encountered

- Tender proposal filter panel overlapped on wide/medium screens.
- Requisition filter panel needed layout fixes.
- Attendance page correction fields looked too heavy.
- Assistant drawer suggestions once hid the chat area.

### Fix Pattern

- Use responsive grid/flex wrapping.
- Avoid fixed widths that exceed available content area.
- Keep cards shallow; do not nest cards inside cards.
- Tables should sit in overflow containers where needed.
- Keep large-monitor desktop presentation clean.

## Document Preview Not Showing

### Supported Preview MIME Types

See [app/Models/Document.php](app/Models/Document.php):

- PDF
- common images
- SVG
- plain text
- CSV

Unsupported files should still be downloadable.

### Fix

- Check stored file path exists in storage.
- Check MIME type was captured correctly.
- Confirm route permissions and `documents.preview`.

## PDF Output Looks Basic

### Cause

Official generated PDFs currently use `SimplePdfService`, a compact text-based generator designed to keep exports working on shared hosting.

### Possible Future Fix

Enable/improve DomPDF templates or build richer PDF generation, but verify Hostinger compatibility first.

## Old CRM Import Fails

### Command

```powershell
php artisan crm:import-old-data --database=datamatics_crm_old_import
```

### Common Causes

- Old database not imported locally.
- Database name differs.
- Source tables are not RISE CRM-style table names.
- Client/staff mapping cannot be found.

### Fix

- Confirm old DB exists in MySQL.
- Confirm table `rise_items` exists.
- Import clients/users first if mappings are required.
- Review skipped/unmatched records manually.

## Tests Fail After Auth/User Changes

### Known Issues Encountered

- Optional validated array keys caused undefined `username` error.
- Dot usernames like `name.surname` were initially rejected by `alpha_dash`.
- Invitation link test redirected because admin was still logged in; real flow requires guest opening invite link.

### Fixes Applied

- Use null coalescing for optional validated fields.
- Username validation now allows letters, numbers, dots, underscores, and dashes.
- Tests log admin out before opening invite link.

## Public Repo Secret Scan

Before committing:

```powershell
rg -n 'NVIDIA_API_KEY=.+|GEMINI_API_KEY=.+|MAIL_PASSWORD=' -S . --glob '!vendor/**' --glob '!node_modules/**' --glob '!storage/logs/**' --glob '!.env'
```

Review any matches. Placeholder docs are fine; real secrets are not.

## Git / Deployment Discipline

- User requested every completed change be committed.
- Run tests/build before commit when code changed.
- Push to `main` after commit unless instructed otherwise.

## Unconfirmed / Needs Verification

- Production Hostinger cron availability.
- Production mail provider behavior.
- Whether generated PDFs need exact branding/template matching.
- Whether all old CRM records have been imported and validated.
