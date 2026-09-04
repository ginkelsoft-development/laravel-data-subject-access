<?php

declare(strict_types=1);

namespace Ginkelsoft\DataSubjectAccess\Concerns;

use Ginkelsoft\ComplianceCore\Concerns\HasSubjectQuery;
use Ginkelsoft\ComplianceCore\Contracts\ResolvesSubjectColumn;
use Ginkelsoft\DataSubjectAccess\Actions\CollectSubjectData;
use Ginkelsoft\DataSubjectAccess\Support\ExportableConfig;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Trait Exportable
 *
 * Marks an Eloquent model as participating in GDPR art. 15 ("subject
 * access") exports. The trait is intentionally lightweight: the
 * decisioning lives in {@see ExportableConfig} and
 * {@see CollectSubjectData}.
 *
 * Models declare their policy via the `#[Exportable]` class attribute
 * (subject column) and a protected `$exportable` array property
 * (fields list, labels, optional transforms).
 *
 * `forSubjectQuery` itself is no longer declared here: it is inherited
 * from {@see HasSubjectQuery} (compliance-core), which builds it as
 * `WHERE {subjectColumn()} = :subject`. This trait only has to satisfy
 * {@see ResolvesSubjectColumn}. Models that also use `Forgettable` (from
 * `laravel-data-right-to-be-forgotten`, once it composes the same base
 * trait) no longer need an `insteadof` — both traits resolve
 * `forSubjectQuery` to the exact same `HasSubjectQuery` source, so PHP
 * never sees a collision.
 *
 * @mixin Model
 */
trait Exportable
{
    use HasSubjectQuery;

    /**
     * Resolve the export policy for this model.
     *
     * @return ExportableConfig|null Null when no policy is declared.
     */
    public function exportablePolicy(): ?ExportableConfig
    {
        return ExportableConfig::for(static::class);
    }

    /**
     * The column {@see HasSubjectQuery} filters on for `forSubjectQuery`.
     *
     * Deliberate, explicit behaviour for a model that uses this trait but
     * never declared an `Exportable` policy (no `#[Exportable]` attribute,
     * no `$exportable` property): this now throws instead of silently
     * matching zero rows. `CollectSubjectData` already skips such models
     * before ever calling `forSubjectQuery`, via its own policy-null
     * check, so in practice this only guards a direct/manual call — and a
     * clear failure beats a silent, misleadingly-empty export for a
     * misconfigured model.
     *
     * @throws InvalidArgumentException When no policy is declared.
     */
    public static function subjectColumn(): string
    {
        $policy = ExportableConfig::for(static::class);

        if ($policy === null) {
            throw new InvalidArgumentException(
                'Cannot build forSubjectQuery() for '.static::class.': no Exportable '
                .'policy is declared. Add the #[Exportable] attribute and/or a protected '
                .'$exportable property, or override forSubjectQuery() directly.'
            );
        }

        return $policy->column;
    }
}
