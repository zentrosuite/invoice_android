# Releasing Zentro Invoice for Android

How to build a **signed release APK** for a new version, check it, and publish it on
GitHub Releases. Follow it top to bottom for every update.

> **The one rule:** every release is signed with the same key,
> `~/.zentro-android/zentro-invoice.jks`. Android installs an update over the old app only
> when the signature matches. With any other key, the phone refuses the update. Uninstalling to
> get round that deletes all invoices on the phone. **Never regenerate the keystore. Keep
> copies of `~/.zentro-android/` off this computer** (a USB stick, a password manager).

---

## 1. One-time setup (a new computer)

| Need | Where it lives here | Notes |
|---|---|---|
| PHP 8.3+, Composer | system | The dev PHP does not need pdo_sqlite; the phone brings its own PHP 8.4. |
| Android SDK | `~/Android/Sdk` | build-tools 35, platforms 35/36, NDK 27.0.12077973 (Gradle downloads the NDK on the first build, about 1 GB). |
| **Full JDK 17** (with `javac`) | `~/.jdks/jdk-17.0.16+8` | The system's `java-17-openjdk-amd64` is only the runtime and fails with *"does not provide the required capabilities: [JAVA_COMPILER]"*. Temurin 17 from `github.com/adoptium/temurin17-binaries`, checksum-verified. |
| Release keystore | `~/.zentro-android/zentro-invoice.jks` | Alias `zentro-invoice`. Passwords in `~/.zentro-android/keystore.txt` (`KEYSTORE=`, `ALIAS=`, `PASSWORD=`). The store and key passwords are the same. |
| Emulator (optional) | AVD `zentro` (Android 15, Pixel 6) | For a smoke test before a phone. |

`.env` is not in git. Start from `.env.example` and set at least:

```dotenv
APP_NAME="Zentro Invoice"
APP_ENV=production                           # the whole .env is packed into the app:
APP_DEBUG=false                              # debug on would show developer error pages
LOG_LEVEL=warning
NATIVEPHP_APP_ID=com.zentrosuite.invoice     # never change: it is the app's identity
NATIVEPHP_APP_VERSION=1.0.0                  # shown to people
NATIVEPHP_APP_VERSION_CODE=1                 # must go up every release
NATIVEPHP_ANDROID_SDK_LOCATION=/home/lws/Android/Sdk
NATIVEPHP_START_URL=/launch
```

Shell environment for every build:

```bash
export ANDROID_HOME=~/Android/Sdk ANDROID_SDK_ROOT=~/Android/Sdk
export JAVA_HOME=~/.jdks/jdk-17.0.16+8 PATH=~/.jdks/jdk-17.0.16+8/bin:$PATH
```

Keystore fingerprint (SHA-256), to check that a build was signed with the right key:
`DC:A1:08:…:9E:42` (full value: `keytool -list -v -keystore ~/.zentro-android/zentro-invoice.jks -alias zentro-invoice`).

---

## 2. Before building

1. **Code is committed and tests pass:** `php artisan test` (needs `.env.testing`, MySQL).
2. **Set the version** in `.env`:
   - `NATIVEPHP_APP_VERSION_CODE`: **the last released code + 1** (see the release history
     below). Android refuses an update whose code is not higher, and the app only unpacks the
     new code on the phone when *version + code* changes. With the same numbers, the phone
     keeps running the old code.
   - `NATIVEPHP_APP_VERSION`: `1.0.1` for fixes, `1.1.0` for new features.
   - **NativePHP quirk:** `native:package` builds with the code `.env` has *before* the run,
     then adds 1 to `.env` by itself. So after a build `.env` already shows the next code.
     That is fine for the next release; if you rebuild the *same* release, set it back first.
     Always confirm the real number with `aapt` (section 4).
3. **Database changes go in new migrations only.** Never edit a migration that has been
   released. The phone runs new migrations when the app starts, on top of the owner's data.
4. A backup made by an older version must still restore: `BackupService::VERSION` only goes up
   when the backup format itself changes, and `restore()` must keep reading older versions.

---

## 3. Build the signed APK

### Normal way: in a real terminal

```bash
cd /var/www/html/invoice-mobile
P=$(grep '^PASSWORD=' ~/.zentro-android/keystore.txt | cut -d= -f2-)
php artisan native:package android --build-type=release \
  --keystore=$HOME/.zentro-android/zentro-invoice.jks --key-alias=zentro-invoice \
  --keystore-password="$P" --key-password="$P" --output=$HOME/zentro-work/release
```

### If it stops at "Running Gradle" with *"TTY mode requires /dev/tty"*

This happens when it runs without a terminal (a script or background job). Either wrap the
same command in a pseudo-terminal:

