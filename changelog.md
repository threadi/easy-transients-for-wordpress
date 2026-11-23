# Changelog

## [1.5.0] - 23.11.2026

### Added

- Added PHPCS, PHPStan (level 8) and PHPUnit tests incl. GitHub Actions for testing and releases
- Actions of transients are run only once, even with parallel requests; errors in actions are caught and logged
- Expired dismiss-markers are removed, new markers are not autoloaded
- JS shows an error if dismissing a notice failed and handles only notices of this library
- Added filter `etfw_{slug}_template` to change the path of templates

### Changed

- Styles are written without CSS nesting
- Scripts and styles are only loaded for users with the capability and if notices exist
- Dismissing a notice uses the dismissible days of the transient instead of the value from the request
- Templates in themes are searched in `{theme}/{slug}/vendor/threadi/easy-transients-for-wordpress/templates/`
- PHP 8.0 is now required in composer.json
- All files use `declare(strict_types=1)`

### Fixed

- Fixed changed messages of existing transients not being shown (the list of transients saved complete objects)
- Fixed possible fatal errors with invalid or old entries in the list or in a transient
- Fixed dismissing notices if multiple projects use this library
- Fixed grouped view without configured template showing nothing
- Fixed sorting of unknown types and prioritized transients
- Fixed detection of templates in projects which use this library
- Fixed hide_on for backend URLs with "/" or encoded chars in query string
