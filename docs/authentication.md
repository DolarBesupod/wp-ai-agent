# Authentication

WP AI Agent supports three AI providers: **Anthropic** (Claude), **OpenAI** (GPT), and **Google** (Gemini). The provider is auto-detected from the model name — you only need to supply the correct API key.

## Supported Providers

| Provider | Model prefixes | API key constant | API key env var | Subscription constant |
|----------|---------------|-----------------|-----------------|----------------------|
| Anthropic | `claude-*` | `ANTHROPIC_API_KEY` | `ANTHROPIC_API_KEY` | `ANTHROPIC_SUBSCRIPTION_KEY` |
| OpenAI | `gpt-*`, `o1-*`, `o3-*`, `o4-*`, `chatgpt-*` | `OPENAI_API_KEY` | `OPENAI_API_KEY` | `OPENAI_SUBSCRIPTION_KEY` |
| Google | `gemini-*`, `models/gemini-*` | `GOOGLE_API_KEY` | `GOOGLE_API_KEY` | — |

Both Anthropic and OpenAI support subscription authentication. Anthropic uses `ANTHROPIC_SUBSCRIPTION_KEY` and OpenAI uses `OPENAI_SUBSCRIPTION_KEY` (e.g., a Codex CLI access token). Google uses API keys only.

## 1. PHP Constants

Define credentials in `wp-config.php`:

```php
// Anthropic (default provider)
define( 'ANTHROPIC_API_KEY', 'sk-ant-your-key-here' );

// OpenAI (needed only if you use GPT models)
define( 'OPENAI_API_KEY', 'sk-your-key-here' );

// Google (needed only if you use Gemini models)
define( 'GOOGLE_API_KEY', 'your-key-here' );
```

You only need the key for the provider you are using. Run `wp agent init` to set up Anthropic interactively.

To use subscription authentication instead of an API key:

```php
// Anthropic subscription
define( 'ANTHROPIC_SUBSCRIPTION_KEY', 'sub-your-key-here' );

// OpenAI subscription (Codex CLI token)
define( 'OPENAI_SUBSCRIPTION_KEY', 'your-codex-access-token' );
```

## 2. Environment Variables

Export the variable before running WP-CLI:

```bash
# Anthropic
export ANTHROPIC_API_KEY=sk-ant-your-key-here

# OpenAI
export OPENAI_API_KEY=sk-your-key-here

# Google
export GOOGLE_API_KEY=your-key-here

# Anthropic subscription
export ANTHROPIC_SUBSCRIPTION_KEY=sub-your-key-here

# OpenAI subscription (Codex CLI token)
export OPENAI_SUBSCRIPTION_KEY=your-codex-access-token

wp agent chat
```

Useful for CI pipelines, Docker containers, or `.env`-based setups.

## 3. Database Storage

Store credentials in the WordPress database using the auth CLI:

```bash
wp agent auth set --provider=anthropic
wp agent auth set --provider=openai
wp agent auth set --provider=google

# Anthropic subscription mode
wp agent auth set --provider=anthropic --mode=subscription

# OpenAI subscription mode
wp agent auth set --provider=openai --mode=subscription
```

You'll be prompted to enter the key (input is hidden). The key is stored as a WordPress option (`wp_ai_agent_credential_{provider}`) and is never exposed in files on disk.

This is useful when you don't have access to `wp-config.php` or prefer managing credentials through CLI commands.

## Priority Chain

For each provider, credentials are resolved in this order:

1. PHP constant (e.g., `ANTHROPIC_API_KEY`, `OPENAI_API_KEY`)
2. Environment variable (e.g., `ANTHROPIC_API_KEY`, `OPENAI_API_KEY`)
3. Subscription constant (Anthropic and OpenAI: `ANTHROPIC_SUBSCRIPTION_KEY` / `OPENAI_SUBSCRIPTION_KEY`)
4. Subscription environment variable (Anthropic and OpenAI)
5. Database credential (set via `wp agent auth set`)

