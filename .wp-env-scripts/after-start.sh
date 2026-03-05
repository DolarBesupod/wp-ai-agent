#!/usr/bin/env bash
#
# wp-env afterStart lifecycle script.
#
# Installs Node.js and MCP server packages in the dev CLI container
# so stdio MCP servers work out of the box for E2E testing.
#
# Safe to re-run — skips installation if Node is already present.

set -euo pipefail

CLI_CONTAINER=$(docker ps --filter "name=-cli-1" --format '{{.Names}}' | grep -v tests | head -1)

if [ -z "$CLI_CONTAINER" ]; then
	echo "[after-start] Dev CLI container not found, skipping MCP setup."
	exit 0
fi

echo "[after-start] Setting up dev CLI container: $CLI_CONTAINER"

# Install Node.js if not already present.
if docker exec "$CLI_CONTAINER" sh -c 'command -v node' > /dev/null 2>&1; then
	echo "[after-start] Node.js already installed: $(docker exec "$CLI_CONTAINER" node --version)"
else
	echo "[after-start] Installing Node.js and npm..."
	docker exec --user root "$CLI_CONTAINER" sh -c 'apk add --no-cache nodejs npm > /dev/null 2>&1'
	echo "[after-start] Node.js installed: $(docker exec "$CLI_CONTAINER" node --version)"
fi

# Install MCP server packages if not already present.
if docker exec "$CLI_CONTAINER" sh -c 'command -v mcp-server-everything' > /dev/null 2>&1; then
	echo "[after-start] MCP server packages already installed."
else
	echo "[after-start] Installing MCP server packages..."
	docker exec --user root "$CLI_CONTAINER" sh -c \
		'npm install -g @modelcontextprotocol/server-filesystem @modelcontextprotocol/server-everything > /dev/null 2>&1'
	echo "[after-start] MCP servers installed."
fi

echo "[after-start] Done."
