<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Services\R2Storage;

try {
    (new R2Storage())->healthCheck();
    fwrite(STDOUT, "Cloudflare R2 upload and deletion check passed.\n");
} catch (Throwable $error) {
    fwrite(STDERR, "Cloudflare R2 check failed: {$error->getMessage()}\n");
    exit(1);
}
