# acms-plugin-skeleton

A starter skeleton for developing an **a-blog cms** plugin. It is based on the `SamplePlugin`
bundled with a-blog cms, lightly modernized, and comes with a ready-to-run **PHPUnit** setup
(`appleple/acms-testing-framework`).

## Requirements

- **Production:** PHP 8.1 – 8.5 (`require.php` is `^8.1`).
- **Development / CI:** PHP 8.5 (`config.platform.php` is `8.5.0`; the bundled Docker image is
  `php8.5`). Coding standards (phpcs) and static analysis (phpstan) check the whole 8.1 – 8.5 range.

## What's inside

| File / dir | Purpose |
|---|---|
| `src/ServiceProvider.php` | Plugin registration (hooks, correctors, validators, admin templates). |
| `src/GET/Sample.php` | Example display module (`<!-- BEGIN_MODULE Sample -->`). Thin; delegates to a service. |
| `src/POST/Sample.php` | Example action module (`ACMS_POST_Sample`). |
| `src/Modules/Get/V2/Sample.php` | Example V2 (Twig) module returning a data array; call via `module('V2_Sample')`. |
| `src/Hook.php` | Extension points (e.g. `extendsGlobalVars`, `extendsTwig`). |
| `src/Corrector.php` | Custom template output filter, e.g. `{var}[sample('...')]`. |
| `src/Validator.php` | Custom form validator (with `#[AsValidationOption]`). |
| `src/Services/SampleService.php` | Business logic extracted so it can be unit tested. |
| `src/template/…` | Twig settings screen (`acms_config`) + module config/select templates. |
| `tests/` | `Unit/` (no DB) and `Integration/` (DB, auto rollback). |
| `.github/workflows/test.yml` | Sample CI workflow. |

## Quick start

```bash
# 1. Generate your plugin from this skeleton (you'll be asked for a name).
composer create-project appleple/acms-plugin-skeleton my-awesome-plugin
cd my-awesome-plugin

# 2. Start a-blog cms + MySQL.
cp .env.example .env
docker compose up -d
# → open http://localhost:8080 and finish the setup wizard

# 3. Run the tests (from /workspace, where the whole repo is mounted).
docker compose exec acms bash -lc "cd /workspace && composer install && vendor/bin/phpunit"
```

