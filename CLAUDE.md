# Craft CMS Plugin Development Guidelines

## Project Environment
- Runtime: DDEV / Docker
- Common DDEV commands:
  - `ddev start` / `ddev stop` / `ddev restart`
  - `ddev ssh` — shell into the web container
  - `ddev exec php craft ...` — run Craft CLI commands
  - `ddev composer ...` — run Composer inside the container
  - `ddev describe` — show project URLs and services

## Plugin Architecture
- Always use Craft CMS 5
- Always check the official documentation for the latest best practices: https://craftcms.com/docs/5.x/extend/
- Follow PSR-4 autoloading standards
- Organize code into appropriate namespaces and directories
- Use English for all code comments, docblocks, and commit messages

### Directory Structure
```
src/
├── Plugin.php            # Main plugin class
├── controllers/
│   ├── CpController.php  # Control Panel routes
│   └── ApiController.php # Frontend API routes
├── models/
│   └── Settings.php      # Plugin settings model
└── services/
    └── ApiService.php    # Business logic
templates/                # Twig templates for CP interface
CHANGELOG.md              # Updated with every change
```

## Code Style
- Follow PSR-12 coding standards
- Use strict types: `declare(strict_types=1);` at the top of every PHP file
- Type-hint all method parameters and return types
- Write docblocks for all public methods
- Keep controllers thin — delegate logic to services
- Never use `exit` or `die`; throw exceptions or return proper responses

## Craft CMS Conventions
- Register all components (services, controllers, etc.) in `Plugin::init()`
- Use `Craft::$app->...` for core service access
- Use `Craft::t('plugin-handle', '...')` for all user-facing strings
- Validate models before saving: always check `$model->validate()`
- Use `craft\web\Response` for API responses
- Prefer `craft\helpers\*` helper classes over custom utility functions
- Always check permissions with `$this->requirePermission(...)` in CP controllers

## Settings
- Define settings in `src/models/Settings.php` extending `craft\base\Model`
- Register settings via `Plugin::createSettingsModel()` and `Plugin::settingsHtml()`
- Store sensitive values (API keys, tokens) using environment variables — never hardcode them
- Use `craft\helpers\App::parseEnv()` to resolve env vars in settings

## Templates (Twig)
- Extend `_layouts/cp` for all CP pages
- Use `craft.app.view.registerAssetBundle()` for assets
- Always escape output with `|e` or `{{ }}` (auto-escaped) — never use `{# raw #}` without reason
- Keep templates logic-free; move complex logic to services

## Error Handling
- Use `craft\log\MonologTarget` or `Craft::error()` / `Craft::warning()` for logging
- Catch and log exceptions — never let them bubble silently
- Return meaningful HTTP status codes from API controllers (200, 400, 404, 500)

## Changelog
- Update `CHANGELOG.md` with every change
- Follow the [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) format
- Group entries under: `Added`, `Changed`, `Fixed`, `Removed`
- Use the next unreleased version header `## [Unreleased]` for ongoing work

## Git Workflow
- Write commit messages in English, imperative mood: `Add feature X`, `Fix bug in Y`
- One logical change per commit
- Branch naming: `feature/short-description`, `fix/short-description`
- Never commit secrets, `.env` files, or generated assets