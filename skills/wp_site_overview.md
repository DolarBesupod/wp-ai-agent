---
description: Get a comprehensive overview of the WordPress site using WP-CLI
requires_confirmation: false
---

# WordPress Site Overview

**WordPress Core:**
Version: !`wp core version`
Site URL: !`wp option get siteurl`
Blog Name: !`wp option get blogname`

**Plugins:**
!`wp plugin list --fields=name,status,version,update --format=table`

**Active Theme:**
!`wp theme list --status=active --fields=name,version,update --format=table`

**Administrator Accounts:**
!`wp user list --role=administrator --fields=ID,user_login,user_email --format=table`

**Scheduled Cron Events:**
!`wp cron event list --fields=hook,next_run_relative,recurrence --format=table`

---

Summarize the site health status. Flag anything that needs attention: outdated plugins or themes, multiple administrator accounts, suspicious cron events, or anything unusual.
