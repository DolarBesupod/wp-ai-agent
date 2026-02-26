---
description: Scaffold a new WordPress plugin with standard structure and boilerplate
parameters:
  plugin_name:
    type: string
    description: Human-readable plugin name (e.g. "My Awesome Plugin")
    required: true
  description:
    type: string
    description: Short one-line plugin description
    required: true
  slug:
    type: string
    description: Plugin slug for file names and text domain (e.g. my-awesome-plugin)
    required: true
  namespace:
    type: string
    description: Root PHP namespace for plugin classes (e.g. MyVendor\MyPlugin)
    required: true
requires_confirmation: true
---

Scaffold a WordPress plugin with these details:

- **Name:** $plugin_name
- **Description:** $description
- **Slug:** $slug
- **Namespace:** $namespace

Create the following files using write_file:

1. **$slug/$slug.php** — Main plugin file with:
   - WordPress plugin header comment (Plugin Name, Description, Version: 0.1.0, Requires at least: 6.0, Requires PHP: 8.1, Text Domain: $slug)
   - `declare(strict_types=1);`
   - Version constant: `define( '$slug_VERSION', '0.1.0' );` (uppercase slug with underscores)
   - Composer autoloader: `require_once __DIR__ . '/vendor/autoload.php';`
   - Initialization: `add_action( 'plugins_loaded', [ \$namespace\Plugin::class, 'getInstance' ] );`

2. **$slug/composer.json** — with:
   - `name` field as `vendor/$slug`
   - `autoload.psr-4` mapping `"$namespace\\"` to `"includes/"`
   - `require` for `"php": ">=8.1"`

3. **$slug/includes/Plugin.php** — with:
   - `namespace $namespace;`
   - `declare(strict_types=1);`
   - Singleton pattern: private static `$instance`, public static `getInstance(): static`
   - Public `register(): void` method hooked into `init` that calls `load_plugin_textdomain()`

Follow PSR-12 with tabs for indentation. Add `@since 0.1.0` PHPDoc to all public methods.
