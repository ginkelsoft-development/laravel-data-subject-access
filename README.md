# Ginkelsoft Laravel Data Subject Access

[![Tests](https://github.com/ginkelsoft-development/laravel-data-subject-access/actions/workflows/tests.yml/badge.svg?branch=development)](https://github.com/ginkelsoft-development/laravel-data-subject-access/actions/workflows/tests.yml)
[![License](https://img.shields.io/badge/license-MIT-green.svg?style=flat-square)](LICENSE)
[![Laravel](https://img.shields.io/badge/Laravel-10--13-brightgreen?style=flat-square&logo=laravel)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.2%20--%208.5-blue?style=flat-square&logo=php)](https://php.net)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%20max-brightgreen?style=flat-square)](phpstan.neon.dist)

## Overview

Implements **GDPR art. 15 (right of access)** — and, since the output is
structured, doubles as art. 20 (data portability) — for a Laravel
application. Given a subject identifier, the package collects every record
that any registered Eloquent model holds about that subject and renders it
in JSON or Markdown. The action is strictly **read-only**: nothing is
modified, deleted, or anonymized.

Every access is itself a verwerking, so each export writes one row per
matched model to a dedicated, append-only `subject_access_log` hash chain
built on the shared `HashChain` from
`ginkelsoft/laravel-compliance-core`. The log stores only the irreversible
subject hash, the source model class, a record count and the format — never
the exported data, never the subject identifier.

This is the **subject access** member of the GinkelSoft compliance family.

## The family

| Package | GDPR Article(s) | Role |
| ------- | --------------- | ---- |
| [`laravel-compliance-core`](https://github.com/ginkelsoft-development/laravel-compliance-core) | art. 5(2) | Shared primitives |
| [`laravel-data-retention`](https://github.com/ginkelsoft-development/laravel-data-retention) | art. 5(1)(e) | Storage limitation |
| [`laravel-data-right-to-be-forgotten`](https://github.com/ginkelsoft-development/laravel-data-right-to-be-forgotten) | art. 17 | Subject-driven erasure |
| **`laravel-data-subject-access`** | **art. 15 + 20** | **Subject access — this package** |
| [`laravel-data-consent`](https://github.com/ginkelsoft-development/laravel-data-consent) | art. 6(1)(a) + 7 | Consent registry |
| [`laravel-data-breach-registry`](https://github.com/ginkelsoft-development/laravel-data-breach-registry) | art. 33 + 34 | Breach registry |
| [`laravel-compliance-hub`](https://github.com/ginkelsoft-development/laravel-compliance-hub) | art. 5(2) | Umbrella |

## How it works

### 1. Declare which fields are exportable

Mirror of the other family patterns: an attribute for the subject column, a
property for the explicit list of fields. Models implement
`Ginkelsoft\DataSubjectAccess\Contracts\Exportable` and use the matching
trait.

**The field list is opt-in per field**: auto-including every column is
unsafe (internal flags, technical foreign keys, hashed values) so this
package refuses to do it.

```php
use Ginkelsoft\DataSubjectAccess\Attributes\Exportable;
use Ginkelsoft\DataSubjectAccess\Concerns\Exportable as ExportableTrait;
use Ginkelsoft\DataSubjectAccess\Contracts\Exportable as ExportableContract;

#[Exportable(column: 'id')]
class User extends Model implements ExportableContract
{
    use ExportableTrait;

    protected array $exportable = [
        'fields' => [
            'id'    => 'Subject identifier',
            'email' => 'E-mailadres',
        ],
    ];
}

class Profile extends Model implements ExportableContract
{
    use ExportableTrait;

    protected array $exportable = [
        'column' => 'user_id',
        'fields' => [
            'first_name'    => 'Voornaam',
            'last_name'     => 'Achternaam',
            'email'         => ['label' => 'E-mailadres'],
            'logged_in_at'  => [
                'label'     => 'Aangemeld op',
                'transform' => fn ($v) => $v?->format('Y-m-d H:i:s'),
            ],
            // internal_note is intentionally not listed: it stays out of the export.
        ],
    ];
}
```

### 2. Combine with right-to-be-forgotten (optional)

`Exportable` and `Forgettable` (`laravel-data-right-to-be-forgotten`) both
build their `forSubjectQuery()` from the shared `HasSubjectQuery` base trait
in `ginkelsoft/laravel-compliance-core`. That trait resolves the subject
column through the model's own `ResolvesSubjectColumn` implementation, so a
model can use both traits directly — no `insteadof` conflict resolution
needed:

```php
use Ginkelsoft\DataRightToBeForgotten\Attributes\Forgettable as ForgettablePolicy;
use Ginkelsoft\DataRightToBeForgotten\Concerns\Forgettable;
use Ginkelsoft\DataRightToBeForgotten\Contracts\Forgettable as ForgettableContract;
use Ginkelsoft\DataSubjectAccess\Attributes\Exportable as ExportablePolicy;
use Ginkelsoft\DataSubjectAccess\Concerns\Exportable;
use Ginkelsoft\DataSubjectAccess\Contracts\Exportable as ExportableContract;

#[ExportablePolicy(column: 'id')]
#[ForgettablePolicy(column: 'id', action: 'delete')]
class User extends Model implements ExportableContract, ForgettableContract
{
    use Exportable, Forgettable;

    protected array $exportable = [
        'fields' => [
            'id'    => 'Subject identifier',
            'email' => 'E-mailadres',
        ],
    ];
}
```

Each package keeps its own config: `Exportable` reads `ExportableConfig` —
set above via the `#[ExportablePolicy]` attribute (subject column) plus the
`$exportable` property (the exported field list). `Forgettable` reads
`ForgettableConfig` — set here via the `#[ForgettablePolicy]` attribute
(`column` and `action`); a `$forgettable` property is also available when a
model needs per-field anonymize strategies instead of a hard delete, see the
[`laravel-data-right-to-be-forgotten` README](https://github.com/ginkelsoft-development/laravel-data-right-to-be-forgotten#how-it-works).
Both configs point at the same `id` column here, so `forSubjectQuery` — built
once, in the shared `HasSubjectQuery` trait — just works for both. When the
columns differ (e.g. a polymorphic model like `ForgetTicket`), override
`forSubjectQuery` on the model to combine both — see the Gotchas section.

### 3. Register the models

```php
// config/subject-access.php
return [
    'models' => [
        \App\Models\User::class,
        \App\Models\Profile::class,
    ],
    'include_soft_deleted' => true,
];
```

### 4. Run the export

```bash
php artisan retention:export 01HXYZ
php artisan retention:export 01HXYZ --format=markdown
php artisan retention:export 01HXYZ --format=json --output=storage/exports/01HXYZ.json
```

The command name keeps the `retention:` prefix for BC with the v1.x
monolithic package. Without `--output` the export is written to STDOUT, so
it can be piped or captured. With `--output` it lands in the given file
(missing intermediate directories are created). Two formats ship by
default; the `Ginkelsoft\DataSubjectAccess\Contracts\Exporter` interface
lets you add more (HTML, CSV, PDF) without touching the rest of the
package.

### 5. Verify the access chain

```php
use Ginkelsoft\ComplianceCore\Config\LogSecret;
use Ginkelsoft\ComplianceCore\Support\HashChain;
use Illuminate\Support\Facades\DB;

$entries = DB::table('subject_access_log')->orderBy('id')->get()
    ->map(fn ($row) => (array) $row)->all();

$intact = HashChain::verify($entries, LogSecret::value());
```

Or run `php artisan compliance:verify` from the
[hub](https://github.com/ginkelsoft-development/laravel-compliance-hub) to
verify every chain in the family in one shot.

## What the log stores

| Column | Meaning |
| ------ | ------- |
| `subject_hash` | SHA-256 of subject id + secret — irreversible |
| `model_type` | FQCN of the matched model |
| `record_count` | Number of records found for this model |
| `format` | The exporter format used (`json`, `markdown`, ...) |
| `performed_at` | UTC timestamp |
| `previous_hash` / `hash` | Hash chain bookkeeping |

No subject identifier, no field values.

## Compliance notes

* **GDPR art. 15** — Right of access. Produces a structured snapshot of
  every Exportable record per subject.
* **GDPR art. 20** — Data portability. The JSON format is machine-readable
  and reusable by the subject.
* **GDPR art. 5(2)** — Accountability. The `subject_access_log` hash chain
  proves the access happened, when, by which format, and over which models.

This package is **not legal advice**. Identity verification (is this
requester actually the subject?) is your application's responsibility.

## Installation

```bash
composer require ginkelsoft/laravel-data-subject-access
php artisan vendor:publish --tag=compliance-config
php artisan vendor:publish --tag=subject-access-config
php artisan vendor:publish --tag=subject-access-migrations
php artisan migrate
```

Then add a secret to `.env` (shared with the rest of the family):

```env
COMPLIANCE_LOG_SECRET="$(openssl rand -base64 32)"
```

## Gotchas

- **Identity verification is your problem.** This package does not check
  whether the requester is actually the subject. Running `retention:export`
  against an unverified identifier is a data breach waiting to happen — do
  a verified email round-trip, an authenticated session, or a manual KYC
  step before invoking the command.
- **The export is a snapshot.** Records created or modified after the
  export are obviously not in it. If the subject asks for a fresh export
  tomorrow, run it again — accountability comes from the per-call log row.
- **Combining with right-to-be-forgotten, different columns.** When a model
  uses both `Exportable` and `Forgettable` and the two policies point at
  *different* subject columns (e.g. a polymorphic model like `ForgetTicket`
  linked via `subject_id` for one policy and `owner_id` for the other),
  override `forSubjectQuery` on the model to `OR` both columns together. The
  common case — same column for both policies — needs no override at all;
  see [Combine with right-to-be-forgotten](#2-combine-with-right-to-be-forgotten-optional)
  above.
- **PDF is intentionally not built-in.** Adding a PDF generator would pull
  in a heavy dependency for what is essentially an Exporter contract that
  you can implement in a project-specific way (Dompdf, mPDF, Browsershot).
  JSON and Markdown cover the common cases.
- **Migrated from v1.x?** In the monolithic `laravel-data-retention` v1.x
  package, subject-access logged into `retention_log` with a sentinel
  `subject_access` field. This package writes to its own
  `subject_access_log` table instead, which means existing
  `retention_log` rows from before the upgrade stay verifiable in place
  (the retention package still owns that chain) and new access actions
  start a fresh chain in the new table. No data migration needed; see
  [UPGRADE.md](https://github.com/ginkelsoft-development/laravel-compliance-core/blob/development/UPGRADE.md).

## Testing

```bash
composer install
vendor/bin/pest
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/pint --test
```

## Reporting bugs

Found a bug or unexpected behaviour? We want to hear about it.

**Preferred — open a GitHub issue:**
[https://github.com/ginkelsoft-development/laravel-data-subject-access/issues/new](https://github.com/ginkelsoft-development/laravel-data-subject-access/issues/new)

When opening an issue, please include:

1. **Versions** — PHP, Laravel, and the package version
   (`composer show ginkelsoft/laravel-data-subject-access`).
2. **What you did** — the artisan command, code snippet, or steps that
   triggered the bug.
3. **What you expected** vs **what actually happened** — include full
   error output or a stack trace if there is one.
4. **A minimal reproduction** if you can — a failing test or a small
   code sample beats a long description.

**Security-sensitive findings** (anything that could expose personal
data, break a hash-chain, or bypass an audit log) — please **do not**
open a public issue. E-mail **info@ginkelsoft.com** directly with
"SECURITY" in the subject line and we will respond privately.

**Not on GitHub?** You can also e-mail **info@ginkelsoft.com** with the
same information.

## Contact

For commercial support, integration questions, or anything that doesn't
fit a GitHub issue: **info@ginkelsoft.com** —
[https://ginkelsoft.com](https://ginkelsoft.com).

## License

MIT License — see [LICENSE](LICENSE).
(c) 2026 Ginkelsoft
