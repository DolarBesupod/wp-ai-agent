<?php

declare(strict_types=1);

/**
 * Bootstrap file for the PHP CLI Agent.
 *
 * This file initializes all dependencies and returns a configured CliApplication instance.
 * It uses the ConfigurationResolver to load configuration from multiple sources with priority:
 * 1. Environment variables (highest priority)
 * 2. .wp-ai-agent/settings.json + .wp-ai-agent/mcp.json
 * 3. Built-in defaults (lowest priority)
 *
 * @since n.e.x.t
 */

namespace WpAiAgent;

use WpAiAgent\Core\Agent\Agent;
use WpAiAgent\Core\Agent\AgentLoop;
use WpAiAgent\Core\Tool\ToolExecutor;
use WpAiAgent\Integration\AiClient\AiClientAdapter;
use WpAiAgent\Integration\Cli\BypassPersistence;
use WpAiAgent\Integration\Cli\CliApplication;
use WpAiAgent\Integration\Cli\CliConfirmationHandler;
use WpAiAgent\Integration\Command\CommandExecutor;
use WpAiAgent\Integration\Command\CommandLoader;
use WpAiAgent\Integration\Command\CommandRegistry;
use WpAiAgent\Integration\Configuration\ConfigurationResolver;
use WpAiAgent\Integration\Configuration\MarkdownParser;
use WpAiAgent\Integration\Mcp\McpClientManager;
use WpAiAgent\Integration\Mcp\McpServerConfiguration as IntegrationMcpServerConfig;
use WpAiAgent\Integration\Mcp\McpToolRegistry;
use WpAiAgent\Integration\Settings\ArgumentSubstitutor;
use WpAiAgent\Integration\Settings\BashCommandExpander;
use WpAiAgent\Integration\Settings\FileReferenceExpander;
use WpAiAgent\Integration\Settings\SettingsDiscovery;
use WpAiAgent\Integration\Tool\BuiltInToolRegistry;

/**
 * Creates and configures the CLI application.
 *
 * @return CliApplication The configured application instance.
 *
 * @throws \WpAiAgent\Core\Exceptions\AiClientException If AI client initialization fails.
 * @throws \WpAiAgent\Core\Exceptions\ConfigurationException If configuration is invalid.
 */
return (static function (): CliApplication {
	// Create configuration resolver and load configuration from all sources.
	$config_resolver = new ConfigurationResolver();
	$configuration = $config_resolver->resolve();

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
	$session_repository = new class ($configuration->getSessionStoragePath())
		implements Core\Contracts\SessionRepositoryInterface {
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
			if (! file_exists($file_path)) {
				throw new Core\Exceptions\SessionNotFoundException($session_id);
			}

			$content = file_get_contents($file_path);
			if ($content === false) {
				throw new Core\Exceptions\SessionPersistenceException(
					sprintf('Failed to read session file: %s', $file_path)
				);
			}

			$data = json_decode($content, true);
			if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
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
				if (! is_array($msg_data)) {
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
				if (! unlink($file_path)) {
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

		/**
		 * @return array<int, array{
		 *     id: Core\ValueObjects\SessionId,
		 *     metadata: Core\Contracts\SessionMetadataInterface
		 * }>
		 */
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
			if (! is_dir($this->storage_path)) {
				if (! mkdir($this->storage_path, 0755, true)) {
					throw new Core\Exceptions\SessionPersistenceException(
						sprintf('Failed to create session storage directory: %s', $this->storage_path)
					);
				}
			}
		}
	};

	// Create confirmation handler with persistence.
	// Bypass state is stored in .wp-ai-agent/settings.json under permissions.allow.
	// Legacy bypass_state.json is migrated automatically on first run.
	$working_dir = getcwd();
	if ($working_dir === false) {
		$working_dir = '.';
	}
	$bypass_persistence = BypassPersistence::forWorkingDirectory($working_dir);
	$confirmation_handler = new CliConfirmationHandler(
		null, // output stream (STDOUT)
		null, // input stream (STDIN)
		null, // colors (auto-detect)
		$configuration->getBypassedTools(), // default bypass from config
		$bypass_persistence
	);

	// Create tool registry with built-in tools and executor.
	$tool_registry = BuiltInToolRegistry::createWithAllTools();
	$tool_executor = new ToolExecutor($tool_registry, $confirmation_handler);

	// Load MCP servers from configuration resolver.
	$mcp_client_manager = null;
	try {
		$mcp_servers = $config_resolver->getMcpServers();

		if (\count($mcp_servers) > 0) {
			$mcp_client_manager = new McpClientManager();

			// Convert Core configs to Integration configs and connect.
			$integration_configs = [];
			foreach ($mcp_servers as $core_config) {
				$integration_configs[] = IntegrationMcpServerConfig::fromArray(
					$core_config->getName(),
					$core_config->toArray()
				);
			}

			$failures = $mcp_client_manager->connectAll($integration_configs);

			// Log connection failures but continue.
			foreach ($failures as $server_name => $error) {
				fwrite(STDERR, \sprintf(
					"\033[33m[MCP] Failed to connect to %s: %s\033[0m\n",
					$server_name,
					$error->getMessage()
				));
			}

			// Discover and register MCP tools.
			if (\count($mcp_client_manager->getConnectedServers()) > 0) {
				$mcp_tool_registry = new McpToolRegistry($mcp_client_manager, $tool_registry);
				$tools_registered = $mcp_tool_registry->discoverAndRegister();

				if ($tools_registered > 0) {
					fwrite(STDERR, \sprintf(
						"\033[32m[MCP] Registered %d tools from %d server(s)\033[0m\n",
						$tools_registered,
						\count($mcp_client_manager->getConnectedServers())
					));
				}
			}
		}
	} catch (\Throwable $e) {
		// MCP loading is optional - continue without it.
		fwrite(STDERR, \sprintf("\033[33m[MCP] %s\033[0m\n", $e->getMessage()));
	}

	// Create command system components.
	$markdown_parser = new MarkdownParser();
	$settings_discovery = new SettingsDiscovery($working_dir);
	$argument_substitutor = new ArgumentSubstitutor();
	$file_reference_expander = new FileReferenceExpander();
	$bash_command_expander = new BashCommandExpander();

	// Create command loader and registry.
	$command_loader = new CommandLoader($markdown_parser);
	$command_registry = new CommandRegistry($command_loader, $settings_discovery);

	// Auto-discover custom commands from .wp-ai-agent/commands directories.
	try {
		$command_registry->discover();

		$custom_commands = $command_registry->getCustomCommands();
		if (\count($custom_commands) > 0) {
			fwrite(STDERR, \sprintf(
				"\033[32m[Commands] Discovered %d custom command(s)\033[0m\n",
				\count($custom_commands)
			));
		}
	} catch (\Throwable $e) {
		// Command discovery is optional - continue without it.
		fwrite(STDERR, \sprintf("\033[33m[Commands] %s\033[0m\n", $e->getMessage()));
	}

	// Create command executor for processing command content.
	$command_executor = new CommandExecutor(
		$argument_substitutor,
		$file_reference_expander,
		$bash_command_expander
	);

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
	// Pass $mcp_client_manager to keep MCP connections alive during application lifecycle.
	// Pass command system components for custom slash command support.
	return new CliApplication(
		$configuration,
		$agent,
		$output_handler,
		$mcp_client_manager,
		$command_registry,
		$command_executor
	);
})();
