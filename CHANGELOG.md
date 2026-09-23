# Changelog

All notable changes to `thesmarter/zatca` are documented here. The format is
based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [1.1.0] - 2026-09-23

### Added
- Bilingual documentation: full Arabic `README.ar.md` with language switcher.
- Examples 8 (UBL builder) and 9 (production CSID renewal).
- `CONTRIBUTING.md`, `SECURITY.md`, `.editorconfig`, `.gitattributes`.

### Changed
- Full PSR-12 compliance enforced by `phpcs.xml` + CI `lint` job
  (`composer validate --strict`, code style, PHP 8.1–8.5 matrix).

## [1.0.0] - 2026-09-23

First stable release.

### Added
- Onboarding: CSR builder (secp256k1), compliance CSID, compliance invoice
  checks, production CSID and production CSID renewal (`PATCH production/csids`).
- UBL 2.1 invoice builder (`InvoiceBuilder`): simplified/standard invoices,
  credit and debit notes (388/383/381) with automatic totals and VAT grouping.
- Pipeline: XSLT-based invoice hashing, DOM-based XAdES signing (namespace
  aware, no string surgery), TLV QR generation.
- Submission: reporting (simplified) and clearance (standard) APIs with typed
  `SubmissionResponse`/`ValidationResults` entities.
- Environments: sandbox, simulation and production tiers via `ZatcaEnvironment`.
- Facade: `Smart\Zatca\Zatca` entry point for all services.
- Examples 0-9 covering the full lifecycle; PHPUnit suite.

### Fixed
- `CsrGenerationException` (instead of `TypeError`) when OpenSSL key
  generation fails.
- Namespace rebrand `Zid\Zatca` → `Smart\Zatca`; package `thesmarter/zatca`.

[1.0.0]: https://github.com/thesmarter/zatca/releases/tag/v1.0.0
