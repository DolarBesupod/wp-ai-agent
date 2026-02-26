<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Load WP-CLI stubs so tests that cover WpCli integration classes can run
// without a real WP-CLI runtime. Guards prevent redeclaration if WP-CLI is
// ever present in the environment.
if (!class_exists('WP_CLI')) {
	require_once __DIR__ . '/Stubs/WpCliStub.php';
}