```bash
export ZP="$P"
script -qec 'php artisan native:package android --build-type=release --keystore=$HOME/.zentro-android/zentro-invoice.jks --keystore-password="$ZP" --key-alias=zentro-invoice --key-password="$ZP" --output=$HOME/zentro-work/release --no-interaction' /dev/null
```

or, since the bundle is already prepared by then, run Gradle yourself, passing the signing
values per command:

```bash
cd /var/www/html/invoice-mobile/nativephp/android
P=$(grep '^PASSWORD=' ~/.zentro-android/keystore.txt | cut -d= -f2-)
./gradlew assembleRelease \
  -PMYAPP_UPLOAD_STORE_FILE=$HOME/.zentro-android/zentro-invoice.jks \
  -PMYAPP_UPLOAD_KEY_ALIAS=zentro-invoice \
  -PMYAPP_UPLOAD_STORE_PASSWORD="$P" -PMYAPP_UPLOAD_KEY_PASSWORD="$P"
```

Output: `nativephp/android/app/build/outputs/apk/release/app-release.apk`.

### Always afterwards: remove the password NativePHP leaves behind

`native:package` writes the keystore password in plain text into
`nativephp/android/gradle.properties` (git-ignored, but still on disk). Remove it:

```bash
sed -i '/^MYAPP_UPLOAD_/d' nativephp/android/gradle.properties
```

---

## 4. Check the APK

```bash
APK=nativephp/android/app/build/outputs/apk/release/app-release.apk
BT=~/Android/Sdk/build-tools/35.0.0

$BT/apksigner verify --print-certs $APK | grep SHA-256   # must be DC:A1:08:…:9E:42
$BT/aapt dump badging $APK | head -1                     # versionCode / versionName as planned
unzip -p $APK assets/bundle_meta.json                    # same version + version_code
unzip -p $APK assets/laravel_bundle.zip > /tmp/b.zip && unzip -p /tmp/b.zip .env | grep -E '^APP_(ENV|DEBUG)='
                                                         # must say production / false
```

Then install it **over** the previous version (`-r` keeps the data), on the emulator or a phone
with USB debugging:

```bash
~/Android/Sdk/platform-tools/adb install -r $APK
```

Smoke test, at phone size:

- [ ] Launch animation, then the PIN screen; **existing invoices are still there**.
- [ ] Create an invoice; PDF opens in a few seconds.
- [ ] Send on WhatsApp opens the customer's chat with the PDF attached (real phone only).
- [ ] Share PDF and Save PDF work; CSV export saves.
- [ ] Backup saves a `.zentrobackup`, and Restore of it works.
- [ ] A fresh install shows the welcome screen with "Have a backup? Restore it instead".

---

## 5. Publish on GitHub

1. Commit the version bump notes (the code is already committed) and push to
   `github.com/zentrosuite/invoice_android` `main`.
2. Tag the release commit `v<version>` (for example `v1.0.1`) and push the tag.
3. **Releases → Draft a new release** (`/releases/new`): choose the tag, title
   `Zentro Invoice <version>`, write what changed, and attach the APK renamed to
   `Zentro-Invoice-<version>.apk`. Add its SHA-256 (`sha256sum`) in the notes.
4. Keep every released APK. An older APK cannot be installed over a newer one, so they are
   for records, not for going back.

---

## 6. Installing on the owner's phone

- Send the APK (WhatsApp, Drive, USB). On the phone, open it and allow
  "Install unknown apps" for the app it was opened from, the first time only.
- **An update installs over the old app: never uninstall first.** Uninstalling deletes
  the data unless a backup was saved.
- Before a big update, make a Backup from the app anyway.

## If something goes wrong

| Problem | Cause / fix |
|---|---|
| "App not installed" / "package conflicts" on update | Signed with a different key, or version code not higher. Rebuild with the right keystore and a higher code. |
| Update installed but shows old screens | Version name + code unchanged. Raise the code and rebuild. |
| `[JAVA_COMPILER]` capability error | `JAVA_HOME` points at a runtime-only Java. Use the full JDK above. |
| `TTY mode requires /dev/tty` | Run in a real terminal, or use the Gradle command in section 3. |
| Keystore lost | Existing installs can no longer be updated. Owners must Backup, uninstall, install the new build and Restore. Prevent it: keep copies of `~/.zentro-android/`. |

---

## Release history

| Version | Code | Date | Notes |
|---|---|---|---|
| 1.0.0 | 1 | 2026-10-13 | First release: PIN lock, invoices and PDF on the phone, Send on WhatsApp with the PDF attached, Share/Save PDF, CSV export, Backup/Restore (also from the welcome screen). |
