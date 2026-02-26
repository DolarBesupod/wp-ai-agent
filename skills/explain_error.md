---
description: Analyze a PHP or WordPress error and suggest a fix
parameters:
  error:
    type: string
    description: The error message or stack trace to analyze
    required: true
requires_confirmation: false
---

Analyze this PHP/WordPress error and provide a structured diagnosis:

$error

Work through these steps:
1. Identify the error type (Fatal, Warning, Notice, Exception, WP_Error)
2. Find the root cause from the message and stack trace
3. If the trace includes a file path, use read_file to inspect the relevant lines
4. Suggest a specific fix with example code where applicable
5. Note whether this is a WordPress-specific issue, a PHP version issue, or a plugin/theme conflict

Format your response as: **Error Type**, **Root Cause**, **Fix**, **Prevention**.
