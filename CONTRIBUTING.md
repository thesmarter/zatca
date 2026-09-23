# Contributing to thesmarter/zatca

Thank you for considering a contribution. This document describes the workflow
expected for changes to this package.

## Branches

- `develop` is the integration branch. Open feature and fix pull requests
  against `develop`.
- `main` (mirrored by `master`) holds releasable state and is updated by
  maintainers via release PRs from `develop`.
- Name your branch descriptively: `feature/<short-name>`,
  `fix/<short-name>` or `docs/<short-name>`.

## Requirements

- PHP `^8.1` with extensions: `openssl`, `dom`, `xsl`, `json`, `bcmath`,
  `simplexml`.
- Install dependencies with `composer install`.

## Code style (PSR-12)

All PHP code in `src/`, `tests/` and `examples/` must pass PSR-12:

```bash
composer cs        # vendor/bin/phpcs (uses phpcs.xml)
composer cs-fix    # vendor/bin/phpcbf — auto-fix what it can
```

`phpcs.xml` documents any rule adjustments (currently only the
`Generic.Files.LineLength` advisory is excluded: fixtures embed long
base64/XML strings that must not be wrapped).

## Tests are required

- Add or update PHPUnit tests for every behavior change or bug fix.
- Run the suite before pushing:

```bash
composer test
```

- CI runs the suite on PHP 8.1–8.5 plus a `lint` job
  (`composer validate --strict` + `vendor/bin/phpcs` on PHP 8.3).
  Both must be green.

## Pull request checklist

Copy this checklist into your PR description and tick every box:

```markdown
- [ ] PR targets `develop` (not `main`/`master`)
- [ ] Code follows PSR-12 (`composer cs` is green)
- [ ] Tests added/updated for the change (`composer test` is green)
- [ ] No logic changes hidden inside style-only fixes
- [ ] Docs updated (`README.md` / `CHANGELOG.md`) when behavior changes
```

Small, focused PRs are reviewed fastest. If you are unsure about an
approach, open an issue first to discuss it.
