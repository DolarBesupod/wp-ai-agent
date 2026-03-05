<?php

declare(strict_types=1);

namespace Automattic\WpAiAgent\Integration\WpCli;

use Automattic\WpAiAgent\Core\Contracts\AgentInterface;
use Automattic\WpAiAgent\Core\Contracts\ConfigurationInterface;
use Automattic\WpAiAgent\Core\Contracts\SessionRepositoryInterface;
use Automattic\WpAiAgent\Core\Exceptions\ConfigurationException;
use Automattic\WpAiAgent\Core\ValueObjects\SessionId;
use Automattic\WpAiAgent\Integration\AiClient\AiClientAdapterInterface;
use Automattic\WpAiAgent\Integration\AiClient\ProviderDetector;
use Automattic\WpAiAgent\Integration\Mcp\McpClientManager;

/**
 * WP-CLI application.
 *
 * Self-contained application that provides chat, ask, and init commands for
 * the WP-CLI integration. Does not depend on CliApplication or src/bootstrap.php.
 * All dependencies are injected through the constructor for testability.
 *
 * @since 0.1.0
 */
class WpCliApplication
{
	/**
	 * Map of short configuration keys to wp-config.php constant names and their
	 * default values and types.
	 *
	 * Each entry has: constant (string), default (mixed), type (string).
	 * Type is one of: 'string', 'int', 'float', 'bool'.
	 *
	 * @var array<string, array{constant: string, default: mixed, type: string}>
	 */
	private const CONFIG_KEY_MAP = [
		'model'          => [
			'constant' => 'WP_AI_AGENT_MODEL',
			'default'  => 'claude-sonnet-4-6',
			'type'     => 'string',
		],
		'max_tokens'     => [
			'constant' => 'WP_AI_AGENT_MAX_TOKENS',
			'default'  => 8192,
			'type'     => 'int',
		],
		'temperature'    => [
			'constant' => 'WP_AI_AGENT_TEMPERATURE',
			'default'  => 1.0,
			'type'     => 'float',
		],
		'system_prompt'  => [
			'constant' => 'WP_AI_AGENT_SYSTEM_PROMPT',
			'default'  => '',
			'type'     => 'string',
		],
		'debug'          => [
			'constant' => 'WP_AI_AGENT_DEBUG',
			'default'  => false,
			'type'     => 'bool',
		],
		'streaming'      => [
			'constant' => 'WP_AI_AGENT_STREAMING',
			'default'  => true,
			'type'     => 'bool',
		],
		'max_iterations' => [
			'constant' => 'WP_AI_AGENT_MAX_ITERATIONS',
			'default'  => 50,
			'type'     => 'int',
		],
		'bypassed_tools' => [
			'constant' => 'WP_AI_AGENT_BYPASSED_TOOLS',
			'default'  => '',
			'type'     => 'string',
		],
	];

	/**
	 * Creates a new WpCliApplication.
	 *
	 * @param ConfigurationInterface $configuration The configuration.
	 * @param AgentInterface $agent The agent.
	 * @param WpCliOutputHandler $output_handler The output handler.
	 * @param WpCliConfirmationHandler     $confirmation_handler The confirmation handler.
	 * @param SessionRepositoryInterface $session_repository The session repository.
	 * @param AiClientAdapterInterface $ai_adapter The AI adapter for model and provider switching.
	 * @param McpClientManager|null $mcp_client_manager MCP client manager (kept alive to prevent GC).
	 * @param CredentialResolver|null $credential_resolver Credential resolver for runtime provider switching.
	 *
	 * @since 0.1.0
	 */
	public function __construct(
		/** @phpstan-ignore property.onlyWritten */
		private readonly ConfigurationInterface $configuration,
		private readonly AgentInterface $agent,
		private readonly WpCliOutputHandler $output_handler,
		private readonly WpCliConfirmationHandler $confirmation_handler,
		/** @phpstan-ignore property.onlyWritten */
		private readonly SessionRepositoryInterface $session_repository,
		private readonly AiClientAdapterInterface $ai_adapter,
		/** @phpstan-ignore property.onlyWritten */
		private readonly ?McpClientManager $mcp_client_manager = null,
		private readonly ?CredentialResolver $credential_resolver = null,
	) {
	}

