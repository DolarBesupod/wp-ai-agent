# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2026-03-05

### Added

- AI agent with ReAct loop (Think, Act, Observe).
- WP-CLI commands: `wp agent chat` (interactive) and `wp agent ask` (one-shot).
- Built-in tools: bash, glob, grep, read_file, write_file, think.
- MCP client support (stdio and HTTP transports).
- Custom slash commands system.
- Skills system (WordPress options-backed).
- Session persistence.
- Multi-provider AI support (Anthropic, OpenAI, Google).
- Yolo mode (auto-confirm tool executions).
- Credential management via `wp agent auth`.
