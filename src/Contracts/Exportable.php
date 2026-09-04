<?php

declare(strict_types=1);

namespace Ginkelsoft\DataSubjectAccess\Contracts;

use Ginkelsoft\ComplianceCore\Contracts\ResolvesSubjectColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Contract every Eloquent model registered under
 * `subject-access.models` must implement.
 *
 * The {@see \Ginkelsoft\DataSubjectAccess\Concerns\Exportable} trait
 * provides a default implementation of both methods: `forSubjectQuery`
 * via compliance-core's `HasSubjectQuery` (`WHERE {policy.column} =
 * :subject`), and `subjectColumn` via {@see ResolvesSubjectColumn}, read
 * from the resolved `ExportableConfig`. Models with a more complex
 * subject mapping can override `forSubjectQuery` directly while still
 * implementing this contract, so the collector can keep its dispatch
 * fully typed.
 *
 * A model that implements both this contract and `Forgettable` (from
 * `laravel-data-right-to-be-forgotten`) only needs one implementation of
 * `forSubjectQuery` — both traits resolve it from the same
 * `HasSubjectQuery` source, so no `insteadof` is required.
 */
interface Exportable extends ResolvesSubjectColumn
{
    /**
     * Build the query that selects every record of this model belonging
     * to the given subject identifier.
     *
     * @return Builder<Model>
     */
    public static function forSubjectQuery(string $subject): Builder;
}
