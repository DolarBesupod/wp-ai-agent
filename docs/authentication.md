# Authentication

WP AI Agent supports four provider IDs:

- `anthropic` (Claude API key flow)
- `claudeCode` (Claude Code subscription flow)
- `openai` (OpenAI API key or Codex subscription flow)
- `google` (Gemini API key flow)

## Supported Providers

| Provider ID | Typical model routing | API key constant/env | Subscription constant/env |
|-------------|------------------------|----------------------|---------------------------|
| `anthropic` | `claude-*`             | `ANTHROPIC_API_KEY`  | `ANTHROPIC_SUBSCRIPTION_KEY` |
| `claudeCode` | Explicit provider path (no model prefix) | — | `CLAUDE_CODE_SUBSCRIPTION_KEY` |
| `openai`    | `gpt-*`, `o1-*`, `o3-*`, `o4-*`, `chatgpt-*` | `OPENAI_API_KEY` | `OPENAI_SUBSCRIPTION_KEY` |
| `google`    | `gemini-*`, `models/gemini-*` | `GOOGLE_API_KEY` | — |

## Anthropic API Key vs Claude Code Subscription

Both paths can use Claude models, but credentials differ:

- `anthropic` uses an Anthropic API key (`x-api-key` auth).
- `claudeCode` uses an Anthropic setup-token (`Authorization: Bearer ...`).

Setup-token validation for `claudeCode` subscription mode:

- Expected prefix: `sk-ant-oat01-`
- Minimum length: 80
- Invalid/mismatched secrets are rejected with actionable CLI errors.
  Example: pasting an Anthropic API key into `claudeCode` subscription mode instructs you to use `--provider=anthropic --mode=api_key`.

## 1. PHP Constants (`wp-config.php`)

```php
// Anthropic API key (default Claude model routing)
define( 'ANTHROPIC_API_KEY', 'sk-ant-your-key-here' );

// Claude Code subscription token (setup-token)
define( 'CLAUDE_CODE_SUBSCRIPTION_KEY', 'sk-ant-oat01-...' );

// OpenAI
define( 'OPENAI_API_KEY', 'sk-your-key-here' );

// Google
define( 'GOOGLE_API_KEY', 'your-key-here' );

// OpenAI Codex subscription token
define( 'OPENAI_SUBSCRIPTION_KEY', 'your-codex-access-token' );
```

## 2. Environment Variables

```bash
# Anthropic API key
export ANTHROPIC_API_KEY=sk-ant-your-key-here

# Claude Code setup-token
export CLAUDE_CODE_SUBSCRIPTION_KEY=sk-ant-oat01-...

# OpenAI
export OPENAI_API_KEY=sk-your-key-here

# OpenAI Codex subscription token
export OPENAI_SUBSCRIPTION_KEY=your-codex-access-token

# Google
export GOOGLE_API_KEY=your-key-here

wp agent chat
```

## 3. Database Storage (`wp agent auth`)

```bash
# API key flows
wp agent auth set --provider=anthropic
wp agent auth set --provider=openai
wp agent auth set --provider=google

# Subscription flows
wp agent auth set --provider=claudeCode --mode=subscription
wp agent auth set --provider=openai --mode=subscription
```

Interactive subscription prompts are provider-aware:

- `openai` subscription prompt references `codex login`.
- `claudeCode` subscription prompt references `claude setup-token`.

## Priority Chain

Credentials are resolved per provider in this order:

1. Provider API key constant
2. Provider API key environment variable
3. Provider subscription constant
4. Provider subscription environment variable
5. Database credential (`wp agent auth set`)

When both API key and subscription credentials are configured for the same provider, API key takes priority.

## Provider Selection Behavior

- Model prefix detection selects:
  - `anthropic` for `claude-*`
  - `openai` for OpenAI prefixes
  - `google` for Gemini prefixes
- `claudeCode` is a dedicated provider path and is not selected by a model prefix.
- Provider-specific auth validation still applies when you configure credentials through `wp agent auth`.

## Using OpenAI Codex CLI Tokens

OpenAI's [Codex CLI](https://github.com/openai/codex) can authenticate with your ChatGPT account via OAuth. The resulting access token works as a subscription key for WP AI Agent.

Important details:

- Codex tokens target ChatGPT backend endpoints, not standard OpenAI platform API key endpoints.
- Codex subscription tokens should be configured for `openai` with `--mode=subscription`.

Setup:

1. Run `codex login`.
2. Copy `access_token` from `~/.codex/auth.json`.
3. Store it as `OPENAI_SUBSCRIPTION_KEY` (constant/env) or with:
   `wp agent auth set --provider=openai --mode=subscription`.

## Choosing a Model

Default model: `claude-sonnet-4-6`.

```php
// OpenAI
define( 'WP_AI_AGENT_MODEL', 'gpt-4o' );

// Google
define( 'WP_AI_AGENT_MODEL', 'gemini-2.0-flash' );
```

Runtime model switching:

```
/model
/model gpt-4o
/model gemini-2.0-flash
/model claude-sonnet-4-6
```

## Managing Credentials

View all credential statuses:

```bash
wp agent auth status
```

View one credential:

```bash
wp agent auth get --provider=anthropic
wp agent auth get --provider=claudeCode
wp agent auth get --provider=openai
```

Delete DB credential:

```bash
wp agent auth delete --provider=claudeCode
```

## Security Notes

- CLI output always masks secrets.
- Database credentials use `autoload=false`.
- Constants/environment values take precedence over DB values.
- Never commit secrets to version control.

## Out of Scope

Google Antigravity / Cloud Code OAuth integration is out of scope for this authentication change.
