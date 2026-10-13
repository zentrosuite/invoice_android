# Zentro Invoices

A single-person invoice builder for shops, freelancers and studios. Create, edit and keep
invoices as drafts, share them on WhatsApp or save them as PDF, and every unpaid invoice carries
a UPI payment QR code.

Built on Laravel 12 (PHP 8.3+, MySQL) and the Velzon Bootstrap 5 theme. There is **no front-end
build step**: our CSS and JS are plain files in `public/assets/app/`, so the app can be uploaded
as-is to shared hosting such as InfinityFree.

## Phases

| # | Phase | Status |
|---|---|---|
| 1 | Foundation: setup wizard with usage profile, login, recovery code, app shell | done |
| 2 | Company profile and invoice settings (logo, address, UPI ID, numbering, tax %) | done |
| 3 | Customers | |
| 4 | Invoice builder: free-form lines, drafts, statuses, editing | |
| 5 | Invoice designs, PDF download, UPI QR on unpaid invoices | |
| 6 | WhatsApp sharing and public invoice link | |
| 7 | Dashboard, payments, mobile polish, InfinityFree deploy package | |

## Usage profiles

Chosen during setup and changeable later. A profile only sets the defaults a new invoice starts
from — any invoice can still hold whatever lines you type.

| Profile | Columns |
|---|---|
| Shop / Retail | Item · Qty · Rate · Amount |
| Freelancer | Service description · Amount (no quantity) |
| Studio / Agency | Service · Hours · Rate / hr · Amount |
| Something else | Description · Qty · Rate · Amount, with columns switchable |

## Local development

```bash
composer install
cp .env.example .env        # or let the installer create it on first request
php artisan serve
```

Open the site and the setup wizard walks through the server check, database, tables, your
business and the owner login. It finishes by showing a **recovery code** once — the app sends
no email, so that code is how a forgotten password is reset (Forgot password? on the sign-in
page). A new code can be issued from My profile.

Tests use a separate MySQL database, `zentro_invoices_test` (see `phpunit.xml`):

```bash
php artisan test
vendor/bin/pint
```

Uploaded logos are re-encoded to PNG and stored in `public/uploads/company` — not
`storage/app/public`, because shared hosting cannot create the `storage:link` symlink. On a local
Apache install that folder must be writable by the web server user.

Our own CSS/JS URLs carry `?v=` from `config('app.asset_version')`. Bump it on a release.
