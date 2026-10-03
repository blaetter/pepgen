# Changelog pepgen

All notable changes, grouped by topic. New entries go into the "Unreleased" section at the top; on deployment it gets the date.

Sections per version: Features, Security, Bugs, Removed, Maintenance (omit empty sections).

## Unreleased

### Features
- Configurable timezone for the token (`timezone`, default Europe/Berlin)
- `clear public` also removes leftover temporary zip files
- The config file can be chosen via the environment variable `PEPGEN_CONFIG`

### Security
- ePub ids with path or shell characters are denied, `zip` runs without a shell
- Responses no longer contain paths, exception messages or stack traces
- Only POST requests with string parameters are accepted
- Tokens are compared in constant time
- Names, email addresses and valid tokens are no longer logged; default log level INFO
- No directory listing for the download folder

### Bugs
- Downloads failed between midnight and 1 or 2 am (date of the token in UTC)
- Already generated ePubs were generated again on every request
- Character sequences like `$1` in the watermark were treated as back references
- An interrupted zip left temporary files in the download folder and incomplete ePubs that were delivered later
- Temporary copies were not removed after a successful generation
- The log was not written into the configured log directory
- `clear`: exit code 1 for `--dry-run`, `--days` took precedence over `--all`, invalid `--days` values were not detected
- Details of denied requests followed the message in the log without separator

### Maintenance
- Dependencies updated: Monolog 3, Symfony pinned to 6.4, abandoned `symfony/debug` removed; `composer audit` is clean
- Runs on PHP 8.2 to 8.5
- Tests run within their own installation in the temp directory and no longer delete real files
- CI tests PHP 8.2, 8.3 and 8.4 including code style; Travis CI removed
