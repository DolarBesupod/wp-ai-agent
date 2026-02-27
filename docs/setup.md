# Setup Guide

## Requirements

- WordPress 7.0+ (the plugin depends on the core-bundled AI client)
- PHP 8.4+
- WP-CLI

## 1. Install the Plugin

```bash
wp plugin activate wp-ai-agent
wp agent ask "Hello"
```

## 2. Configure a Provider

You need credentials for at least one AI provider. There are three ways to configure them (pick one):

### Option A: PHP Constants in wp-config.php

Add one of these blocks to `wp-config.php`:

**Anthropic (API key)**

```php
define( 'ANTHROPIC_API_KEY', 'sk-ant-your-api-key-here' );
```

**Claude Code (subscription)**

```php
define( 'CLAUDE_CODE_SUBSCRIPTION_KEY', 'sk-ant-oat01-your-setup-token-here' );
```

Get the token by running `claude setup-token` in the Claude Code CLI.

**Anthropic (subscription)**

```php
define( 'ANTHROPIC_SUBSCRIPTION_KEY', 'sk-ant-oat01-your-setup-token-here' );
```

Same token from `claude setup-token`. Uses the Anthropic provider path instead of Claude Code.

**OpenAI (API key)**

```php
define( 'OPENAI_API_KEY', 'sk-your-openai-key-here' );
define( 'WP_AI_AGENT_MODEL', 'gpt-5.2-2025-12-11' );
```

**OpenAI Codex (subscription)**

```php
define( 'OPENAI_SUBSCRIPTION_KEY', 'your-codex-access-token' );
define( 'WP_AI_AGENT_MODEL', 'GPT-5.3-Codex' );
```

Run `codex login`, then copy `access_token` from `~/.codex/auth.json`.
Only Codex models (`GPT-5.3-Codex`, `gpt-5.2-2025-12-11-codex`) work with subscription tokens.

**Google**

```php
define( 'GOOGLE_API_KEY', 'your-google-api-key' );
define( 'WP_AI_AGENT_MODEL', 'gemini-2.5-flash' );
```

### Option B: CLI Credential Manager

Store credentials in the WordPress database instead of editing config files:

```bash
# Anthropic API key
wp agent auth set --provider=anthropic

# Anthropic subscription (setup-token)
wp agent auth set --provider=anthropic --mode=subscription

# Claude Code subscription
wp agent auth set --provider=claudeCode --mode=subscription

# OpenAI API key
wp agent auth set --provider=openai

# OpenAI Codex subscription
wp agent auth set --provider=openai --mode=subscription

# Google API key
wp agent auth set --provider=google
```

Each command prompts for the secret interactively. You can also pass it inline with `--secret=<value>`.

Manage stored credentials:

```bash
wp agent auth status              # Show all configured credentials
wp agent auth get --provider=anthropic   # Show masked credential details
wp agent auth delete --provider=openai   # Remove a credential
```

### Option C: Environment Variables

Set the same constant names as environment variables:

```bash
export ANTHROPIC_API_KEY="sk-ant-your-api-key"
export OPENAI_API_KEY="sk-your-openai-key"
export GOOGLE_API_KEY="your-google-key"
export CLAUDE_CODE_SUBSCRIPTION_KEY="sk-ant-oat01-your-token"
export OPENAI_SUBSCRIPTION_KEY="your-codex-token"
export ANTHROPIC_SUBSCRIPTION_KEY="sk-ant-oat01-your-token"
```

### Credential Priority

When multiple credentials exist for the same provider, the highest-priority source wins:

1. PHP constant in `wp-config.php` (API key mode)
2. Environment variable (API key mode)
3. Subscription constant or environment variable
4. Database credential (`wp agent auth set`)

## 3. Agent Configuration

All settings below are optional. The agent works out of the box with sensible defaults.

### PHP Constants (wp-config.php)

Add any of these to `wp-config.php` to customize the agent:

