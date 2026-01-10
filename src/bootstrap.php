<?php

declare(strict_types=1);

/**
 * Bootstrap file for the PHP CLI Agent.
 *
 * This file initializes all dependencies and returns a configured CliApplication instance.
 * It uses environment variables for configuration and creates all necessary services.
 *
 * @since n.e.x.t
 */

namespace PhpCliAgent;

use PhpCliAgent\Core\Agent\Agent;
use PhpCliAgent\Core\Agent\AgentLoop;
use PhpCliAgent\Core\Tool\ToolExecutor;
use PhpCliAgent\Core\Tool\ToolRegistry;
use PhpCliAgent\Integration\AiClient\AiClientAdapter;
use PhpCliAgent\Integration\Cli\CliApplication;

/**
 * Creates and configures the CLI application.
 *
 * @return CliApplication The configured application instance.
 *
 * @throws \PhpCliAgent\Core\Exceptions\AiClientException If AI client initialization fails.
 * @throws \PhpCliAgent\Core\Exceptions\ConfigurationException If configuration is invalid.
 */
return (static function (): CliApplication {
	// Create configuration.
	$configuration = new class implements Core\Contracts\ConfigurationInterface {
		/** @var array<string, mixed> */
		private array $config = [];

		public function __construct()
		{
			$this->config = [
				'model' => getenv('AGENT_MODEL') ?: 'claude-sonnet-4-20250514',
				'max_tokens' => (int) (getenv('AGENT_MAX_TOKENS') ?: 4096),
				'temperature' => (float) (getenv('AGENT_TEMPERATURE') ?: 0.7),
				'max_iterations' => (int) (getenv('AGENT_MAX_ITERATIONS') ?: 100),
				'session_storage_path' => getenv('AGENT_SESSION_PATH') ?: sys_get_temp_dir() . '/php-cli-agent-sessions',
				'debug' => (bool) getenv('AGENT_DEBUG'),
				'streaming' => (bool) (getenv('AGENT_STREAMING') ?: true),
				'bypassed_tools' => [],
				'system_prompt' => $this->getDefaultSystemPrompt(),
			];
		}

		public function get(string $key, mixed $default = null): mixed
		{
			return $this->getNestedValue($key) ?? $default;
		}

		public function set(string $key, mixed $value): void
		{
			$this->setNestedValue($key, $value);
		}

		public function has(string $key): bool
		{
			return $this->getNestedValue($key) !== null;
		}

		public function getModel(): string
		{
			$model = $this->config['model'] ?? '';
			return is_string($model) ? $model : '';
		}

		public function getApiKey(): string
		{
			$api_key = getenv('ANTHROPIC_API_KEY');
			if ($api_key === false || $api_key === '') {
				throw new Core\Exceptions\ConfigurationException(
					'ANTHROPIC_API_KEY environment variable is not set'
				);
			}
			return $api_key;
		}

		public function getMaxTokens(): int
		{
			$value = $this->config['max_tokens'] ?? 4096;
			return is_numeric($value) ? (int) $value : 4096;
		}

		public function getTemperature(): float
		{
			$value = $this->config['temperature'] ?? 0.7;
			return is_numeric($value) ? (float) $value : 0.7;
		}

		public function getSessionStoragePath(): string
		{
			$path = $this->config['session_storage_path'] ?? sys_get_temp_dir() . '/php-cli-agent-sessions';
			return is_string($path) ? $path : sys_get_temp_dir() . '/php-cli-agent-sessions';
		}

		public function getSystemPrompt(): string
		{
			$prompt = $this->config['system_prompt'] ?? '';
			return is_string($prompt) ? $prompt : '';
		}

		public function getMaxIterations(): int
		{
			$value = $this->config['max_iterations'] ?? 100;
			return is_numeric($value) ? (int) $value : 100;
		}

		public function isDebugEnabled(): bool
		{
			return (bool) ($this->config['debug'] ?? false);
		}

		public function isStreamingEnabled(): bool
		{
			return (bool) ($this->config['streaming'] ?? true);
		}

		/** @return array<int, string> */
		public function getBypassedTools(): array
		{
			$tools = $this->config['bypassed_tools'] ?? [];
			if (!is_array($tools)) {
				return [];
			}
			return array_values(array_filter($tools, 'is_string'));
		}

		/** @return array<string, mixed> */
		public function toArray(): array
		{
			return $this->config;
		}

		public function loadFromFile(string $path): void
		{
			if (!file_exists($path)) {
				throw new Core\Exceptions\ConfigurationException(
					sprintf('Configuration file not found: %s', $path)
				);
			}

			$extension = pathinfo($path, PATHINFO_EXTENSION);

			if ($extension === 'yaml' || $extension === 'yml') {
				if (!function_exists('yaml_parse_file')) {
					$content = file_get_contents($path);
					if ($content === false) {
						throw new Core\Exceptions\ConfigurationException(
							sprintf('Failed to read configuration file: %s', $path)
						);
					}
					$parsed = \Symfony\Component\Yaml\Yaml::parse($content);
				} else {
					$parsed = yaml_parse_file($path);
				}

				if ($parsed === false || !is_array($parsed)) {
					throw new Core\Exceptions\ConfigurationException(
						sprintf('Failed to parse YAML configuration: %s', $path)
					);
				}

				$this->merge($parsed);
				return;
			}

			if ($extension === 'json') {
				$content = file_get_contents($path);
				if ($content === false) {
					throw new Core\Exceptions\ConfigurationException(
						sprintf('Failed to read configuration file: %s', $path)
					);
				}

				$parsed = json_decode($content, true);
				if (json_last_error() !== JSON_ERROR_NONE || !is_array($parsed)) {
					throw new Core\Exceptions\ConfigurationException(
						sprintf('Failed to parse JSON configuration: %s - %s', $path, json_last_error_msg())
					);
				}

				$this->merge($parsed);
				return;
			}

			throw new Core\Exceptions\ConfigurationException(
				sprintf('Unsupported configuration file format: %s', $extension)
			);
		}

		/** @param array<string, mixed> $config */
		public function merge(array $config): void
		{
			$this->config = array_merge($this->config, $config);
		}

		private function getDefaultSystemPrompt(): string
		{
			return <<<PROMPT
You are a helpful AI assistant with access to tools.
When the user asks you to perform tasks, use the available tools to help them.
Be concise but thorough in your responses.
PROMPT;
		}

		private function getNestedValue(string $key): mixed
		{
			$keys = explode('.', $key);
			$value = $this->config;

			foreach ($keys as $k) {
				if (!is_array($value) || !array_key_exists($k, $value)) {
					return null;
				}
				$value = $value[$k];
			}

			return $value;
		}

		private function setNestedValue(string $key, mixed $value): void
		{
			$keys = explode('.', $key);
			$current = &$this->config;

			foreach (array_slice($keys, 0, -1) as $k) {
				if (!isset($current[$k]) || !is_array($current[$k])) {
					$current[$k] = [];
				}
				$current = &$current[$k];
			}

			$current[end($keys)] = $value;
		}
	};

	// Create output handler.
	$output_handler = new class implements Core\Contracts\OutputHandlerInterface {
		private bool $debug_enabled = false;

		public function write(string $text): void
		{
			echo $text;
		}

		public function writeLine(string $text): void
		{
			echo $text . PHP_EOL;
		}

		public function writeError(string $text): void
		{
			fwrite(STDERR, "\033[31m" . $text . "\033[0m" . PHP_EOL);
		}

		public function writeSuccess(string $text): void
		{
			echo "\033[32m" . $text . "\033[0m" . PHP_EOL;
		}

		public function writeWarning(string $text): void
		{
			echo "\033[33m" . $text . "\033[0m" . PHP_EOL;
		}

		public function writeToolResult(string $tool_name, Core\ValueObjects\ToolResult $result): void
		{
			$status = $result->isSuccess() ? "\033[32m[OK]\033[0m" : "\033[31m[FAIL]\033[0m";
			$output = $result->getOutput();
			if ($output === '') {
				$output = $result->getError() ?? '';
			}
			if (strlen($output) > 200) {
				$output = substr($output, 0, 200) . '...';
			}
			$this->writeLine(sprintf('%s %s: %s', $status, $tool_name, $output));
		}

		public function writeAssistantResponse(string $text): void
		{
			$this->writeLine('');
			$this->writeLine("\033[36m" . $text . "\033[0m");
		}

		public function writeStreamChunk(string $chunk): void
		{
			echo "\033[36m" . $chunk . "\033[0m";
		}

		public function writeStatus(string $status): void
		{
			$this->writeLine("\033[33m" . $status . "\033[0m");
		}

		public function writeDebug(string $message): void
		{
			if ($this->debug_enabled) {
				$this->writeLine("\033[90m[DEBUG] " . $message . "\033[0m");
			}
		}

		public function clearLine(): void
		{
			echo "\r\033[K";
		}

		public function setDebugEnabled(bool $enabled): void
		{
			$this->debug_enabled = $enabled;
		}

		public function isDebugEnabled(): bool
		{
			return $this->debug_enabled;
		}
	};

	// Create session repository (in-memory with file persistence).
	$session_repository = new class ($configuration->getSessionStoragePath()) implements Core\Contracts\SessionRepositoryInterface {
		/** @var array<string, Core\Contracts\SessionInterface> */
		private array $sessions = [];
		private string $storage_path;

		public function __construct(string $storage_path)
		{
			$this->storage_path = $storage_path;
			$this->ensureStorageDirectory();
		}

		public function save(Core\Contracts\SessionInterface $session): void
		{
			$this->sessions[$session->getId()->toString()] = $session;

			// Also persist to disk.
			$file_path = $this->getFilePath($session->getId());
			$data = [
				'id' => $session->getId()->toString(),
				'system_prompt' => $session->getSystemPrompt(),
				'messages' => array_map(
					static fn (Core\ValueObjects\Message $msg): array => [
						'role' => $msg->getRole(),
						'content' => $msg->getContent(),
						'tool_calls' => $msg->getToolCalls(),
						'tool_call_id' => $msg->getToolCallId(),
						'tool_name' => $msg->getToolName(),
					],
					$session->getMessages()
				),
				'metadata' => $session->getMetadata()->toArray(),
			];

			$json = json_encode($data, JSON_PRETTY_PRINT);
			if ($json === false) {
				throw new Core\Exceptions\SessionPersistenceException(
					sprintf('Failed to encode session: %s', json_last_error_msg())
				);
			}

			if (file_put_contents($file_path, $json) === false) {
				throw new Core\Exceptions\SessionPersistenceException(
					sprintf('Failed to write session file: %s', $file_path)
				);
			}
		}

		public function load(Core\ValueObjects\SessionId $session_id): Core\Contracts\SessionInterface
		{
			$id_string = $session_id->toString();

			// Check in-memory cache first.
			if (isset($this->sessions[$id_string])) {
				return $this->sessions[$id_string];
			}

			// Try to load from disk.
			$file_path = $this->getFilePath($session_id);
			if (!file_exists($file_path)) {
				throw new Core\Exceptions\SessionNotFoundException($session_id);
			}

			$content = file_get_contents($file_path);
			if ($content === false) {
				throw new Core\Exceptions\SessionPersistenceException(
					sprintf('Failed to read session file: %s', $file_path)
				);
			}

			$data = json_decode($content, true);
			if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
				throw new Core\Exceptions\SessionPersistenceException(
					sprintf('Failed to decode session: %s', json_last_error_msg())
				);
			}

			/** @var string $system_prompt */
			$system_prompt = $data['system_prompt'] ?? '';
			$session = new Core\Session\Session($session_id, $system_prompt);

			/** @var array<int, mixed> $messages */
			$messages = $data['messages'] ?? [];
			foreach ($messages as $msg_data) {
				if (!is_array($msg_data)) {
					continue;
				}
				/** @var string $role */
				$role = $msg_data['role'] ?? '';
				/** @var string $msg_content */
				$msg_content = $msg_data['content'] ?? '';

				/** @var array<int, array{id: string, name: string, arguments: array<string, mixed>}> $tool_calls */
				$tool_calls = is_array($msg_data['tool_calls'] ?? null) ? $msg_data['tool_calls'] : [];

				/** @var string $tool_call_id */
				$tool_call_id = $msg_data['tool_call_id'] ?? '';
				/** @var string $tool_name */
				$tool_name = $msg_data['tool_name'] ?? '';

				$message = match ($role) {
					Core\ValueObjects\Message::ROLE_USER => Core\ValueObjects\Message::user($msg_content),
					Core\ValueObjects\Message::ROLE_ASSISTANT => Core\ValueObjects\Message::assistant(
						$msg_content,
						$tool_calls
					),
					Core\ValueObjects\Message::ROLE_TOOL => Core\ValueObjects\Message::toolResult(
						$tool_call_id,
						$tool_name,
						$msg_content
					),
					default => Core\ValueObjects\Message::user($msg_content),
				};
				$session->addMessage($message);
			}

			$this->sessions[$id_string] = $session;

			return $session;
		}

		public function exists(Core\ValueObjects\SessionId $session_id): bool
		{
			if (isset($this->sessions[$session_id->toString()])) {
				return true;
			}

			return file_exists($this->getFilePath($session_id));
		}

		public function delete(Core\ValueObjects\SessionId $session_id): bool
		{
			$id_string = $session_id->toString();
			$existed = isset($this->sessions[$id_string]);
			unset($this->sessions[$id_string]);

			$file_path = $this->getFilePath($session_id);
			if (file_exists($file_path)) {
				$existed = true;
				if (!unlink($file_path)) {
					throw new Core\Exceptions\SessionPersistenceException(
						sprintf('Failed to delete session file: %s', $file_path)
					);
				}
			}

			return $existed;
		}

		/** @return array<int, Core\ValueObjects\SessionId> */
		public function listAll(): array
		{
			$ids = [];

			$files = glob($this->storage_path . '/*.json');
			if ($files === false) {
				return $ids;
			}

			foreach ($files as $file) {
				$id = pathinfo($file, PATHINFO_FILENAME);
				$ids[] = Core\ValueObjects\SessionId::fromString($id);
			}

			return $ids;
		}

		/** @return array<int, array{id: Core\ValueObjects\SessionId, metadata: Core\Contracts\SessionMetadataInterface}> */
		public function listWithMetadata(): array
		{
			$result = [];
			$ids = $this->listAll();

			foreach ($ids as $id) {
				try {
					$session = $this->load($id);
					$result[] = [
						'id' => $id,
						'metadata' => $session->getMetadata(),
					];
				} catch (\Throwable $e) {
					// Skip invalid sessions.
				}
			}

			return $result;
		}

		private function getFilePath(Core\ValueObjects\SessionId $session_id): string
		{
			return $this->storage_path . '/' . $session_id->toString() . '.json';
		}

		private function ensureStorageDirectory(): void
		{
			if (!is_dir($this->storage_path)) {
				if (!mkdir($this->storage_path, 0755, true)) {
					throw new Core\Exceptions\SessionPersistenceException(
						sprintf('Failed to create session storage directory: %s', $this->storage_path)
					);
				}
			}
		}
	};

	// Create confirmation handler.
	$confirmation_handler = new class implements Core\Contracts\ConfirmationHandlerInterface {
		/** @var array<string, bool> */
		private array $bypasses = [];
		private bool $auto_confirm = false;

		/** @param array<string, mixed> $arguments */
		public function confirm(string $tool_name, array $arguments): bool
		{
			if ($this->auto_confirm || isset($this->bypasses[$tool_name])) {
				return true;
			}

			echo sprintf("\n\033[33mTool: %s\033[0m\n", $tool_name);

			if (count($arguments) > 0) {
				echo "Arguments:\n";
				foreach ($arguments as $key => $value) {
					$json_value = json_encode($value);
					$display_value = is_string($json_value) ? $json_value : '';
					if (strlen($display_value) > 100) {
						$display_value = substr($display_value, 0, 100) . '...';
					}
					echo sprintf("  %s: %s\n", $key, $display_value);
				}
			}

			echo "\nAllow this action? [y]es / [n]o / [a]lways for this tool / allow [A]ll: ";

			$input = fgets(STDIN);
			$response = $input !== false ? trim($input) : '';

			if ($response === 'A') {
				$this->auto_confirm = true;
				return true;
			}

			if ($response === 'a') {
				$this->bypasses[$tool_name] = true;
				return true;
			}

			return strtolower($response) === 'y' || strtolower($response) === 'yes';
		}

		public function shouldBypass(string $tool_name): bool
		{
			return isset($this->bypasses[$tool_name]);
		}

		public function addBypass(string $tool_name): void
		{
			$this->bypasses[$tool_name] = true;
		}

		public function removeBypass(string $tool_name): void
		{
			unset($this->bypasses[$tool_name]);
		}

		/** @return array<int, string> */
		public function getBypasses(): array
		{
			return array_keys($this->bypasses);
		}

		public function clearBypasses(): void
		{
			$this->bypasses = [];
		}

		public function setAutoConfirm(bool $auto_confirm): void
		{
			$this->auto_confirm = $auto_confirm;
		}

		public function isAutoConfirm(): bool
		{
			return $this->auto_confirm;
		}
	};

	// Create tool registry and executor.
	$tool_registry = new ToolRegistry();
	$tool_executor = new ToolExecutor($tool_registry, $confirmation_handler);

	// Set bypassed tools via confirmation handler.
	foreach ($configuration->getBypassedTools() as $tool_name) {
		$confirmation_handler->addBypass($tool_name);
	}

	// Create AI adapter.
	$ai_adapter = new AiClientAdapter(
		$configuration->getApiKey(),
		$configuration->getModel(),
		$configuration->getMaxTokens()
	);
	$ai_adapter->setTemperature($configuration->getTemperature());

	// Create agent loop.
	$agent_loop = new AgentLoop(
		$ai_adapter,
		$tool_executor,
		$tool_registry,
		$output_handler
	);
	$agent_loop->setMaxIterations($configuration->getMaxIterations());

	// Create agent.
	$agent = new Agent(
		$agent_loop,
		$session_repository,
		$configuration->getSystemPrompt()
	);

	// Enable debug mode if configured.
	if ($configuration->isDebugEnabled()) {
		$output_handler->setDebugEnabled(true);
	}

	// Create and return CLI application.
	return new CliApplication(
		$configuration,
		$agent,
		$output_handler
	);
})();
