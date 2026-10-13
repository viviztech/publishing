# Wisdom News

A PHP and MySQL publishing application with a Tamil magazine interface, article and category pages, newsroom administration, scheduled publishing, advertising placements, and page-view counts.

Source repository: https://github.com/viviztech/publishing

## Runtime

- PHP 8.3, the version used for local verification.
- MySQL with utf8mb4 and JSON support.
- PHP extensions: PDO, pdo_mysql, curl, dom, mbstring, fileinfo, and session.
- Apache-compatible rewrites, including Hostinger's LiteSpeed hosting.
- Cloudflare R2 for new image uploads in production.

The repository contains application code, SQL schema and patches, compiled browser assets, self-hosted fonts, and example configuration. No Composer install or Node build is needed for this version.

## Hostinger deployment

Follow [the deployment guide](docs/HOSTINGER.md). Deploy the repository root into the website's public_html directory using the root .htaccess file; do not deploy only the public folder.

Copy .env.example to .env on the hosting server and fill in the actual website database and R2 values. Credentials belong in the server's .env file.

Editorial content and media are separate from Git. The prepared local site has 228 imported WordPress articles and 415 mirrored images. Transfer an application database export and public/uploads separately to preserve those stories and images.

## First administrator and scheduled publishing

From the deployed project directory:

```sh
php scripts/check-hosting.php
php scripts/check-hosting.php --database
php scripts/create-admin.php
```

Configure a PHP cron job for the absolute path to scripts/publish-scheduled.php every minute. It publishes due scheduled articles using the application timezone.

## Local verification

Existing development data and configuration remain in the ignored storage directory. The integration checks require the isolated local database and the app running at http://127.0.0.1:8000:

```sh
php scripts/check-publishing.php --local
php scripts/verify-wordpress-import.php --local --web
```

New local setup requires importing database/schema.sql and database/seed.sql into an empty development database and providing local environment values. The seed is development sample content; it does not contain the imported WordPress site or an administrator password.

## Files excluded from Git

The .env file, local database files, logs, backups, source snapshots, uploaded images, browser sessions, and screenshots are ignored. Font license notices remain with the source assets.
