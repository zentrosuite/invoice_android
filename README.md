# Zentro Invoice — Android app

The Zentro Invoices web app, packaged as an Android app with [NativePHP for Mobile](https://nativephp.com)
(MIT). Everything runs on the phone: PHP, the Laravel app and a SQLite database. No server,
no hosting, works offline.

This branch differs from the website (`zentrosuite/invoice`) in a few deliberate ways:

| Website | App |
|---|---|
| Web installer, email + password login | First-launch welcome (profile, business name), **4-digit PIN**, recovery code; locks after 5 idle minutes |
| MySQL on the host | SQLite on the phone, migrated automatically on start |
| Customer link `/i/…` + WhatsApp message | **Send on WhatsApp opens the customer's chat with the PDF attached** (own plugin, `packages/zentro-whatsapp`) |
| Download PDF / CSV | Android **Save as** (Downloads, Google Drive…) and the share sheet |
| — | **Backup / Restore**: one `.zentrobackup` file (database + logo + signature); dashboard reminder after 7 days |
| Logo in `public/uploads` | Logo in `storage/app/uploads` (public/ is replaced by app updates) |

## Build

```bash
cp .env.example .env && php artisan key:generate
# set NATIVEPHP_ANDROID_SDK_LOCATION to the Android SDK
composer install
php artisan native:install android
python3 tools/build-app-icons.py "/path/to/Logo Draft.png"   # icon + launch images
php artisan native:run android            # on a phone (USB debugging) or emulator
php artisan native:package android        # release .apk
```

Native plugins used: `nativephp/mobile-share` (share sheet), `smronju/nativephp-document-picker`
(file picker and Save as), `zentro/nativephp-whatsapp` (this repo: chat + attachment).
