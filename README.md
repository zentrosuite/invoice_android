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
| 3 | Customers | done |
| 4 | Invoice builder: itemised or package pricing, drafts, statuses, editing | done |
| 5 | Invoice designs (Package, Modern, Classic, Receipt, Minimal), PDF download, UPI QR on unpaid invoices | done |
| 6 | WhatsApp sharing and public invoice link | done |
| 7 | Dashboard, payments, mobile polish, InfinityFree deploy package | done |

## Usage profiles

Chosen during setup and changeable later. A profile only sets the defaults a new invoice starts
from — any invoice can still hold whatever lines you type.

| Profile | Default design | Lines |
|---|---|---|
| Shop / Retail | Receipt | Item · Qty · Rate · Amount |
| Freelancer | Package | Services marked Included / Not included, one package total |
| Studio / Agency | Package | Same; switchable to Service · Hours · Rate / hr · Amount |
| Something else | Classic | Description · Qty · Rate · Amount, with columns switchable |

The **Package** design follows the 5 Star Sales and Service invoice: a list of services, each
Included or Not included with optional sub-points, and a single total — the way a freelancer
quotes a project.

## Deploying to InfinityFree

```bash
tools/build-infinityfree.sh     # → dist/zentro-invoices-infinityfree-<date>.zip
```

Built from the committed `HEAD` (commit first): production-only dependencies for PHP 8.3, the
web root swapped into `htdocs/` with the application locked away in `htdocs/_app/`, a
production `.env` template (the installer creates `.env` and its key on first visit), and
placeholder files so upload tools keep the empty storage folders. The step-by-step guide,
`docs/DEPLOY-INFINITYFREE.md`, ships in the zip as `READ-ME-FIRST.md`.

New versions that change the database: upload the new package (keeping `_app/.env`,
`_app/storage/` and `uploads/`), sign in, and press **Apply update** in the banner — there is
no shell on the host to run migrations.

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

Invoice designs live in `resources/views/documents/`. One view per design serves both the
on-screen preview and the PDF (dompdf), so they cannot drift apart — which means the views are
written for dompdf: CSS 2.1, tables for layout, no flexbox, grid, CSS variables or gradients,
and images as data URIs. The fonts are Inter (SIL OFL, `public/assets/fonts/inter`), registered
as one family per weight because dompdf only distinguishes normal and bold. dompdf caches font
metrics in `storage/fonts`, which must be writable.

Uploaded logos are re-encoded to PNG and stored in `public/uploads/company` — not
`storage/app/public`, because shared hosting cannot create the `storage:link` symlink. On a local
Apache install that folder must be writable by the web server user.

Our own CSS/JS URLs carry `?v=` from `config('app.asset_version')`. Bump it on a release.
