# PHP CLI Agent

A general-purpose CLI AI agent built in PHP, featuring an interactive REPL with streaming output, ReAct loop (Think → Act → Observe), tool execution with user confirmation, MCP integration for external tools, and session persistence.

## Features

- **Interactive REPL**: Command-line interface with streaming AI responses
- **ReAct Loop**: Think → Act → Observe reasoning pattern for tool usage
- **Tool Execution**: Built-in tools with user confirmation before execution
- **MCP Integration**: Connect to external MCP servers for additional capabilities
- **Session Persistence**: Save and resume conversations with JSON-based storage
- **YAML Configuration**: Configure the agent, providers, and MCP servers via YAML files
- **PSR-3 Logging**: File and console loggers with log rotation and sensitive data redaction

## Requirements

- PHP 8.1 or higher
- Composer
- Anthropic API key

## Installation

1. Clone the repository:

```bash
git clone https://github.com/galatanovidiu/php-cli-agent.git
cd php-cli-agent
```

2. Install dependencies:

```bash
composer install
```

3. Set up your environment:

```bash
export ANTHROPIC_API_KEY="your-api-key-here"
```

## Configuration

### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `ANTHROPIC_API_KEY` | Your Anthropic API key (required) | - |
| `AGENT_MODEL` | Claude model to use | `claude-sonnet-4-20250514` |
| `AGENT_MAX_TOKENS` | Maximum tokens in response | `4096` |
| `AGENT_TEMPERATURE` | Response temperature (0.0-1.0) | `0.7` |
| `AGENT_MAX_ITERATIONS` | Maximum ReAct loop iterations | `100` |
| `AGENT_SESSION_PATH` | Path for session storage | System temp directory |
| `AGENT_DEBUG` | Enable debug output | `false` |
| `AGENT_STREAMING` | Enable streaming responses | `true` |

### YAML Configuration

Create an `agent.yaml` file for persistent configuration:

```yaml
agent:
  model: claude-sonnet-4-20250514
  max_tokens: 4096
  temperature: 0.7
  max_iterations: 100
  streaming: true
  debug: false

provider:
  type: anthropic
  api_key: ${ANTHROPIC_API_KEY}

mcp_servers:
  - name: filesystem
    command: npx
    args: ["@modelcontextprotocol/server-filesystem", "/path/to/allowed"]
```

## Usage

### Starting the Agent

```bash
php agent
```

### REPL Commands

| Command | Description |
|---------|-------------|
| `/help` | Show available commands |
| `/new` | Start a new conversation |
| `/sessions` | List saved sessions |
| `/resume <id>` | Resume a saved session |
| `/save` | Save current session |
| `/delete <id>` | Delete a session |
| `/tools` | List available tools |
| `/debug` | Toggle debug mode |
| `/exit` or `/quit` | Exit the agent |

### Example Session

```
$ php agent

PHP CLI Agent v1.0
Type /help for available commands, /exit to quit.

You: What files are in the current directory?

Agent: I'll check what files are in the current directory for you.
[Using tool: read_directory]

The current directory contains:
- src/           (directory)
- tests/         (directory)
- composer.json  (file)
- README.md      (file)
...

You: /exit
Goodbye!
```

## Architecture

The project follows a clean architecture with two main layers:

### Core Layer (`src/Core/`)

Platform-agnostic business logic:

- **Agent**: Main orchestrator managing sessions and the ReAct loop
- **AgentLoop**: Implements the Think → Act → Observe reasoning pattern
- **Session**: Conversation state and message history
- **Tool System**: Tool registry, executor, and confirmation handling
- **Value Objects**: Immutable domain objects (Message, ToolResult, SessionId, etc.)
- **Contracts**: Interfaces defining component boundaries

### Integration Layer (`src/Integration/`)

Platform-specific implementations:

- **CLI**: Command-line interface, REPL runner, output handling
- **AI Client**: Anthropic Claude integration via `wordpress/php-ai-client`
- **MCP**: MCP protocol integration via `galatanovidiu/php-mcp-client`
- **Logging**: PSR-3 compliant file and console loggers
- **Configuration**: YAML configuration loading with environment variable expansion
- **Session Storage**: JSON-based file persistence

## Built-in Tools

The agent comes with several built-in tools:

| Tool | Description |
|------|-------------|
| `think` | Internal reasoning without side effects |
| `read_file` | Read contents of a file |
| `write_file` | Write content to a file |
| `list_directory` | List files in a directory |
| `glob` | Find files matching a pattern |
| `grep` | Search for patterns in files |
| `bash` | Execute shell commands (requires confirmation) |

## Development

### Running Tests

```bash
# Run all tests
composer test

# Run tests with coverage
./vendor/bin/phpunit --coverage-text

# Run specific test file
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
php-cli-agent/
├── agent                  # CLI entry point
├── src/
│   ├── Core/              # Platform-agnostic business logic
│   │   ├── Agent/         # Agent, AgentLoop, Context
│   │   ├── Contracts/     # Interfaces
│   │   ├── Exceptions/    # Domain exceptions
│   │   ├── Session/       # Session management
│   │   ├── Tool/          # Tool system
│   │   └── ValueObjects/  # Immutable value objects
│   ├── Integration/       # Platform-specific implementations
│   │   ├── AiClient/      # Anthropic integration
│   │   ├── Cli/           # CLI/REPL implementation
│   │   ├── Configuration/ # YAML configuration
│   │   ├── Logging/       # PSR-3 loggers
│   │   ├── Mcp/           # MCP integration
│   │   └── Session/       # File-based persistence
│   └── bootstrap.php      # Dependency wiring
├── tests/
│   ├── Unit/              # Unit tests
│   └── Integration/       # Integration tests
├── composer.json
├── phpstan.neon
├── phpcs.xml
└── phpunit.xml
```

## Extending the Agent

### Adding Custom Tools

Implement the `ToolInterface`:

```php
use PhpCliAgent\Core\Contracts\ToolInterface;
use PhpCliAgent\Core\ValueObjects\ToolResult;

class MyCustomTool implements ToolInterface
{
    public function getName(): string
    {
        return 'my_custom_tool';
    }

    public function getDescription(): string
    {
        return 'Description of what the tool does';
    }

    public function getParameterSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'input' => ['type' => 'string', 'description' => 'Input parameter'],
            ],
            'required' => ['input'],
        ];
    }

    public function execute(array $arguments): ToolResult
    {
        $input = $arguments['input'] ?? '';

        // Your tool logic here

        return ToolResult::success('Result output');
    }

    public function requiresConfirmation(): bool
    {
        return true; // Ask user before executing
    }
}
```

Register the tool with the registry:

```php
$registry->register(new MyCustomTool());
```

### Connecting MCP Servers

Add MCP servers to your configuration:

```yaml
mcp_servers:
  - name: my-server
    command: /path/to/mcp-server
    args: ["--option", "value"]
    env:
      API_KEY: ${MY_API_KEY}
```

## License

MIT License - see [LICENSE](LICENSE) for details.

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch
3. Write tests for new functionality
4. Ensure all tests pass and code quality checks succeed
5. Submit a pull request

## Acknowledgments

- [Anthropic Claude](https://www.anthropic.com/) - AI model
- [Model Context Protocol](https://modelcontextprotocol.io/) - Tool integration standard
- [wordpress/php-ai-client](https://github.com/WordPress/php-ai-client) - Claude API client
- [galatanovidiu/php-mcp-client](https://github.com/galatanovidiu/php-mcp-client) - MCP protocol client
