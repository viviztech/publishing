# Hostinger deployment

Deploy Wisdom News to the Hostinger cloud hosting website for wisdomnews.in using the PHP and MySQL runtime. The Git repository provides application code; the imported article database, images, and server configuration are transferred separately.

## Details to gather

| Detail | Where or how it is used |
| --- | --- |
| Exact cloud plan name and website entry | Confirm the target website is a custom PHP website with MySQL, Git, SSH, and cron access. |
| Domain and document directory | wisdomnews.in and the website's actual public_html path. |
| hPanel access | Access to this website's Git, PHP configuration, files, databases, backups, SSL, and cron tools. |
| SSH access | Host, port, username, and an authorized SSH key for transferring files and running CLI commands. SSH is not required merely to connect GitHub using OAuth. |
| New application database | Database name, assigned user, password, hostname, and port. Keep the WordPress database separate for rollback. |
| R2 configuration | Account ID, access key ID, secret access key, bucket, public media URL, and region for new production image uploads. |
| Owner account | Administrator name, email, and a password of at least 12 characters, entered directly in the setup command. |
| DNS and SSL | Domain pointed at the selected hosting website with HTTPS active. |

Enter passwords and private keys directly in the relevant hosting tools or server configuration. GitHub needs source code and the empty .env.example template.

Hostinger lists database names and users under the website's MySQL tools; its in-hosting database hostname is localhost. See [database details](https://www.hostinger.com/support/1583552-how-to-find-your-mysql-database-details-in-hostinger/).

## Prepare the existing WordPress site

1. Back up its files, database, and current DNS configuration.
2. Preserve the WordPress backup outside the deployment directory.
3. Create a separate application database in hPanel. The redesigned app has its own schema; it cannot run against WordPress tables.
4. Prepare an SQL export of the current application database and an archive of public/uploads. Local MySQL server files cannot be uploaded as a database import.
5. Transfer these files privately. They are excluded from Git.

The current local database already includes the WordPress import and the publishing-readiness schema patch. A complete SQL export of that database should be imported into a new empty application database. Do not run database/seed.sql on top of the export; it adds sample records and can conflict with imported IDs.

For a brand-new empty sample installation instead, import database/schema.sql then database/seed.sql. That route does not include the 228 imported articles.

## PHP configuration

Use PHP 8.3 for the initial deployment because this is the version verified locally. Enable PDO, pdo_mysql, curl, dom, mbstring, fileinfo, and session.

Set upload_max_filesize to at least 8M and post_max_size to at least 12M. Keep display_errors off. A memory_limit of at least 256M is a starting configuration for imports; check actual plan limits and workload before raising it.

Hostinger exposes these settings under the website dashboard's PHP Configuration tool. See [PHP configuration](https://www.hostinger.com/support/4667515-how-to-manage-php-extensions-and-options-in-hostinger/).

## Deploy the repository

Use repository https://github.com/viviztech/publishing and branch main. In the website dashboard, open Advanced → Git, connect GitHub, select this repository, and deploy the repository root into this website's public_html directory. Leave automatic deployment off during the initial migration.

Hostinger supports GitHub OAuth deployment for custom PHP websites on cloud hosting. See [Git deployment](https://www.hostinger.com/support/1583302-how-to-deploy-a-git-repository-in-hostinger/).

The root .htaccess routes requests to public/index.php, serves assets and uploaded images, and blocks application files, configuration, Git metadata, and runtime data. The repository is deployed as a whole because app, database, and scripts sit alongside public.

Keep the existing site backup outside public_html while preparing the cutover. Inspect the deployment target before deploying into the live domain.

## Configure the server and transfer content

1. Copy .env.example to .env on the server.
2. Enter APP_URL=https://wisdomnews.in, APP_ENV=production, APP_DEBUG=false, and SESSION_SECURE=true.
3. Enter the new hPanel database credentials and the production R2 values.
4. Leave CA_BUNDLE_PATH blank unless the Hostinger server actually needs a custom bundle; Windows certificate paths do not apply on Linux.
5. Import the application SQL export into the new empty application database with phpMyAdmin or the MySQL CLI.
6. Transfer public/uploads to the same path under the deployed project. The local archive currently contains 415 mirrored images, about 115 MB.
7. Ensure the hosting user can write storage/logs, storage/cache, storage/backups, and storage/tmp. Keep private directories private and avoid broad 777 permissions.
8. Run the readiness checks from the project directory:

```sh
php scripts/check-hosting.php
php scripts/check-hosting.php --database
php scripts/create-admin.php
```

New production uploads use R2. A Git deployment does not transfer media objects or verify R2 connectivity. Once R2 is configured, scripts/test-r2.php can test a temporary upload and deletion; it writes a disposable object to the configured bucket.

## Scheduled publishing

Create a PHP cron job for the absolute server path to scripts/publish-scheduled.php. Use every minute: * * * * *.

The hosting scheduler uses UTC for its cron schedule. Running every minute avoids a fixed timezone conversion; the script and database connection use APP_TIMEZONE=Asia/Kolkata to evaluate due articles.

See [Hostinger cron jobs](https://www.hostinger.com/support/1583465-how-to-set-up-a-cron-job-at-hostinger/).

## Verify before completing the cutover

- Home, category, article, search, and policy pages return the expected content.
- Imported Tamil URLs and image URLs resolve on the live domain.
- Administrator login, article save, image upload, and campaign management work.
- The scheduler publishes a test story at the expected time.
- Configured advertisements adapt to desktop and mobile screens.
- The footer counter records public views while excluding staff and known bots.
- HTTPS and the canonical domain are correct.
- .env, .git, storage, and database files cannot be downloaded.

Replace placeholder publication contact and policy details before launch. Add permanent redirects from old WordPress permalinks to the corresponding new article routes; the import preserves original source URLs for that mapping.

## Future updates

Push source changes to GitHub and use a reviewed manual deployment until runtime preservation is verified. Keep current backups of .env, application SQL, and uploaded images outside the deployment directory. Confirm the hosting deployment process preserves those runtime files before enabling automatic deployment.

Source updates do not apply database migrations. Apply only the SQL patches required for the installed schema, after taking a backup. If deployment fails, restore the previous source release, its configuration, and the matching database/media backup.