| Constant                     | Type   | Default             | Description                                  |
| ---------------------------- | ------ | ------------------- | -------------------------------------------- |
| `WP_AI_AGENT_MODEL`          | string | `claude-sonnet-4-6` | AI model to use                              |
| `WP_AI_AGENT_MAX_TOKENS`     | int    | `8192`              | Maximum tokens per response                  |
| `WP_AI_AGENT_TEMPERATURE`    | float  | `1.0`               | Creativity/randomness (0.0 to 1.0+)          |
| `WP_AI_AGENT_MAX_ITERATIONS` | int    | `50`                | Maximum agent loop iterations per turn       |
| `WP_AI_AGENT_STREAMING`      | bool   | `true`              | Stream responses in real time                |
| `WP_AI_AGENT_DEBUG`          | bool   | `false`             | Enable verbose debug output                  |
| `WP_AI_AGENT_SYSTEM_PROMPT`  | string | _(built-in)_        | Custom system prompt                         |
| `WP_AI_AGENT_BYPASSED_TOOLS` | string | `''`                | Comma-separated tools that skip confirmation |
| `WP_AI_AGENT_AUTO_CONFIRM`   | bool   | `false`             | Auto-confirm all tool executions (yolo mode) |

Example:

```php
define( 'WP_AI_AGENT_MODEL', 'gpt-5.2-2025-12-11' );
define( 'WP_AI_AGENT_MAX_TOKENS', 4096 );
define( 'WP_AI_AGENT_MAX_ITERATIONS', 25 );
define( 'WP_AI_AGENT_BYPASSED_TOOLS', 'think,read_file,glob' );
```

### Environment Variables

Override any agent setting at runtime using these environment variables:

| Variable               | Overrides                    | Type   |
| ---------------------- | ---------------------------- | ------ |
| `ANTHROPIC_API_KEY`    | API key (highest priority)   | string |
| `AGENT_MODEL`          | `WP_AI_AGENT_MODEL`          | string |
| `AGENT_MAX_TOKENS`     | `WP_AI_AGENT_MAX_TOKENS`     | int    |
| `AGENT_MAX_ITERATIONS` | `WP_AI_AGENT_MAX_ITERATIONS` | int    |
| `AGENT_TEMPERATURE`    | `WP_AI_AGENT_TEMPERATURE`    | float  |
| `AGENT_STREAMING`      | `WP_AI_AGENT_STREAMING`      | bool   |
| `AGENT_DEBUG`          | `WP_AI_AGENT_DEBUG`          | bool   |
| `AGENT_SESSION_PATH`   | Session storage directory    | string |

Example:

```bash
AGENT_MODEL=gpt-5.2-2025-12-11 AGENT_DEBUG=1 wp agent chat
```

### Quick Setup via CLI

Run `wp agent init` to write all agent constants to `wp-config.php` interactively. It prompts for the API key and writes all `WP_AI_AGENT_*` constants with their default values.

```bash
wp agent init          # Write constants (skip existing)
wp agent init --force  # Overwrite existing constants
```

## 4. Model Selection

The default model is `claude-sonnet-4-6`. To use a different model, set `WP_AI_AGENT_MODEL`:

```php
define( 'WP_AI_AGENT_MODEL', 'claude-sonnet-4-6' );    // Anthropic
define( 'WP_AI_AGENT_MODEL', 'gpt-5.2-2025-12-11' );              // OpenAI
define( 'WP_AI_AGENT_MODEL', 'GPT-5.3-Codex' );        // OpenAI Codex
define( 'WP_AI_AGENT_MODEL', 'gemini-2.5-flash' );     // Google
```

The provider is auto-detected from the model name prefix:

| Prefix                                  | Provider            |
| --------------------------------------- | ------------------- |
| `claude-`                               | Anthropic           |
| `gpt-`, `o1-`, `o3-`, `o4-`, `chatgpt-` | OpenAI              |
| `gemini-`, `models/gemini-`             | Google              |
| _(unrecognized)_                        | Anthropic (default) |

The `claudeCode` provider cannot be auto-detected — it requires the `CLAUDE_CODE_SUBSCRIPTION_KEY` constant.

You can switch models at runtime during a chat session:

```
/model gpt-5.2-2025-12-11
/model claude-sonnet-4-6
```

Switching to a model from a different provider will automatically resolve credentials for the new provider.

## 5. Settings File

For more advanced configuration, create `~/.wp-ai-agent/settings.json`:

```json
{
  "provider": {
    "type": "anthropic",
    "model": "claude-sonnet-4-6",
    "max_tokens": 8192
  },
  "max_turns": 100,
  "temperature": 0.7,
  "streaming": true,
  "debug": false,
  "auto_confirm": false,
  "session_storage_path": "~/.wp-ai-agent/sessions",
  "log_path": "~/.wp-ai-agent/logs",
  "default_system_prompt": "",
  "permissions": {
    "allow": [],
    "deny": []
  }
}
```

