# WP AI Agent

> **This is a research project, not polished for production use.** It serves as a testing ground for three WordPress ecosystem packages being developed in parallel: the [PHP AI Client](https://github.com/WordPress/php-ai-client) (provider-agnostic LLM SDK), the [PHP MCP Client](https://github.com/Automattic/php-mcp-client), and the WordPress Abilities API. The agent ties all three together into a real, end-to-end use case that exercises them under realistic conditions.

A WordPress plugin that exposes an AI agent through WP-CLI. You talk to it via `wp agent chat` (interactive REPL) or `wp agent ask` (one-shot). Under the hood it runs a ReAct loop (Think → Act → Observe) — the LLM reasons about your request, picks a tool, executes it, observes the result, and repeats until the task is done. Tools range from file system operations (read, write, glob, grep, bash) to WordPress Abilities (any action a plugin registers) and external MCP servers.

The AI communication is handled entirely by the WordPress 7.0 core-bundled [PHP AI Client](https://github.com/WordPress/php-ai-client), which abstracts away provider differences — the same agent works with Anthropic, OpenAI, or Google by switching a config value.

> **Note:** WordPress 7.0 is currently in beta. This plugin requires it for the core-bundled AI client (`WordPress\AiClient` namespace).

## Key Dependencies

| Package | Role |
|---------|------|
| [wordpress/php-ai-client](https://github.com/WordPress/php-ai-client) | Provider-agnostic PHP AI SDK bundled in WordPress 7.0. Powers all LLM communication (Anthropic, OpenAI, Google). |
| [wordpress/php-mcp-schema](https://github.com/WordPress/php-mcp-schema) | PHP DTOs and types mirroring the official MCP TypeScript schema. Used for type-safe MCP message handling. |
| [automattic/php-mcp-client](https://github.com/Automattic/php-mcp-client) | MCP client implementation supporting stdio and HTTP transports. Connects the agent to external MCP servers. |

## Features

- **WP-CLI Native**: `wp agent chat`, `wp agent ask`, `wp agent init`, `wp agent config`, `wp agent auth`, `wp agent skills`
- **ReAct Loop**: Think → Act → Observe reasoning pattern for tool usage
- **WordPress Abilities**: Bridges the WordPress Abilities API — the agent can discover and execute any registered ability (core or plugin-provided) through a single STRAP facade tool
- **User Context Management**: Switch WordPress user context so abilities run with the correct permissions
- **Tool Execution**: Built-in tools with confirmation prompts; auto-confirm (yolo) mode for trusted environments
- **MCP Integration**: Connect to external MCP servers (stdio and HTTP) — tools are discovered and registered automatically
- **Multi-Provider**: Anthropic, OpenAI, and Google via the WordPress AI Client
- **Custom Skills**: Extend the agent with markdown-based skill files, managed via `wp agent skills`
- **Session Persistence**: Save and resume conversations

## Requirements

- PHP 8.1+
- WordPress 7.0+ (currently in beta — requires the core-bundled AI client)
- WP-CLI 2.0+
- API credentials for at least one AI provider (Anthropic, OpenAI, or Google)

## Installation

1. Clone or copy the plugin to `wp-content/plugins/wp-ai-agent/`
2. Install dependencies:

```bash
cd wp-content/plugins/wp-ai-agent
composer install
```

3. Activate the plugin:

```bash
wp plugin activate wp-ai-agent
```

4. Run the setup wizard to configure credentials in `wp-config.php`:

```bash
wp agent init
```

## Configuration

See the [Setup Guide](docs/setup.md) for the full configuration reference, including all providers, settings, MCP servers, and custom commands.

Quick start — add one of these to `wp-config.php`:

```php
// Anthropic
define( 'ANTHROPIC_API_KEY', 'sk-ant-your-api-key-here' );

// OpenAI
define( 'OPENAI_API_KEY', 'sk-your-openai-key-here' );
define( 'WP_AI_AGENT_MODEL', 'gpt-5.2-2025-12-11' );

// Google
define( 'GOOGLE_API_KEY', 'your-google-api-key' );
define( 'WP_AI_AGENT_MODEL', 'gemini-2.5-flash' );
```

Or run the interactive setup wizard:

```bash
wp agent init
```

## Usage

### Interactive chat

```bash
wp agent chat
wp agent chat --session=abc123   # resume a session
wp agent chat --yolo             # auto-confirm all tool executions
```

During a chat session the following slash commands are available:

| Command | Description |
|---------|-------------|
| `/model` | Display the current AI model |
| `/model <name>` | Switch to a different AI model for this session |
| `/new` | Clear conversation context and start fresh (keeps the session) |
| `/yolo` | Enable auto-confirm — tools execute without prompting |
| `/yolo off` | Disable auto-confirm — tools prompt for confirmation again |
| `/quit` | End the session and exit |

### One-shot message

```bash
wp agent ask "What plugins are active?"
wp agent ask "Run a health check" --debug
wp agent ask "Create a draft post" --yolo
```

### Manage skills

```bash
wp agent skills list
wp agent skills show summarize
wp agent skills add summarize --file=./summarize.md
wp agent skills remove summarize
```

## Tools

The agent has access to built-in tools, WordPress tools, dynamic skills, and MCP tools.

### Built-in Tools

Always available, these provide core file system and shell access:

| Tool | Description | Confirmation |
|------|-------------|:------------:|
| `think` | Internal reasoning and planning without side effects | No |
| `read_file` | Read file contents with line numbers, offset, and limit | No |
| `write_file` | Write content to a file (creates parent directories) | Yes |
| `glob` | Find files matching a glob pattern | No |
| `grep` | Search file contents with regex patterns | No |
| `bash` | Execute shell commands with timeout support | Yes |

### WordPress Tools

Registered automatically when running under WordPress 7.0+. These bridge the WordPress Abilities API and user system:

| Tool | Description | Confirmation |
|------|-------------|:------------:|
| `wordpress_abilities` | STRAP facade for all WordPress abilities — list, describe, and execute any registered ability | Per-ability |
| `wordpress_users` | Manage WordPress user context — list, set, and query the active user for ability execution | No |

#### WordPress Abilities (STRAP pattern)

Instead of registering each WordPress ability as a separate tool, a single `wordpress_abilities` facade exposes them all through three actions:

- **`list`** — Discover all available abilities with their annotations and key parameters
- **`describe`** — Get the full JSON Schema for a specific ability's parameters
- **`execute`** — Run an ability by name with parameters

Readonly abilities (e.g., `core/get-site-info`) execute immediately. Mutative abilities require the agent to pass `confirmed: true` in params, or auto-confirm mode (`--yolo`) to be active.

#### User Context

WordPress abilities check permissions against the current user. Since WP-CLI defaults to user ID 0, the agent uses `wordpress_users` to set the active user before executing abilities:

```
1. wordpress_users → list (find administrators)
2. wordpress_users → set user "admin"
3. wordpress_abilities → execute "core/get-site-info"
```

### Dynamic Tools

**Skills** — Custom tools defined as markdown files with YAML frontmatter. Managed via `wp agent skills` or by placing files in:
- `~/.wp-ai-agent/commands/` — user-level, available everywhere
- `.wp-ai-agent/commands/` — project-level, overrides user-level by name

**MCP Tools** — Tools from connected MCP servers are discovered and registered automatically with confirmation required.

## Architecture

Two-layer architecture separating platform-agnostic logic from WordPress-specific code:

### Core Layer (`src/Core/`)

No WordPress or HTTP dependencies:

- **Agent**: Session orchestrator (`Agent`), ReAct loop (`AgentLoop`), and execution context (`AgentContext`)
- **Tool System**: Registry, executor, and confirmation contracts
- **Value Objects**: Immutable domain objects (`Message`, `ToolResult`, `SessionId`, `ToolName`)
- **Contracts**: Interfaces defining all component boundaries

### Integration Layer (`src/Integration/`)

WordPress and WP-CLI implementations:

- **AiClient**: Wraps `WordPress\AiClient` for provider-agnostic LLM communication
- **Ability**: STRAP facade (`AbilityStrapTool`) bridging WordPress Abilities to the tool system
- **User**: User context management (`UserContextTool`) for ability permissions
- **MCP**: Client manager and tool adapter via `automattic/php-mcp-client`
- **Skill**: Custom skill loader, registry, and tool adapter
- **WpCli**: CLI commands (`chat`, `ask`, `init`, `config`, `auth`, `skills`), bootstrap, and handlers
- **Configuration**: Reads `WP_AI_AGENT_*` constants from `wp-config.php`
- **Session**: Persists conversations as WordPress options

## Development

### Running Tests

```bash
composer test                    # all tests
composer phpstan                 # static analysis (level 8)
composer phpcs                   # code style (PSR-12 with tabs)
./vendor/bin/phpcbf              # auto-fix style issues
```

### WordPress Integration Testing

Uses `@wordpress/env` to spin up a WordPress 7.0 environment:

```bash
npx @wordpress/env start
npx @wordpress/env run cli wp agent ask "What plugins are active?"
npx @wordpress/env stop
```

### Project Structure

```
wp-ai-agent/
├── wp-ai-agent.php           # Plugin entry point
├── src/
│   ├── Core/                 # Platform-agnostic business logic
│   │   ├── Agent/            # Agent, AgentLoop, AgentContext
│   │   ├── Contracts/        # Interfaces
│   │   ├── Exceptions/       # Domain exceptions
│   │   ├── Session/          # Session management
│   │   ├── Tool/             # Tool system
│   │   └── ValueObjects/     # Immutable value objects
│   └── Integration/          # Platform-specific implementations
│       ├── Ability/          # WordPress Abilities STRAP facade
│       ├── AiClient/         # WordPress AI Client adapter
│       ├── Configuration/    # wp-config.php constant reader
│       ├── Mcp/              # MCP client integration
│       ├── Session/          # File-based persistence (dev/testing)
│       ├── Skill/            # Custom skill loader and registry
│       ├── Tool/             # Built-in tools (bash, glob, grep, etc.)
│       ├── User/             # WordPress user context tool
│       └── WpCli/            # WP-CLI commands and bootstrap
├── tests/
│   ├── Stubs/                # WP_CLI and WordPress function stubs
│   └── Unit/                 # Unit tests
├── composer.json
├── phpstan.neon
├── phpcs.xml
└── phpunit.xml
```

## License

GPL-2.0-or-later — see [LICENSE](LICENSE) for details.
