# LuxMoniterServer

Monitoring/management UI for LuxPower-style hybrid solar inverters (Laravel 13, PHP 8.3, Vue 3 SPA).
Ingest does **not** happen in this repo: an ESP32 polls the inverter over Modbus and publishes MQTT; an external Node.js worker writes InfluxDB (time series) and Redis (latest state). Laravel is the read/UI + settings-write layer.

## Commands

- Dev (server + queue + vite): `composer run dev`
- Tests: `composer test` (config:clear → `pint --test` → phpstan → pest). Faster loop: `php artisan test --compact --filter=SomeTest`
- Style fix: `composer lint` or `vendor/bin/pint --dirty --format agent`
- Static analysis: `composer types:check` (Larastan level 7, no baseline)
- Frontend: `npm run build` / `npm run dev` — Filament themes are Vite inputs, a missing entry breaks the panel

## Where things live

| Area | Location |
| --- | --- |
| Vue SPA | `resources/js/` (Pinia, Vue Router) — index at `/` |
| API | `routes/api.php` (`/v1/*`), controllers at `app/Http/Controllers/Api/V1/` |
| Services | `app/Services/{Influx,Mqtt,Realtime,DeviceSettings}Service.php` — container auto-wired, no interfaces or bindings |
| Inverter register map | `app/Support/InverterSettings.php` — mirrors `Esp32/src/inverter_settings/page.html` |
| Themes | `resources/css/app.css` (Tailwind v4) |
| Skills | `.agents/skills/**` (laravel-best-practices, pest-testing, inverter-settings, ponytail*) |

The application is a pure SPA, access to resources is protected via API endpoints and `auth:sanctum`. All interactions happen through JSON APIs.

Read path: Redis `device:<serial>:latest|online` via `RealtimeService` → `DeviceTelemetryController::realtime`; InfluxDB via `InfluxService` → `DeviceTelemetryController::{history,dashboard}`.
Write path: `DeviceSettingsService::save()` → `MqttService::publishSettings` → MQTT `luxmonitor/<serial>/cmd/settings` → ESP32 → `cmd/result` → Redis `device:<serial>:cmd_result` → blocking poll in `pollRegs()` (25s timeout) → audit row in `device_commands`.

## Conventions

- Vietnamese for UI strings, notifications and comments. Keep it.
- Vue SPA layout: `resources/js/views` for pages, `resources/js/components` for reusable parts.
- Services use promoted `readonly` constructor properties, throw `RuntimeException`; catch `\Throwable` at the API boundary and surface errors as JSON (which FE toasts).
- Theme tweaks go in `resources/css/app.css` (Tailwind v4, CSS-first, no `tailwind.config.js`).
- Time: Influx stores UTC — convert with `setTimezone('UTC')->toIso8601ZuluString()` before Flux ranges, group days in PHP local time.

## Testing

- Pest + `RefreshDatabase` on in-memory SQLite (`phpunit.xml`); the real `.env` is MySQL.
- No Influx/MQTT/Redis is available in tests — fake or inject the services instead of hitting them.
- Only the stock `ExampleTest` stubs exist today.

## Gotchas

- The app needs MySQL + Redis + InfluxDB 2.x + an MQTT broker; `php artisan serve` alone shows nothing useful.
- Missing `cmd/result` usually means the external Node worker is not running, not an app bug.
- `DeviceSettingsService::read()` blocks up to 25s inside a web request — never call it in a loop.
- Flux reserved words: fields are `import_power` / `export_power`; `aggregateWindow(…, location: …)` takes a record, not a string.
- Alpine: inside directive expressions `this` is the DOM element, not the component.
- `storage/diag_influx2.php` is a committed standalone Influx diagnostic (hardcoded serial `4313800597`) — not wired to any command.
- `.ai/mcp/mcp.json` points at another machine's paths (`C:\Users\LeoPotato\…`), so the Boost MCP server does not start here.
- `.env.example` is stock Laravel and omits the real service keys: `INFLUX_{URL,TOKEN,ORG,BUCKET}`, `MQTT_{HOST,PORT,USERNAME,PASSWORD,CLIENT_ID}`, Redis vars.

## Code navigation

- A CodeGraph index lives in `.codegraph/` (git-ignored, 121 files / ~8k symbols). It replaces grep+read loops: prefer it over chained searches. `codegraph sync` after edits, `codegraph index` for a full rebuild, `codegraph status` to check staleness.
- The codegraph MCP server has no default project — every `codegraph_*` call must pass `projectPath: d:\Php\CODE\LuxMonitor\LuxMoniterServer`, otherwise it cannot resolve an index.

## Agent customizations

- `.github/copilot-instructions.md` holds the ponytail lazy-senior-dev ruleset — leave it in place.
- `.github/instructions/*.instructions.md` attach by glob: `filament-theme` (`resources/css/filament/**`), `realtime-telemetry` (`app/Livewire/**`, `resources/views/livewire/**`, `RealtimeController`).
- `.github/agents/codegraph-planner.agent.md` is a read-only agent (`tools: [read, search, codegraph/*]`) for research/planning in Ask- or Plan-like work — the built-in Ask/Plan modes cannot be given MCP tools.
- `.agents/skills/inverter-settings/` documents the register-map read/write round-trip; it is a hand-written project skill, not tracked in `skills-lock.json`.
- The section above this Boost block is maintained by hand; the Boost block below is generated by `php artisan boost:update`.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.3. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record a rule with `record-rule` only when the user explicitly asks for one. Instructions for the work at hand are not rules, no matter how emphatic: "remove this typo", "use X here" are work to do, not rules to record. Never record a rule on your own initiative, as a byproduct of a change, or to summarize what you just did. When the user does ask, pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Use `record-rule` rather than your native memory or notes tool, because native memory is personal and session-scoped, while only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== tests rules ===

# Test Enforcement

- Add or update tests for behavior and logic changes when a test provides meaningful regression coverage.
- Pure copy, styling, and layout-only changes do not require new or updated tests.
- When test coverage applies, run the affected tests and ensure they pass.
- Test the changed behavior and its important failure modes, but do not add tests beyond them.
- Read the `testing-best-practices` skill before writing tests.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

</laravel-boost-guidelines>