All fields are optional — only include what you want to override.

String values support `${ENV_VAR}` expansion:

```json
{
  "provider": {
    "api_key": "${ANTHROPIC_API_KEY}"
  }
}
```

| Key                     | Type   | Default                   | Description                                             |
| ----------------------- | ------ | ------------------------- | ------------------------------------------------------- |
| `provider.type`         | string | `anthropic`               | Provider: `anthropic`, `openai`, `google`, `claudeCode` |
| `provider.model`        | string | `claude-sonnet-4-6`       | Model identifier                                        |
| `provider.max_tokens`   | int    | `8192`                    | Maximum tokens per response                             |
| `provider.api_key`      | string | —                         | API key (supports `${ENV_VAR}`)                         |
| `max_turns`             | int    | `100`                     | Maximum agent loop iterations per session               |
| `temperature`           | float  | `0.7`                     | Creativity/randomness                                   |
| `streaming`             | bool   | `true`                    | Stream responses in real time                           |
| `debug`                 | bool   | `false`                   | Enable verbose debug output                             |
| `auto_confirm`          | bool   | `false`                   | Skip tool confirmation prompts                          |
| `session_storage_path`  | string | `~/.wp-ai-agent/sessions` | Session file storage directory                          |
| `log_path`              | string | `~/.wp-ai-agent/logs`     | Log file directory                                      |
| `default_system_prompt` | string | `""`                      | Custom system prompt                                    |
| `permissions.allow`     | array  | `[]`                      | Tool names that bypass confirmation                     |
| `permissions.deny`      | array  | `[]`                      | Tool names that are blocked                             |

### Configuration Priority

Settings are resolved in this order (highest priority first):

1. **Environment variables** (`AGENT_*`, `ANTHROPIC_API_KEY`)
2. **Settings file** (`~/.wp-ai-agent/settings.json`)
3. **PHP constants** (`WP_AI_AGENT_*` in `wp-config.php`)
4. **Built-in defaults**

## 6. MCP Servers

