<?php

declare(strict_types=1);

namespace Ginkelsoft\DataSubjectAccess\Tests\Concerns;

use Ginkelsoft\ComplianceCore\Concerns\HasSubjectQuery;

/**
 * Stand-in for `Ginkelsoft\DataRightToBeForgotten\Concerns\Forgettable`
 * (a separate package, so it cannot be depended on from here — it has
 * its own, identical follow-up to compose `HasSubjectQuery`).
 *
 * Deliberately declares its own `subjectColumn()` too, the way the real
 * `Forgettable` trait is expected to (reading `ForgettableConfig`
 * instead of `ExportableConfig`), so a model combining this trait with
 * `Exportable` faces a genuine two-trait method collision on
 * `subjectColumn()` — not just on `forSubjectQuery()`. The combined
 * fixture proves that collision is resolved with one plain method on
 * the model, no `insteadof` required.
 */
trait FakeForgettable
{
    use HasSubjectQuery;

    public static function subjectColumn(): string
    {
        return 'wrong_column_never_used_because_the_model_overrides_this';
    }
}
