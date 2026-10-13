#!/usr/bin/env bash
#
# Build the InfinityFree upload package: dist/zentro-invoices-infinityfree-<date>.zip
#
#   tools/build-infinityfree.sh
#
# InfinityFree serves one fixed folder, htdocs, and cannot point it at Laravel's public/. So the
# two halves are swapped:
#
#   htdocs/            what public/ holds — index.php, .htaccess, assets, uploads
#   htdocs/_app/       the application, behind a deny-all .htaccess
#
# Built from `git archive HEAD`, so only committed files ship: no .env, no uploads, no logs,
# no local test data. Commit first.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
STAMP="$(date +%Y%m%d)"
DIST="$ROOT/dist"
WORK="$DIST/build"
PKG="$WORK/package"
HTDOCS="$PKG/htdocs"
APP="$HTDOCS/_app"
ZIP="$DIST/zentro-invoices-infinityfree-$STAMP.zip"

if [ -n "$(git -C "$ROOT" status --porcelain --untracked-files=no)" ]; then
    echo "Uncommitted changes — the package is built from HEAD, so commit first." >&2
    exit 1
fi

rm -rf "$WORK" "$ZIP"
mkdir -p "$APP"

echo "→ Exporting HEAD"
git -C "$ROOT" archive HEAD | tar -x -C "$APP"

echo "→ Installing production dependencies (PHP 8.3 platform)"
composer install --working-dir="$APP" --no-dev --optimize-autoloader --no-interaction --no-progress --quiet
# Composer's package:discover boots the app, and the installer's bootstrap then creates a .env
# with a fresh APP_KEY. Each site must make its own on first visit, so it goes.
rm -f "$APP/.env"

echo "→ Swapping public/ into htdocs/"
# Everything public/ holds becomes the web root, dotfiles included.
shopt -s dotglob
mv "$APP"/public/* "$HTDOCS"/
shopt -u dotglob
rmdir "$APP/public"

# Nothing the server needs and nothing a visitor should be able to fetch.
rm -rf "$APP"/tests "$APP"/tools "$APP"/docs "$APP"/phpunit.xml "$APP"/pint.json "$APP"/.editorconfig \
       "$APP"/.gitattributes "$APP"/.gitignore "$APP"/README.md "$APP"/database/database.sqlite

echo "→ Trimming fonts"
# Every browser this app supports loads .woff2, so the .eot/.ttf/.woff/.svg copies of the
# Velzon icon and UI fonts never download — they only slow the upload. Line Awesome and
# Summernote are not used at all. Inter's .ttf files stay: the PDF renderer needs TrueType.
(
    cd "$HTDOCS/assets/fonts"
    rm -f la-* summernote*
    for f in *.eot *.ttf *.woff *.svg; do
        [ -e "$f" ] || continue
        [ -e "${f%.*}.woff2" ] && rm -f "$f"
    done
)

echo "→ Front controller"
cat > "$HTDOCS/index.php" <<'PHP'
<?php

declare(strict_types=1);

/*
| InfinityFree front controller. The application lives in _app/ beside this file; this
| folder is Laravel's public/. Same steps as a stock public/index.php, different paths.
*/

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__ . '/_app/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__ . '/_app/vendor/autoload.php';

$app = require_once __DIR__ . '/_app/bootstrap/app.php';

// public_path() is this folder: uploads, fonts for PDFs and assets all resolve here.
$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
PHP

echo "→ Web-root .htaccess"
cat > "$HTDOCS/.htaccess" <<'HTACCESS'
# Zentro Invoices — web root.
# If the host already created an .htaccess here, keep its lines and ADD this block below them.

Options -Indexes

# Never serve dotfiles, Markdown or the PHP config file.
<FilesMatch "^\.|\.(md|ini|log|sh)$">
    Require all denied
</FilesMatch>

<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews
    </IfModule>

    RewriteEngine On

    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
HTACCESS

echo "→ Locking _app/"
# The only thing between the web and the database password. Both syntaxes: Apache 2.4 and 2.2.
cat > "$APP/.htaccess" <<'HTACCESS'
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Order allow,deny
    Deny from all
</IfModule>
HTACCESS

echo "→ PHP settings (.user.ini — .htaccess cannot carry them on this host)"
cat > "$HTDOCS/.user.ini" <<'INI'
display_errors = Off
log_errors = On
upload_max_filesize = 8M
post_max_size = 10M
max_execution_time = 60
INI

echo "→ Production .env template"
# The installer copies this to .env on the first visit and generates APP_KEY there, so no
# secret is in the package. Debug off: an error page must never show paths or passwords.
sed -i \
    -e 's/^APP_ENV=.*/APP_ENV=production/' \
    -e 's/^APP_DEBUG=.*/APP_DEBUG=false/' \
    -e 's/^LOG_LEVEL=.*/LOG_LEVEL=error/' \
    -e 's/^LOG_STACK=.*/LOG_STACK=daily/' \
    "$APP/.env.example"
grep -q '^LOG_LEVEL=' "$APP/.env.example" || echo 'LOG_LEVEL=error' >> "$APP/.env.example"

echo "→ Folders upload tools would otherwise skip"
# FTP clients and file managers often drop folders that hold nothing but dotfiles, and the
# application cannot run without these. A visible placeholder makes each one a real folder.
for dir in \
    storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions \
    storage/framework/views storage/framework/testing storage/logs storage/fonts bootstrap/cache; do
    mkdir -p "$APP/$dir"
    printf '<!-- keeps this folder in the upload -->\n' > "$APP/$dir/index.html"
done
mkdir -p "$HTDOCS/uploads/company"
printf '<!-- logos and signatures are stored here -->\n' > "$HTDOCS/uploads/company/index.html"
rm -f "$HTDOCS/uploads/.gitignore"

echo "→ Guide"
cp "$ROOT/docs/DEPLOY-INFINITYFREE.md" "$PKG/READ-ME-FIRST.md"

echo "→ Checks"
fail=0
for f in "$HTDOCS/index.php" "$HTDOCS/.htaccess" "$APP/.htaccess" "$APP/vendor/autoload.php" \
         "$APP/.env.example" "$HTDOCS/assets/fonts/inter/Inter-Regular.ttf" "$APP/artisan"; do
    [ -e "$f" ] || { echo "   MISSING ${f#$PKG/}"; fail=1; }
done
[ -e "$APP/.env" ] && { echo "   .env must not ship"; fail=1; }
[ -d "$APP/vendor/phpunit" ] && { echo "   dev dependencies shipped"; fail=1; }
[ "$fail" -eq 0 ] || exit 1

echo "→ Zipping"
(cd "$PKG" && zip -qr "$ZIP" htdocs READ-ME-FIRST.md)
rm -rf "$WORK"

echo
echo "Built $ZIP ($(du -h "$ZIP" | cut -f1), $(unzip -l "$ZIP" | tail -1 | awk '{print $2}') files)"
