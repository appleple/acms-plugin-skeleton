# Maintaining this skeleton

This file is for maintainers of **the skeleton itself** (`ablogcms/plugin-skeleton`). It is removed
from generated plugins by `post-create-project-cmd`, so plugin authors never see it.

## Two different "releases" — don't confuse them

| | What | How |
|---|---|---|
| **A generated plugin** | A plugin created with `composer create-project` | `composer release:*` + `scripts/*` + `.github/workflows/release.yml` (builds a plugin zip → GitHub Release). See the README's *Releasing* section. |
| **This skeleton** | The template published on Packagist | Just a **git tag** so `composer create-project` can resolve a stable version. No zip, no GitHub Release needed. |

The release scripts (`scripts/*`, `release.yml`) exist **for the generated plugin** — that is the
value the skeleton provides. They are not meant to release the skeleton itself.

## Releasing the skeleton (Packagist)

```bash
git tag vX.Y.Z          # e.g. git tag v1.2.0
git push --follow-tags
```

That is all that is required: Packagist picks up the tag and serves it to `composer create-project`.

- Tagging with the `v` prefix is fine. `release.yml` triggers on `v*`, **but it skips itself on the
  skeleton** (see below), so no bogus `Skeleton.zip` release is published.
- A GitHub Release for the skeleton is optional — create one by hand only if you want a changelog.

## Why tagging the skeleton doesn't publish a zip

`.github/workflows/release.yml` runs on `v*` tags in every repo that has it — including this one.
Its first step checks for `scripts/namespace.php`, the one-time bootstrap that **self-deletes during
`composer create-project`**. It exists only in the skeleton, so:

- **Skeleton repo:** `namespace.php` present → the release job is skipped.
- **Generated plugin:** `namespace.php` gone → the release job runs normally.

If you ever rename or remove that bootstrap, update the guard in `release.yml` accordingly.

## Local development

Tests and static analysis run inside the bundled a-blog cms container (the testing framework and
core are not available on the host):

```bash
cp .env.example .env
docker compose up -d
docker compose exec acms bash -lc "cd /workspace && composer install && composer check"
```

`composer check` runs `lint` (PHP_CodeSniffer), `analyse` (PHPStan) and `test` (PHPUnit) — the same
checks CI runs in `.github/workflows/test.yml`.
