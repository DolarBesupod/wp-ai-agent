---
description: Diagnose a WordPress problem with a structured investigation workflow
parameters:
  issue:
    type: string
    description: Description of the problem to investigate
    required: true
requires_confirmation: false
---

Diagnose this WordPress issue using a structured investigation workflow:

**Issue:** $issue

Work through these steps in order, stopping when you find the root cause:

1. **Error log** — run `wp --info` to find the PHP log path, then read the last 100 lines of that log
2. **Core integrity** — run `wp core verify-checksums` to check for modified WordPress core files
3. **Plugin status** — run `wp plugin list --format=table` and look for inactive, error, or update-available states
4. **Theme status** — run `wp theme list --format=table`
5. **Database** — run `wp db check` to detect table corruption or missing tables
6. **Cron** — run `wp cron event list` to spot stuck or suspicious scheduled events

After each step, assess whether the findings explain the reported issue before continuing.

Finish with a clear summary: **Root Cause**, **Affected Component**, **Recommended Fix**.