Connect external tools via the [Model Context Protocol](https://modelcontextprotocol.io).

### Option A: JSON Configuration

Create `~/.wp-ai-agent/mcp.json`:

```json
{
  "mcpServers": {
    "my-http-server": {
      "url": "http://localhost/wp-json/mcp/v1/full",
      "bearer_token": "${MY_TOKEN}",
      "timeout": 30,
      "enabled": true
    },
    "my-stdio-server": {
      "command": "npx",
      "args": ["-y", "@modelcontextprotocol/server-filesystem", "/path/to/dir"],
      "env": { "DEBUG": "true" },
      "timeout": 30,
      "enabled": true
    }
  }
}
```

String values support `${ENV_VAR}` expansion.

#### HTTP Transport

| Key            | Type   | Default      | Description                             |
| -------------- | ------ | ------------ | --------------------------------------- |
| `url`          | string | _(required)_ | HTTP endpoint URL                       |
| `bearer_token` | string | —            | Sets the `Authorization: Bearer` header |
| `headers`      | object | —            | Custom HTTP headers                     |
| `timeout`      | float  | `30`         | Request timeout in seconds              |
| `enabled`      | bool   | `true`       | Enable or disable this server           |

#### Stdio Transport

| Key       | Type   | Default      | Description                           |
| --------- | ------ | ------------ | ------------------------------------- |
| `command` | string | _(required)_ | Command to execute                    |
| `args`    | array  | `[]`         | Command arguments                     |
| `env`     | object | `{}`         | Environment variables for the process |
| `timeout` | float  | `30`         | Timeout in seconds                    |
| `enabled` | bool   | `true`       | Enable or disable this server         |

Transport type is auto-detected: `url` present = HTTP, `command` present = stdio.

### Option B: PHP Constant (wp-config.php)

Define MCP servers directly in `wp-config.php` using a PHP array:

```php
define( 'PHP_CLI_AGENT_MCP_SERVERS', [
    'mcpServers' => [
        'my-server' => [
            'url'          => 'http://localhost/wp-json/mcp/v1/full',
            'bearer_token' => 'your-token-here',
        ],
        'fetch' => [
            'command' => 'uvx',
            'args'    => ['mcp-server-fetch'],
            'timeout' => 30,
            'enabled' => true,
        ],
        'playwright' => [
            'command' => 'npx',
            'args'    => ['-y', '@playwright/mcp'],
            'timeout' => 30,
            'enabled' => true,
        ],
        'wpcom' => [
            'type'    => 'http',
            'url'     => 'https://public-api.wordpress.com/wpcom/v2/mcp/v1',
            'headers' => [
                'Authorization' => 'Bearer ...',
    ],
] );
```

This is useful when credentials should stay in `wp-config.php` rather than in a JSON file.

## 7. Custom Slash Commands

Extend the agent with custom commands stored as markdown files:

- **User-level:** `~/.wp-ai-agent/commands/` (available everywhere)
- **Project-level:** `.wp-ai-agent/commands/` (overrides user-level by name)

Each command is a `.md` file with YAML frontmatter:

```markdown
---
description: Summarize the current post
argument-hint: <post-id>
allowed-tools:
  - read_file
  - bash
model: claude-sonnet-4-6
---

Read post $1 and provide a summary.

Include the content from @wp-content/themes/active-theme/functions.php.
Also check the output of !`wp post get $1 --format=json`.
```

Frontmatter keys:

| Key             | Description                              |
| --------------- | ---------------------------------------- |
| `description`   | Short description shown in help          |
| `argument-hint` | Placeholder for the command argument     |
| `allowed-tools` | Restrict which tools the command can use |
| `model`         | Override the model for this command      |

Content expansion:

| Syntax               | Expands to                         |
| -------------------- | ---------------------------------- |
| `$1` or `$ARGUMENTS` | The argument passed to the command |
| `@path/to/file`      | Contents of the referenced file    |
| `` !`command` ``     | Output of the shell command        |

## 8. Tool Confirmation

By default, the agent asks for confirmation before executing tools that modify the system (bash, write_file, etc.). The `think` tool is always auto-approved.

Ways to control this:

| Method                               | Scope                    | Example                                                           |
| ------------------------------------ | ------------------------ | ----------------------------------------------------------------- |
| `WP_AI_AGENT_AUTO_CONFIRM`           | Global (wp-config.php)   | `define( 'WP_AI_AGENT_AUTO_CONFIRM', true );`                     |
| `auto_confirm` in settings.json      | Global (settings file)   | `"auto_confirm": true`                                            |
| `--yolo` CLI flag                    | Per-session              | `wp agent chat --yolo`                                            |
| `/yolo` slash command                | During session           | Type `/yolo` in chat                                              |
| `WP_AI_AGENT_BYPASSED_TOOLS`         | Per-tool (wp-config.php) | `define( 'WP_AI_AGENT_BYPASSED_TOOLS', 'think,read_file,glob' );` |
| `permissions.allow` in settings.json | Per-tool (settings file) | `"permissions": { "allow": ["bash", "read_file"] }`               |

## 9. Usage

### Interactive Chat

```bash
wp agent chat
wp agent chat --session=abc123     # Resume a session
wp agent chat --no-save            # Don't persist the session
wp agent chat --yolo               # Auto-confirm all tools
wp agent chat --debug              # Verbose output
```

### One-Shot Mode

```bash
wp agent ask "What plugins are active?"
wp agent ask "List posts from last week" --session=abc123
```

### In-Session Commands

| Command            | Description                               |
| ------------------ | ----------------------------------------- |
| `/model`           | Show current model and provider           |
| `/model <name>`    | Switch to a different model               |
| `/new`             | Clear conversation context (keep session) |
| `/yolo`            | Enable auto-confirm for all tools         |
| `/yolo off`        | Re-enable tool confirmation prompts       |
| `/quit` or `/exit` | End the session                           |

## 10. File Locations

| Path                           | Purpose                       |
| ------------------------------ | ----------------------------- |
| `~/.wp-ai-agent/settings.json` | Agent configuration           |
| `~/.wp-ai-agent/mcp.json`      | MCP server configuration      |
| `~/.wp-ai-agent/sessions/`     | Session storage               |
| `~/.wp-ai-agent/logs/`         | Log files                     |
| `~/.wp-ai-agent/commands/`     | User-level custom commands    |
| `.wp-ai-agent/commands/`       | Project-level custom commands |

All `~` paths expand to the user's home directory. Under WP-CLI, configuration resolves from `$HOME`.

## Further Reading

- [authentication.md](authentication.md) — Full credential reference