	/**
	 * Runs an interactive REPL session.
	 *
	 * Starts or resumes a session, then reads lines from STDIN in a loop
	 * and passes each non-empty, non-quit line to the agent. The loop ends
	 * on /quit, /exit, or EOF. The /new command clears the conversation
	 * context without ending the session.
	 *
	 * The greeting is displayed in bold via WP_CLI::colorize() and the prompt
	 * uses a bright-cyan ❯ arrow. WP-CLI handles TTY detection and --no-color
	 * automatically so output degrades gracefully.
	 *
	 * @param array<string, mixed> $assoc_args WP-CLI associative arguments.
	 *                                          Supported keys:
	 *                                          - 'session' (string): session ID to resume.
	 *                                          - 'no-save' (bool): skip persisting the session.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public function chat(array $assoc_args): void
	{
		$this->resolveSession($assoc_args);
		$this->resolveUserContext($assoc_args);

		if (! empty($assoc_args['yolo'])) {
			$this->confirmation_handler->setAutoConfirm(true);
		}

		\WP_CLI::line(\WP_CLI::colorize('%_WP AI Agent%n — type /quit to exit'));

		if ($this->confirmation_handler->isAutoConfirm()) {
			\WP_CLI::warning(\WP_CLI::colorize('%RYolo mode active%n — all tools will execute without confirmation.'));
		}

		while (true) {
			\WP_CLI::out(\WP_CLI::colorize('%C❯%n '));

			$input = \fgets(\STDIN);

			if (false === $input) {
				// EOF reached.
				break;
			}

			$input = \trim($input);

			if ($input === '') {
				continue;
			}

			if ('/yolo' === $input || '/yolo on' === $input) {
				$this->confirmation_handler->setAutoConfirm(true);
				\WP_CLI::success('Auto-confirm enabled. All tools will execute without prompting.');
				continue;
			}

			if ('/yolo off' === $input) {
				$this->confirmation_handler->setAutoConfirm(false);
				\WP_CLI::success('Auto-confirm disabled. Tools will prompt for confirmation.');
				continue;
			}

			if ('/new' === $input) {
				$session = $this->agent->getCurrentSession();

				if ($session !== null) {
					$session->clearMessages();
					\WP_CLI::success('Context cleared. Starting fresh conversation.');
				} else {
					\WP_CLI::warning('No active session.');
				}

				continue;
			}

			if (str_starts_with($input, '/model')) {
				$model_arg = trim(substr($input, 6));
				if ($model_arg === '') {
					\WP_CLI::line(sprintf(
						'Current model: %s (provider: %s)',
						$this->ai_adapter->getModel(),
						$this->ai_adapter->getProviderId()
					));
				} else {
					$this->handleModelSwitch($model_arg);
				}
				continue;
			}

			if ($input === '/quit' || $input === '/exit') {
				break;
			}

			$this->agent->sendMessage($input);
		}

		$this->agent->endSession();
	}

	/**
	 * Sends a single message to the agent and ends the session.
	 *
	 * @param string $message The user's message.
	 * @param array<string, mixed> $assoc_args WP-CLI associative arguments.
	 *                                          Supported keys:
	 *                                          - 'session' (string): session ID to resume.
	 *                                          - 'debug' (bool): enable debug output.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public function ask(string $message, array $assoc_args): void
	{
		$this->resolveSession($assoc_args);
		$this->resolveUserContext($assoc_args);

		if (! empty($assoc_args['yolo'])) {
			$this->confirmation_handler->setAutoConfirm(true);
		}

		if (!empty($assoc_args['debug'])) {
			$this->output_handler->setDebugEnabled(true);
		}

		$this->agent->sendMessage($message);
		$this->agent->endSession();
	}

	/**
	 * Writes required constants to wp-config.php via WP-CLI.
	 *
	 * Prompts for the Anthropic API key interactively and writes all
	 * agent constants that are not already defined. Existing constants are
	 * skipped unless --force is passed.
	 *
	 * @param array<string, mixed> $assoc_args WP-CLI associative arguments.
	 *                                          Supported keys:
	 *                                          - 'force' (bool): overwrite existing constants.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	public function init(array $assoc_args): void
	{
		$force = !empty($assoc_args['force']);

		// Prompt for API key and write it to wp-config.php.
		if (!\defined('ANTHROPIC_API_KEY') || $force) {
			// WP_CLI\Utils\prompt() exists at runtime but is absent from the bundled stubs.
			// @phpstan-ignore function.notFound
			$apiKey = \WP_CLI\Utils\prompt('Enter your Anthropic API key', false, ': ', true);

			if (\is_string($apiKey) && $apiKey !== '') {
				\WP_CLI::runcommand(
					'config set ANTHROPIC_API_KEY ' . $apiKey . ' --add',
					['return' => true]
				);
				\WP_CLI::success('ANTHROPIC_API_KEY written to wp-config.php');
			}
		} else {
			\WP_CLI::line('ANTHROPIC_API_KEY already defined — skipping.');
		}

		// Write each agent constant.
		$allPresent = true;

		foreach (self::CONFIG_KEY_MAP as $configEntry) {
			$constant = $configEntry['constant'];
			$default  = $configEntry['default'];
			$type     = $configEntry['type'];

			if (\defined($constant) && !$force) {
				\WP_CLI::line(\sprintf('%s already defined — skipping.', $constant));
				continue;
			}

			$allPresent = false;

			$valueString = $this->formatConstantValue($default, $type);
			$rawFlag     = $type !== 'string' ? ' --raw' : '';

			\WP_CLI::runcommand(
				\sprintf('config set %s %s --add --type=constant%s', $constant, $valueString, $rawFlag),
				['return' => true]
			);

			\WP_CLI::success(\sprintf('%s written to wp-config.php', $constant));
		}

		if ($allPresent && !$force) {
			\WP_CLI::line('All constants already set, nothing written.');
		}
	}

	/**
	 * Returns the agent instance.
	 *
	 * @return AgentInterface
	 *
	 * @since 0.1.0
	 */
	public function getAgent(): AgentInterface
	{
		return $this->agent;
	}

