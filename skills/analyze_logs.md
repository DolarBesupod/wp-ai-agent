---
description: Read and analyze the WordPress debug log for errors and warnings
parameters:
  lines:
    type: integer
    description: Number of recent log lines to read
    required: false
    default: 100
requires_confirmation: false
---

Analyze the WordPress debug log for recent errors and warnings.

Steps:
1. Run `wp config get WP_DEBUG_LOG` to find the configured log path (may be `true`, `false`, or a file path)
2. If the value is `true`, the log is at `wp-content/debug.log` relative to the WordPress root
3. Run `wp --info` to confirm the WordPress root path if needed
4. Read the last $lines lines of the log file using bash: `tail -n $lines /path/to/debug.log`
5. Parse the output and group entries by severity:
   - **Fatal** — PHP Fatal error, Uncaught exception, call to undefined function
   - **Warning** — PHP Warning, deprecated function calls
   - **Notice** — PHP Notice, doing_it_wrong() calls
6. For each unique error: note the frequency, source file and line, and likely cause

Present a prioritized list with the most critical issues first. For the top 3 issues, suggest a specific fix.
