<?php
declare(strict_types=1);
/* === BEGIN FILE: api/php-probe.php | Zweck: minimaler Probe-Test, ob _bootstrap.php ohne weiteren Config-/DB-Zugriff sauber geladen wird; Umfang: komplette Datei === */

require __DIR__ . '/_bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');
echo 'BOOTSTRAP_OK';

/* === END FILE: api/php-probe.php === */
