# Zentro Invoice — Android app

The Zentro Invoices web app, packaged as an Android app with [NativePHP for Mobile](https://nativephp.com)
(MIT). Everything runs on the phone: PHP, the Laravel app and a SQLite database. No server,
no hosting, works offline.

This branch differs from the website (`zentrosuite/invoice`) in a few deliberate ways:

| Website | App |
|---|---|
| Web installer, email + password login | First-launch welcome (profile, business name), **4-digit PIN**, recovery code; locks after 20 idle minutes |
| MySQL on the host | SQLite on the phone, migrated automatically on start |
| Customer link `/i/…` + WhatsApp message | **Send on WhatsApp opens the customer's chat with the PDF attached** (own plugin, `packages/zentro-whatsapp`) |
| Download PDF / CSV | Android **Save as** (Downloads, Google Drive…) and the share sheet |
| — | **Backup / Restore**: one `.zentrobackup` file (database + logo + signature); restore from the first welcome screen or the Backup page; dashboard reminder after 7 days |
| Logo in `public/uploads` | Logo in `storage/app/uploads` (public/ is replaced by app updates) |
| PDFs by dompdf | **PDFs printed by Android's own browser engine** (own plugin, `packages/zentro-pdf`), so Tamil letters join correctly; dompdf is the fallback |

Both have **advances** (money received before an invoice, kept on the customer and adjusted from the
invoice builder or an invoice page), **auto-draft** (the builder saves unsaved work every few seconds;
after a crash the dashboard offers "Continue") and **Tamil** in the screens and the invoice designs
(Noto Sans Tamil, bundled).

## Where the data is stored

Everything stays on the phone, in the app's private storage:

```
/data/data/com.zentrosuite.invoice/app_storage/persisted_data/database/database.sqlite
/data/data/com.zentrosuite.invoice/app_storage/persisted_data/storage/app/uploads/   (logo, signature)
```

- **Private:** only Zentro Invoice can read it. Other apps and the file manager cannot, and
  no root or setup is needed.
- **App updates keep it.** NativePHP leaves `persisted_data` alone and runs new migrations on
  start. Updates **must be signed with the same release key**: a different key forces an
  uninstall, which deletes the data. Never regenerate the keystore; keep a copy of it off this
  machine.
- **Uninstalling, a factory reset or losing the phone deletes it.** That is what Backup is for:
  one `.zentrobackup` file (database + logo + signature) saved to Google Drive or Downloads, or
  sent on WhatsApp. On the new phone, "Restore it instead" on the first welcome screen brings
  it all back (PIN included) without setting up first. The dashboard reminds the owner after 7 days
  without a backup.
- **Android's own Google backup** is still on with default rules, but it is not guaranteed to
  include this folder. Treat the app's Backup file as the real safety net.

## Build

```bash
cp .env.example .env && php artisan key:generate
# set NATIVEPHP_ANDROID_SDK_LOCATION to the Android SDK
composer install
php artisan native:install android
python3 tools/build-app-icons.py "/path/to/Logo Draft.png"   # icon + launch images
php artisan native:run android            # on a phone (USB debugging) or emulator
php artisan native:package android        # release .apk — see docs/RELEASE-ANDROID.md
```

Native plugins used: `nativephp/mobile-share` (share sheet), `smronju/nativephp-document-picker`
(file picker and Save as), `zentro/nativephp-whatsapp` (this repo: chat + attachment).

**Releasing an update:** follow [docs/RELEASE-ANDROID.md](docs/RELEASE-ANDROID.md): version
numbers, the signing key, the build, checks, and publishing on GitHub Releases.