Steps 3-4 apply to Anthropic and OpenAI only. Google does not support subscription authentication. If a provider has both an API key and a subscription key configured, the API key always takes priority.

If none are found, the agent exits with an error message telling you which constant to define.

If you have the key in `wp-config.php` **and** in the database, the constant always wins. This means you can safely store a fallback in the DB without it overriding your production config.

## Using Codex CLI Tokens

OpenAI's [Codex CLI](https://github.com/openai/codex) can authenticate with your ChatGPT account via OAuth. The resulting access token works as a subscription key for the WP AI Agent.

**Important:** Codex CLI tokens authenticate against the ChatGPT backend API (`chatgpt.com/backend-api/codex/`), not the standard OpenAI platform API (`api.openai.com/v1`). These are separate systems — a ChatGPT subscription does not include OpenAI API credits, and vice versa. The plugin handles the endpoint routing automatically when subscription mode is active.

### Setup

1. Install Codex CLI and run `codex login` to authenticate with your ChatGPT account.
2. Codex caches an access token in `~/.codex/auth.json`.
3. Copy the `access_token` value from that file and use it as `OPENAI_SUBSCRIPTION_KEY` — either as a PHP constant in `wp-config.php`, an environment variable, or via `wp agent auth set --provider=openai --mode=subscription`.
4. Tokens expire. When your token stops working, re-run `codex login` to obtain a fresh one and update the stored value.

### Available Models

When using a Codex CLI subscription token, only Codex-specific models are supported: `gpt-5.2-codex`, `gpt-5.3-codex`, and similar. Standard OpenAI models like `gpt-4o` do **not** work with subscription tokens — they require an OpenAI API key.

The plugin automatically handles the streaming requirement (`stream: true`) and SSE response parsing for the ChatGPT backend.

This is useful if you have a ChatGPT subscription (e.g., Plus or Pro) but no standalone OpenAI API key.

## Choosing a Model

The default model is `claude-sonnet-4-6` (Anthropic). To change it, define `WP_AI_AGENT_MODEL` in `wp-config.php`:

```php
// Use OpenAI
define( 'WP_AI_AGENT_MODEL', 'gpt-4o' );

// Use Google Gemini
define( 'WP_AI_AGENT_MODEL', 'gemini-2.0-flash' );
```

The provider is detected automatically from the model name — no separate provider setting is needed.

**Note:** If using an OpenAI subscription token (Codex CLI), you must use a Codex-specific model (e.g., `gpt-5.2-codex`). Standard models like `gpt-4o` require an API key.

### Switching Models at Runtime

During a chat session, use the `/model` command:

```
/model              # Show current model and provider
/model gpt-4o       # Switch to OpenAI GPT-4o
/model gemini-2.0-flash  # Switch to Google Gemini
/model claude-sonnet-4-6  # Switch back to Anthropic
```

When you switch to a model from a different provider, the agent resolves credentials for the new provider automatically. If the API key is missing, you get an error message and the current model stays unchanged — the session continues.

## Managing Credentials

### View all configured credentials

```bash
wp agent auth status
```

Shows a table with each provider's auth mode, where the credential comes from (constant, env, or db), and a masked version of the secret.

### View a specific credential

```bash
wp agent auth get --provider=anthropic
wp agent auth get --provider=openai
```

Displays the provider, auth mode, masked secret, and timestamps. The raw secret is never shown.

### Remove a database credential

```bash
wp agent auth delete --provider=anthropic
```

This only removes the DB-stored credential. Constants and environment variables are unaffected.

## Security Notes

- Secrets stored in the database are never displayed in full — CLI output always masks them (e.g., `sk-ant-ap****`).
- Database credentials are stored with `autoload=false`, so they are not loaded on every WordPress page request.
- The `wp agent auth status` command shows where each credential comes from, making it easy to audit your setup.
- Never commit API keys to version control. Use `wp-config.php` (which should be gitignored) or environment variables.
