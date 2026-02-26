---
description: List and explain all WordPress hooks (actions and filters) in a PHP file
parameters:
  file_path:
    type: string
    description: Path to the PHP file to analyze
    required: true
requires_confirmation: false
---

Analyze all WordPress hooks in this file:

@$file_path

Find and document every occurrence of:
- `add_action( 'hook_name', ... )` — hook name, callback, priority, accepted_args
- `add_filter( 'hook_name', ... )` — same
- `remove_action()` and `remove_filter()` — what is being removed and why
- `do_action( 'hook_name', ... )` — custom actions fired by this code
- `apply_filters( 'hook_name', ... )` — custom filters applied by this code

For each hook, explain:
1. What it does and when it fires in the WordPress lifecycle
2. What the registered callback does (if readable from this file)
3. Any security implications (are capability checks or nonce verification nearby?)

Present results in two groups:
- **Registered Hooks** (add_action / add_filter / remove_action / remove_filter)
- **Fired Hooks** (do_action / apply_filters)

If the file registers no hooks, say so explicitly.