> **Note:** the bundled `docker-compose.yml` references `appleple/acms` (an all-in-one image, in
> preparation) and `appleple/acms-testing-framework` is being published to Packagist. Until both are
> available, see [Before publishing](#before-publishing-git-clone) and
> [Running tests against your own core](#running-tests-against-your-own-core).

## How `composer create-project` works

`composer create-project VENDOR/PACKAGE target-dir` does three things:

1. **Downloads** the package `VENDOR/PACKAGE` (this skeleton) into `target-dir/` — like a template
   copy, but without any git history.
2. **Installs its dependencies** (runs `composer install`).
3. **Runs the `post-create-project-cmd` script** declared in the skeleton's `composer.json`. Here
   that is `scripts/rename-namespace.php`, which renames `Skeleton` to the plugin name you enter and
   then deletes itself.

For step 1 to work, Composer has to be able to *find* `appleple/acms-plugin-skeleton`. That happens
once this skeleton is published to **Packagist** (and the same is needed for its dependency
`appleple/acms-testing-framework`). So:

- **After publishing:** `composer create-project appleple/acms-plugin-skeleton my-plugin` just works.
- **From GitHub directly (no Packagist):** you can also point Composer at the repo, but the
  dependency still has to be resolvable, so this only helps once `acms-testing-framework` is on
  Packagist:
  ```bash
  composer create-project appleple/acms-plugin-skeleton my-plugin \
    --repository '{"type":"vcs","url":"https://github.com/appleple/acms-plugin-skeleton"}'
  ```

### Before publishing (git clone)

Until the packages are published, clone and rename manually:

```bash
git clone https://github.com/appleple/acms-plugin-skeleton my-plugin
cd my-plugin && rm -rf .git
php scripts/rename-namespace.php MyAwesomePlugin
```

## Layout

Production code lives in **`src/`**, tests in **`tests/`**, and config files at the repository root.

```
.
├── src/                         # plugin code (deployed as the plugin — see below)
│   ├── ServiceProvider.php
│   ├── GET/Sample.php
│   ├── POST/Sample.php
│   ├── Modules/Get/V2/Sample.php
│   ├── Hook.php
│   ├── Corrector.php
│   ├── Validator.php
│   ├── Services/SampleService.php
│   └── template/…
├── tests/                       # Acms\Plugins\Skeleton\Tests\ → tests/
├── composer.json                # Acms\Plugins\Skeleton\ → src/
├── phpunit.xml.dist
├── docker-compose.yml
└── .github/workflows/test.yml
```

`src/` **is the plugin root**: when a-blog cms runs, `extension/plugins/{Name}/` points at this
repository's `src/` (a symlink or bind-mount — `docker-compose.yml` does exactly this). The core
autoloader maps `Acms\Plugins\ → extension/plugins/`, so `Acms\Plugins\Skeleton\ServiceProvider`
resolves to `src/ServiceProvider.php`. That is why classes under `src/` carry no extra `src`
namespace segment (e.g. `src/Services/SampleService.php` is `Acms\Plugins\Skeleton\Services\SampleService`).

The `ServiceProvider` must be resolvable by the core autoloader alone: when a plugin is activated,
a-blog cms instantiates `Acms\Plugins\{Name}\ServiceProvider` before the plugin's own autoloader is
available. It sits at the top of `src/` for that reason.

## Testing

Keep handlers thin and move logic into services, then test the services.

| Target | Test? | Base class |
|---|---|---|
| `Services/*` pure logic | ✅ Unit | `Acms\Testing\TestCase` |
| DB-backed logic | ✅ Integration | `Acms\Testing\DatabaseTestCase` |
| `GET/*` / `POST/*` / V2 modules | ❌ | covered indirectly via services |

Test data is created with `Acms\Testing\Seeder\*` (Blog / Category / User / Entry / …). Integration
tests run inside a transaction that is rolled back automatically.

### Running tests against your own core

Set `ACMS_ROOT` in `phpunit.xml.dist` to your a-blog cms root (inside Docker, the container path).
Provide the test database connection via the core's `.env.testing`
(`ACMS_DB_HOST` / `ACMS_DB_NAME` / `ACMS_DB_USER` / `ACMS_DB_PASS`).

```bash
composer install
vendor/bin/acms-create-database   # create the test DB tables (first run only)
vendor/bin/phpunit
```

## Quality checks

Composer scripts wrap the tooling:

```bash
composer lint      # PHP_CodeSniffer (PSR-12 + PHPCompatibility) — phpcs.xml
composer analyse   # PHPStan (level 5) — phpstan.neon.dist
composer test      # PHPUnit
composer check     # all three
composer format    # phpcbf (auto-fix coding standard)
```

PHPStan resolves a-blog cms core symbols via the extension shipped with
`appleple/acms-testing-framework`; set `scanDirectories` in `phpstan.neon.dist` to your core path
(the default matches the bundled docker-compose). For a local override, create `phpstan.neon`
(git-ignored).

## Continuous integration

`.github/workflows/test.yml` is a **sample** self-contained workflow: it boots a-blog cms + MySQL
with `docker-compose.yml` and runs **coding standards (phpcs), static analysis (phpstan) and tests
(phpunit)** in the container. Adjust it (image tag, license secret, PHP/DB versions) for your host,
or replace it with Bitbucket Pipelines / CircleCI if you prefer.

## Renaming

`composer create-project` runs `scripts/rename-namespace.php`, which replaces `Skeleton` /
`appleple/acms-plugin-skeleton` with your plugin name and then removes itself. To run it manually:

```bash
php scripts/rename-namespace.php MyAwesomePlugin
```
