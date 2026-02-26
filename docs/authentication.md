# Authentication

WP AI Agent needs Anthropic credentials to communicate with Claude. You can provide API-key or subscription credentials.

## 1. PHP Constants

Define credentials in `wp-config.php`:

```php
define( 'ANTHROPIC_API_KEY', 'sk-ant-your-key-here' );
// optional: subscription credential
define( 'ANTHROPIC_SUBSCRIPTION_KEY', 'sub-your-key-here' );
```

This is the most common setup. Run `wp agent init` to set it interactively.

## 2. Environment Variables

Export the variable before running WP-CLI:

```bash
export ANTHROPIC_API_KEY=sk-ant-your-key-here
# or for subscription mode
export ANTHROPIC_SUBSCRIPTION_KEY=sub-your-key-here
wp agent chat
```

Useful for CI pipelines, Docker containers, or `.env`-based setups.

## 3. Database Storage

Store credentials in the WordPress database using the auth CLI:

```bash
wp agent auth set --provider=anthropic
# subscription mode
wp agent auth set --provider=anthropic --mode=subscription
```

You'll be prompted to enter the key (input is hidden). The key is stored as a WordPress option (`wp_ai_agent_credential_anthropic`) and is never exposed in files on disk.

This is useful when you don't have access to `wp-config.php` or prefer managing credentials through CLI commands.

## Priority Chain

When the agent starts, it resolves credentials in this order:

1. `ANTHROPIC_API_KEY` PHP constant
2. `ANTHROPIC_API_KEY` environment variable
3. `ANTHROPIC_SUBSCRIPTION_KEY` PHP constant
4. `ANTHROPIC_SUBSCRIPTION_KEY` environment variable
5. Database credential (set via `wp agent auth set`)

If none are found, the agent exits with an error message.

If you have the key in `wp-config.php` **and** in the database, the constant always wins. This means you can safely store a fallback in the DB without it overriding your production config.

## Managing Credentials

### View all configured credentials

```bash
wp agent auth status
```

Shows a table with each provider's auth mode, where the credential comes from (constant, env, or db), and a masked version of the secret.

### View a specific credential

```bash
wp agent auth get --provider=anthropic
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
