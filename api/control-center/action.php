<?php
declare(strict_types=1);

// Stable compatibility route. The versioned owner file prevents stale opcode
// cache entries from executing a previous write-routing implementation.
require __DIR__ . '/action-v2.php';