	/**
	 * Returns the output handler instance.
	 *
	 * @return WpCliOutputHandler
	 *
	 * @since 0.1.0
	 */
	public function getOutputHandler(): WpCliOutputHandler
	{
		return $this->output_handler;
	}

	/**
	 * Handles the /model command with a model argument.
	 *
	 * Detects the provider from the model name. If the provider differs from the
	 * current one, resolves credentials and switches the provider on the adapter.
	 * If the provider is the same, only changes the model. Missing credentials or
	 * provider initialization failures are reported as non-fatal errors.
	 *
	 * @param string $model The model name to switch to.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	private function handleModelSwitch(string $model): void
	{
		$new_provider = ProviderDetector::detectFromModel($model);
		$current_provider = $this->ai_adapter->getProviderId();

		if ($new_provider !== $current_provider) {
			if ($this->credential_resolver === null) {
				\WP_CLI::error(
					'Cannot switch providers: credential resolver not available.',
					false
				);
				return;
			}

			try {
				$resolved = $this->credential_resolver->resolve($new_provider);
			} catch (ConfigurationException $exception) {
				\WP_CLI::error($exception->getMessage(), false);
				return;
			}

			try {
				$this->ai_adapter->switchProvider(
					$new_provider,
					$resolved->getSecret(),
					$resolved->getAuthMode()
				);
			} catch (\Throwable $exception) {
				\WP_CLI::error(
					sprintf('Failed to switch to provider "%s": %s', $new_provider, $exception->getMessage()),
					false
				);
				return;
			}
		}

		$this->ai_adapter->setModel($model);
		\WP_CLI::success(sprintf('Model switched to %s (provider: %s)', $model, $new_provider));
	}

	/**
	 * Resolves the session from the given associative arguments.
	 *
	 * When a 'session' key is present, resumes the session with that ID.
	 * Otherwise, starts a new session.
	 *
	 * @param array<string, mixed> $assoc_args The WP-CLI associative arguments.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	private function resolveSession(array $assoc_args): void
	{
		if (!empty($assoc_args['session'])) {
			$sessionId = SessionId::fromString((string) $assoc_args['session']);
			$this->agent->resumeSession($sessionId);
		} else {
			$this->agent->startSession();
		}
	}

	/**
	 * Resolves the WordPress user context from the --user flag.
	 *
	 * Accepts a numeric ID, login name, or email address. Resolves the user
	 * via get_user_by() and sets the current user via wp_set_current_user().
	 * Reports the active user context. Does nothing when the flag is absent.
	 *
	 * @param array<string, mixed> $assoc_args The WP-CLI associative arguments.
	 *
	 * @return void
	 *
	 * @since 0.1.0
	 */
	private function resolveUserContext(array $assoc_args): void
	{
		if (empty($assoc_args['user'])) {
			return;
		}

		$identifier = (string) $assoc_args['user'];

		if (is_numeric($identifier)) {
			$wp_user = \get_user_by('id', (int) $identifier);
		} elseif (str_contains($identifier, '@')) {
			$wp_user = \get_user_by('email', $identifier);
		} else {
			$wp_user = \get_user_by('login', $identifier);
		}

		if ($wp_user === false) {
			\WP_CLI::warning(sprintf('User not found: %s — abilities will require manual user context.', $identifier));
			return;
		}

		\wp_set_current_user($wp_user->ID);
		\WP_CLI::log(sprintf(
			'User context: %s (ID %d, %s)',
			$wp_user->user_login,
			$wp_user->ID,
			implode(', ', $wp_user->roles)
		));
	}

	/**
	 * Formats a constant's default value as a shell-safe string for WP-CLI.
	 *
	 * Strings are wrapped in single quotes via escapeshellarg(). Booleans are
	 * output as 'true' or 'false'. Integers and floats are cast to string.
	 *
	 * @param mixed  $value The value to format.
	 * @param string $type  One of 'string', 'int', 'float', 'bool'.
	 *
	 * @return string The shell-safe value representation.
	 *
	 * @since 0.1.0
	 */
	private function formatConstantValue(mixed $value, string $type): string
	{
		return match ($type) {
			'bool'  => $value ? 'true' : 'false',
			'int'   => (string) (int) $value,
			'float' => (string) (float) $value,
			default => \escapeshellarg((string) $value),
		};
	}
}
