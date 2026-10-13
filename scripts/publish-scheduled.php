<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/bootstrap.php';

use App\Core\Database;

$stmt=Database::connection()->prepare('UPDATE articles SET status="published",published_at=COALESCE(published_at,scheduled_at),updated_at=NOW() WHERE status="scheduled" AND scheduled_at IS NOT NULL AND scheduled_at<=NOW()');
$stmt->execute();
fwrite(STDOUT,$stmt->rowCount()." scheduled article(s) published.\n");
