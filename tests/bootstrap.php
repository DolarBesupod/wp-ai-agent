<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Load WP-CLI stubs so tests that cover WpCli integration classes can run
// without a real WP-CLI runtime. Guards prevent redeclaration if WP-CLI is
// ever present in the environment.
if (!class_exists('WP_CLI')) {
	require_once __DIR__ . '/Stubs/WpCliStub.php';
}

// Load WordPress function stubs so tests that cover WpCli integration classes
// can call get_option(), update_option(), and delete_option() without a real
// WordPress runtime. The guards inside the stub prevent redeclaration.
require_once __DIR__ . '/Stubs/WpFunctionsStub.php';

// Load WP_Error stub so tests can create and inspect WP_Error instances
// without a real WordPress runtime. The guard inside prevents redeclaration.
require_once __DIR__ . '/Stubs/WpErrorStub.php';

// Load WP_Ability stub so tests for AbilityToolAdapter and AbilityToolRegistry
// can construct and interact with WP_Ability instances without WordPress 6.9+.
// Must load after WpErrorStub since WP_Ability references WP_Error in type hints.
require_once __DIR__ . '/Stubs/WpAbilityStub.php';
