# Easy Transients for WordPress

Provides a simple wrapper to handle transients in the WordPress-backend as admin_notices or actions.

## Requirements

* PHP 8.2 or newer.
* _composer_ to install this package.
* WordPress-plugin, theme or _Code Snippet_-plugin to embed them in your project.

## Installation

1. ``composer require threadi/easy-transients-for-wordpress``
2. Use a tool like [Strauss](https://github.com/BrianHenryIE/strauss) or [PHP-Scoper](https://github.com/humbug/php-scoper) to prefix the namespace `easyTransientsForWordPress` in your project (see "Usage in multiple projects").
3. Add the configuration (see below) to your WordPress-project (plugin or theme).

## Configuration

Configure the library early (e.g. on `plugins_loaded`) and call `init()` afterward:

```php
use easyTransientsForWordPress\Transients;

$transients_obj = Transients::get_instance();
$transients_obj->set_slug( 'my-plugin' );
$transients_obj->set_capability( 'manage_options' );
$transients_obj->set_path( plugin_dir_path( __FILE__ ) . 'vendor/threadi/easy-transients-for-wordpress/src/' );
$transients_obj->set_url( plugin_dir_url( __FILE__ ) . 'vendor/threadi/easy-transients-for-wordpress/src/' );
$transients_obj->set_vendor_path( plugin_dir_path( __FILE__ ) . 'vendor/' ); // optional.
$transients_obj->set_display_method( 'grouped' ); // "single" (default) or "grouped".
$transients_obj->set_translations(
    array(
        'hide_message'  => __( 'Hide this message for %1$d days.', 'my-plugin' ),
        'dismiss'       => __( 'Dismiss', 'my-plugin' ),
        'dismiss_error' => __( 'The message could not be hidden. Please reload the page and try again.', 'my-plugin' ),
    )
);
$transients_obj->init();
```

## Usage

### Show a notice

```php
$transient_obj = Transients::get_instance()->add();
$transient_obj->set_name( 'my_plugin_hint' );
$transient_obj->set_message( __( 'My message', 'my-plugin' ) );
$transient_obj->set_type( 'success' ); // error, warning, success, hint or info.
$transient_obj->set_dismissible_days( 14 ); // 0 = show only once.
$transient_obj->save();
```

### Run an action

A transient without message but with an action will be run at the end of the next request (frontend or backend).
The action must be a static method.

```php
$transient_obj = Transients::get_instance()->add();
$transient_obj->set_name( 'my_plugin_task' );
$transient_obj->set_action( array( 'MyPlugin\Tasks', 'run_task' ) );
$transient_obj->save();
```

Hint: transients are not a permanent storage. If an external object cache (e.g. Redis) is used, transients could be
removed at any time. Do not use actions for tasks which must be run in any case.

## Templates

The templates could be overridden. They will be searched in this order:

1. In the active theme: `{theme}/{slug}/vendor/threadi/easy-transients-for-wordpress/templates/{template}`
2. In your project: `{vendor-path}/../templates/vendor/threadi/easy-transients-for-wordpress/templates/{template}`
3. The templates of this library in `src/Templates/`.

The resulting path could also be changed with the filter `etfw_{slug}_template`.

## Usage in multiple projects

If more than one plugin uses this library, each of them must prefix the namespace (e.g. with Strauss).
Without that, all plugins would share the same object and overwrite the settings of each other.

All projects should use version 1.5.0 or newer, as older versions answer the AJAX-request to dismiss notices
also for notices of other projects.

## Development

Requirements: PHP 8.0 or newer, Composer, a local MySQL/MariaDB for the tests.

```bash
composer install
composer test-install   # sets up the WordPress test environment, see bin/install-wp-tests.sh
composer test           # runs PHPUnit
composer phpcs          # checks the coding standards (phpcbf fixes most of them)
composer phpstan        # runs PHPStan
```

GitHub Actions run PHPCS, PHPStan and the tests for each pull request.

### Release

1. Update the version in `composer.json` and add an entry in `changelog.md`.
2. Push a tag `build-<version>` (e.g. `build-1.5.0`) or start the workflow "Release" manually.
3. The workflow checks the version and the code, creates the tag `<version>` and a GitHub release with the notes from `changelog.md`.

## Changelog

See [changelog.md](changelog.md).
