<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/domain.php';

header('Location: ' . app_base_path() . '/order_history.php', true, 303);
exit;
