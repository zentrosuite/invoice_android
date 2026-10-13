# Putting Zentro Invoices on InfinityFree

About 15 minutes. You need an InfinityFree account; nothing to install on your computer.

The package contains:

```
htdocs/              everything you upload
READ-ME-FIRST.md     this guide — keep it on your computer, do NOT upload it
```

---

## 1. In the InfinityFree control panel

1. **Create the website** (or pick an existing one) and note its address, e.g. `zentro.infy.click`.
   A free subdomain works; a domain of your own works too.
2. Set **PHP version → 8.3** (Account → Settings). The app needs 8.3 or newer.
3. **MySQL Databases → create a database.** Write down the four values it shows:

   | | Looks like |
   |---|---|
   | MySQL host | `sql123.infinityfree.com` |
   | Database name | `if0_12345678_invoices` |
   | Username | `if0_12345678` |
   | Password | the one you set for the account |

4. **SSL**: turn on the free SSL certificate for the site, and wait until it says active. Customers
   will open your invoice links, so they should be `https://`.

## 2. Upload

1. Unzip the package **on your computer**.
2. Open **File Manager** (or connect by FTP) and go into `htdocs`.
3. Delete the host's sample files there (`index2.html` and similar) — keep any `.htaccess`.
4. Upload the **contents** of the package's `htdocs` folder into your `htdocs`. Not the folder
   itself, or you get `htdocs/htdocs`.

   Fastest: upload the zip in the File Manager and use its *Extract*; then move the files out of
   the extracted `htdocs` folder up one level. Over FTP it is ~6,000 small files — slow but fine.

### Turn on "show hidden files" and check these exist

Files starting with a dot are hidden by most tools, and some skip them on upload. Two matter:

| File | If it is missing |
|---|---|
| `htdocs/.htaccess` | every page except the home page returns the host's 404 |
| `htdocs/_app/.htaccess` | **your database password can be downloaded.** Create it with the single line `Require all denied` |

**If `htdocs/.htaccess` already existed** (the host makes one), your upload may not have replaced
it. Open it: if it does not contain `RewriteEngine On`, copy everything from the package's
`.htaccess` and paste it **below** the host's existing lines.

## 3. Run the setup wizard

Open your site — **with `https://`** — e.g. `https://zentro.infy.click`. The setup wizard starts:

1. **Server check** — everything should be green. A red line names what is missing.
2. **Database** — enter the four values from step 1. Leave the port at 3306.
3. **Build tables** — one click, a few seconds.
4. **Your business** — Shop, Freelancer, Studio or something else, and your business name.
5. **Your login** — your name, email and a password.
6. **Done** — it shows a **recovery code once**. Write it down or save it in a password manager.
   This app sends no email, so that code is the only way back in if you forget your password.

## 4. Check the private files are private

Open these two addresses. Both must show **Forbidden / 403** (or 404). If either shows text or
downloads a file, stop and fix `htdocs/_app/.htaccess` (step 2) before anything else:

```
https://your-site/_app/.env
https://your-site/_app/composer.json
```

## 5. First things in the app

1. **Company profile** — logo, address, phone, **UPI ID** (for the payment QR) and, if you want
   a signatory block on Classic invoices, your signature. Use the **₹1 test QR** on that page to
   check your UPI ID shows your name.
2. **Invoice settings** — numbering (set *Next number* to carry on from your paper book), default
   terms, tax %, design, and the WhatsApp message.
3. Add a customer, make an invoice, press **Send on WhatsApp**, and open the link on your phone.

---

## Good to know

- **The address in your links comes from `APP_URL`** in `htdocs/_app/.env`, captured from the
  first page the wizard served. If you set the site up over `http://`, or later move to your own
  domain, edit that line to the exact address — `https://`, no trailing slash — or customers'
  WhatsApp links will point at the old one.
- **Updating to a new version**: take a backup (below), then upload the new package over the old
  one — but **do not overwrite** `htdocs/_app/.env`, `htdocs/_app/storage/` or `htdocs/uploads/`:
  your settings, the install marker and your logo live there. Sign in; if the new version changes
  the database, a blue bar says so — press **Apply update**.
- **Backups**: phpMyAdmin (control panel) → your database → **Export** → Quick → SQL. Do it
  monthly, and keep a copy of `htdocs/uploads/` and `htdocs/_app/.env`.
- **PDF downloads are slower than pages** (1–3 s on free hosting). That is the PDF being drawn.
- **Free-plan limits**: InfinityFree caps daily hits and CPU. A single business sending a few
  dozen invoices a day is far inside them.
- **If a page shows "500 / Server Error"**: the reason is in `htdocs/_app/storage/logs/`. Never
  set `APP_DEBUG=true` on a live site — the error page would show your database password.
