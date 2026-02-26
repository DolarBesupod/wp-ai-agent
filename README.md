# WP AI Agent

A WordPress plugin that adds an AI agent powered by Claude to WP-CLI. Features an interactive chat REPL, one-shot message mode, streaming output, ReAct loop (Think → Act → Observe), tool execution with user confirmation, and MCP integration for external tools.

## Features

- **WP-CLI Native**: `wp agent chat`, `wp agent ask`, `wp agent init`, `wp agent config`
- **ReAct Loop**: Think → Act → Observe reasoning pattern for tool usage
- **Tool Execution**: Built-in tools with `WP_CLI::confirm()` prompts before execution
- **MCP Integration**: Connect to external MCP servers via `PHP_CLI_AGENT_MCP_SERVERS` constant
- **Session Persistence**: Save and resume conversations stored as WordPress options
- **WordPress Configuration**: All settings stored as PHP constants in `wp-config.php`

## Requirements

- PHP 8.4 or higher
- WordPress 6.0+
- WP-CLI 2.0+
- Anthropic API key

## Installation

1. Clone or copy the plugin to `wp-content/plugins/wp-ai-agent/`
2. Activate the plugin in WordPress admin or via WP-CLI:

```bash
wp plugin activate wp-ai-agent
```

3. Run the setup wizard to configure constants in `wp-config.php`:

```bash
wp agent init
```

## Configuration

All settings are stored as PHP constants in `wp-config.php`. Use `wp agent init` to write them interactively, or set them manually:

```php
define('ANTHROPIC_API_KEY',         'your-api-key-here');
define('WP_AI_AGENT_MODEL',         'claude-sonnet-4-6');
define('WP_AI_AGENT_MAX_TOKENS',    8192);
define('WP_AI_AGENT_TEMPERATURE',   1.0);
define('WP_AI_AGENT_MAX_ITERATIONS', 10);
define('WP_AI_AGENT_DEBUG',         false);
define('WP_AI_AGENT_STREAMING',     true);
define('WP_AI_AGENT_BYPASSED_TOOLS', '');  // comma-separated tool names
```

Manage settings via WP-CLI:

```bash
wp agent config list
wp agent config get model
wp agent config set model claude-opus-4-6
```

## Usage

### Interactive chat

```bash
wp agent chat
wp agent chat --session=abc123   # resume a session
```

### One-shot message

```bash
wp agent ask "What plugins are active?"
wp agent ask "Run a health check" --debug
```

### Initialise configuration

```bash
wp agent init
```

## Architecture

The project uses a two-layer architecture:

### Core Layer (`src/Core/`)

Platform-agnostic business logic with no WordPress or HTTP dependencies:

- **Agent**: Session orchestrator (`Agent`, `AgentLoop`, `AgentContext`)
- **Tool System**: Registry, executor, and confirmation contracts
- **Value Objects**: Immutable domain objects (`Message`, `ToolResult`, `SessionId`)
- **Contracts**: Interfaces defining component boundaries

### Integration Layer (`src/Integration/`)

WordPress and WP-CLI specific implementations:

- **WpCli**: `WpCliApplication`, `WpCliCommand`, `WpCliConfigCommand`, `WpCliBootstrap`
- **AI Client**: Anthropic Claude via `wordpress/php-ai-client`
- **MCP**: MCP protocol via `galatanovidiu/php-mcp-client`
- **Configuration**: `WpConfigConfiguration` — reads `WP_AI_AGENT_*` constants
- **Session Storage**: `WpOptionsSessionRepository` — persists sessions as WordPress options
- **Tool**: Built-in tools (`bash`, `glob`, `grep`, `read_file`, `write_file`, `think`)

## Built-in Tools

| Tool | Description |
|------|-------------|
| `think` | Internal reasoning without side effects |
| `read_file` | Read contents of a file |
| `write_file` | Write content to a file |
| `glob` | Find files matching a pattern |
| `grep` | Search for patterns in files |
| `bash` | Execute shell commands (requires confirmation) |

## Development

### Running Tests

```bash
# Run all tests
composer test

# Run a specific test file
./vendor/bin/phpunit tests/Unit/Core/Agent/AgentTest.php
```

### Code Quality

```bash
# Run PHPStan (level 8)
composer phpstan

# Run PHPCS
composer phpcs

# Auto-fix PHPCS issues
./vendor/bin/phpcbf
```

### Project Structure

```
wp-ai-agent/
├── wp-ai-agent.php        # Plugin entry point
├── src/
│   ├── Core/              # Platform-agnostic business logic
│   │   ├── Agent/         # Agent, AgentLoop, Context
│   │   ├── Contracts/     # Interfaces
│   │   ├── Exceptions/    # Domain exceptions
│   │   ├── Session/       # Session management
│   │   ├── Tool/          # Tool system
│   │   └── ValueObjects/  # Immutable value objects
│   └── Integration/       # Platform-specific implementations
│       ├── AiClient/      # Anthropic integration
│       ├── Configuration/ # Legacy config loader
│       ├── Mcp/           # MCP integration
│       ├── Session/       # File-based persistence (dev/testing)
│       ├── Tool/          # Built-in tools
│       └── WpCli/         # WP-CLI commands, bootstrap, output/confirmation handlers
├── tests/
│   ├── Stubs/             # WP_CLI and WordPress function stubs
│   └── Unit/              # Unit tests
├── composer.json
├── phpstan.neon
├── phpcs.xml
└── phpunit.xml
```

## License

MIT License — see [LICENSE](LICENSE) for details.
