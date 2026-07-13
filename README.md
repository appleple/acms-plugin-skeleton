# acms-plugin-skeleton

A starter skeleton for developing an **a-blog cms** plugin. It is based on the `SamplePlugin`
bundled with a-blog cms, lightly modernized, and comes with a ready-to-run **PHPUnit** setup
(`ablogcms/testing-framework`).

## Requirements

- **PHP:** 8.1 – 8.5
- **a-blog cms:** 3.2.27 or later (3.3 is not tested yet)

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
| `scripts/*.php` | Release chores (packaging / versioning) run via Composer scripts. |
| `.github/workflows/` | Sample CI: `test.yml` (checks) and `release.yml` (publish on a `v*` tag). |

## Quick start

```bash
# 1. Generate your plugin from this skeleton (you'll be asked for a name).
composer create-project ablogcms/plugin-skeleton my-awesome-plugin
cd my-awesome-plugin

# 2. Start a-blog cms + MySQL.
cp .env.example .env
docker compose up -d
# → open http://localhost:8080 and finish the setup wizard

# 3. Run the tests (from /workspace, where the whole repo is mounted).
docker compose exec acms bash -lc "cd /workspace && composer install && vendor/bin/phpunit"
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

`src/` **is the plugin root**: at runtime `extension/plugins/{Name}/` points at this repository's
`src/` (a symlink or bind-mount — `docker-compose.yml` does this). The core autoloader maps
`Acms\Plugins\ → extension/plugins/`, so `Acms\Plugins\Skeleton\Services\SampleService` resolves to
`src/Services/SampleService.php` — that is why classes under `src/` carry no extra `src` namespace
segment, and why `ServiceProvider` sits at the top of `src/` (the core instantiates it before the
plugin's own autoloader exists).

## Testing

Keep handlers thin and move logic into services, then test the services.

| Target | Test? | Base class |
|---|---|---|
| `Services/*` pure logic | ✅ Unit | `Acms\TestingFramework\TestCase` |
| DB-backed logic | ✅ Integration | `Acms\TestingFramework\DatabaseTestCase` |
| `GET/*` / `POST/*` / V2 modules | ❌ | covered indirectly via services |

Test data is created with `Acms\TestingFramework\Seeder\*` (Blog / Category / User / Entry / …). Integration
tests run inside a transaction that is rolled back automatically.

The PHPUnit bootstrap comes from the testing framework: `phpunit.xml.dist` points at
`vendor/ablogcms/testing-framework/bootstrap.php`, so there is no `tests/bootstrap.php` to
maintain. If you need custom setup, add a `tests/bootstrap.php` that `require`s that shared entry and
point `bootstrap` in `phpunit.xml.dist` back at it.

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
composer lint      # PHP_CodeSniffer (PSR-12 + PHPCompatibility) — phpcs.xml.dist
composer analyse   # PHPStan (level max) — phpstan.neon.dist
composer test      # PHPUnit
composer check     # all three
composer format    # phpcbf (auto-fix coding standard)
```

PHPStan resolves a-blog cms core symbols via the extension shipped with
`ablogcms/testing-framework`; set `scanDirectories` in `phpstan.neon.dist` to your core path
(the default matches the bundled docker-compose). For a local override, create `phpstan.neon`
(git-ignored).

## Continuous integration

Two sample workflows are included (adjust image tags / PHP versions for your host, or swap in
another CI provider):

- **`test.yml`** — on every push / PR, boots a-blog cms + MySQL with `docker-compose.yml`, runs
  `lint` / `analyse` once, and runs PHPUnit across a matrix of PHP and a-blog cms versions (defined
  in the workflow). Require the aggregate **`test`** job in branch protection — not the individual
  matrix cells — so new versions can be added without touching the ruleset.
- **`release.yml`** — on a `v*` tag, checks the tag matches `$version`, builds the plugin zip and
  publishes a GitHub Release (see [Releasing](#releasing)).

Dependabot (`.github/dependabot.yml`) opens weekly PRs to keep the workflows' GitHub Actions up to
date; `dependabot-auto-merge.yml` auto-merges patch/minor bumps once checks pass (major bumps are
reviewed by hand). Auto-merge needs **"Allow auto-merge"** enabled and a branch protection rule
requiring the Test check on the default branch. Composer is not tracked by Dependabot: with no
committed `composer.lock` it would only surface major bumps, and `ablogcms/testing-framework` is
matched to the a-blog cms version by hand.

## Releasing

The version lives in `$version` in `src/ServiceProvider.php` — keep it in sync with the git tag
(don't add a `version` to `composer.json`). Composer scripts do the rest:

```bash
composer package             # build build/{Name}.zip (git-ignored)
composer version:set 1.2.3   # set the version (or: patch | minor | major to bump it)
composer release:patch       # bump + commit + tag v1.2.3, then push to publish:
git push --follow-tags       # → release.yml builds and publishes the zip
```

The zip's top folder matches the plugin name (from the `autoload.psr-4` key) — what a-blog cms
installs as `extension/plugins/{Name}/`. Plugins that bundle runtime Composer packages can add a
`src/composer.json`; `composer package` vendors it into `src/vendor/` and includes it in the zip.

### Configuration (`extra.acms-plugin-tools`)

Two optional keys in `composer.json` tune packaging without touching `scripts/`:

```jsonc
"extra": {
  "acms-plugin-tools": {
    "extras": ["README.md"],    // root paths bundled alongside src/ (default: README.md, LICENSE, images)
    "versionInZipName": false   // true → build {Name}{version}.zip instead of {Name}.zip
  }
}
```

- **`extras`** — set this when the plugin keeps its docs elsewhere, e.g. `["docs"]`.
- **`versionInZipName`** — when `true`, the zip is named `{Name}{version}.zip` so the version stays
  visible in the filename (handy when the artifact is downloaded and distributed by hand). Works the
  same regardless of host.

Which CI builds and publishes the zip on a `v*` tag is decided by the workflow file you keep, not by
config: `.github/workflows/release.yml` (GitHub Release) or `bitbucket-pipelines.yml` (pipeline
artifact you download from the Bitbucket UI). Delete the one you do not use. Either way the CI builds
the zip, so `build/` is git-ignored and `composer release:*` only bumps the version, commits, and tags.

## Renaming

`composer create-project` runs `scripts/namespace.php`, which replaces `Skeleton` /
`ablogcms/plugin-skeleton` with your plugin name and then removes itself. To run it manually:

```bash
php scripts/namespace.php MyAwesomePlugin
```

## License

This skeleton is released under the MIT License (see [`LICENSE`](LICENSE)).
