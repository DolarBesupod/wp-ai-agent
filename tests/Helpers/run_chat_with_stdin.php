<?php

// phpcs:disable

/**
 * Subprocess helper: runs WpCliApplication::chat() with stdin piped from the
 * parent process and writes the resulting state to the result file given as
 * the first CLI argument.
 *
 * Usage (called by WpCliApplicationTest via proc_open):
 *
 *   php tests/Helpers/run_chat_with_stdin.php <result_file> <assoc_args_json>
 *
 * The script exits 0 on success. The result file contains a JSON object with:
 *   - auto_confirm   (bool)     : WpCliConfirmationHandler::isAutoConfirm()
 *   - success_message (string)  : last WP_CLI::success() message recorded
 *   - message_count   (int)     : session message count (-1 if no session)
 *   - current_model   (string)  : MinimalAiAdapterStub::getModel()
 *   - line_messages   (string[]): all WP_CLI::line() messages recorded
 */

declare(strict_types=1);

$project_root = dirname(__DIR__, 2);

require_once $project_root . '/vendor/autoload.php';

if (!class_exists('WP_CLI')) {
    require_once $project_root . '/tests/Stubs/WpCliStub.php';
}
require_once $project_root . '/tests/Stubs/WpFunctionsStub.php';

use Automattic\Automattic\WpAiAgent\Integration\WpCli\WpCliApplication;
use Automattic\Automattic\WpAiAgent\Integration\WpCli\WpCliConfirmationHandler;
use Automattic\Automattic\WpAiAgent\Integration\WpCli\WpCliOutputHandler;
use Automattic\Automattic\WpAiAgent\Tests\Stubs\MinimalAgentStub;
use Automattic\Automattic\WpAiAgent\Tests\Stubs\MinimalAiAdapterStub;
use Automattic\Automattic\WpAiAgent\Tests\Stubs\MinimalConfigStub;
use Automattic\Automattic\WpAiAgent\Tests\Stubs\MinimalSessionRepositoryStub;

$result_file = $argv[1] ?? null;
$assoc_json  = $argv[2] ?? '{}';

if ($result_file === null) {
    fwrite(STDERR, "Usage: run_chat_with_stdin.php <result_file> [assoc_args_json]\n");
    exit(1);
}

/** @var array<string, mixed> $assoc_args */
$assoc_args = json_decode($assoc_json, true) ?? [];

// Build the confirmation handler, respecting the --yolo flag in assoc_args.
$initial_auto_confirm = (bool) ($assoc_args['yolo'] ?? false);
$confirmation_handler = new WpCliConfirmationHandler([], $initial_auto_confirm);

$ai_adapter = new MinimalAiAdapterStub();

$app = new WpCliApplication(
    new MinimalConfigStub(),
    new MinimalAgentStub(),
    new WpCliOutputHandler(),
    $confirmation_handler,
    new MinimalSessionRepositoryStub(),
    $ai_adapter,
);

// Run chat(). STDIN is piped from the parent process.
$app->chat($assoc_args);

// Find the last WP_CLI::success() message recorded by the stub.
$success_message = '';
$line_messages   = [];
foreach (\WP_CLI::$calls as $call) {
    if ($call[0] === 'success') {
        $success_message = (string) $call[1];
    }
    if ($call[0] === 'line') {
        $line_messages[] = (string) $call[1];
    }
}

// Capture session message count for /new command tests.
$session       = $app->getAgent()->getCurrentSession();
$message_count = $session !== null ? $session->getMessageCount() : -1;

// Write the result for the parent test to assert on.
file_put_contents(
    $result_file,
    json_encode([
        'auto_confirm'    => $confirmation_handler->isAutoConfirm(),
        'success_message' => $success_message,
        'message_count'   => $message_count,
        'current_model'   => $ai_adapter->getModel(),
        'line_messages'   => $line_messages,
    ])
);
