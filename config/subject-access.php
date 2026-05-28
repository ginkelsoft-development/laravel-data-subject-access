<?php

declare(strict_types=1);

/**
 * -----------------------------------------------------------------------------
 * Ginkelsoft Laravel Data Subject Access - Configuration
 * -----------------------------------------------------------------------------
 *
 * GDPR art. 15 (right of access) and art. 20 (data portability)
 * configuration. List the Eloquent models that hold personal data and
 * may appear in a subject-access export. The signing secret used to
 * hash the audit log lives in `compliance.log_secret` provided by
 * `ginkelsoft/laravel-compliance-core`.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Exportable Models Registry
    |--------------------------------------------------------------------------
    |
    | Models that participate in subject-access exports. Each model
    | must use the Exportable trait, implement the Exportable contract,
    | and declare a `$exportable` property listing the fields to
    | include (explicit opt-in; auto-including all columns is unsafe).
    |
    */
    'models' => [],

    /*
    |--------------------------------------------------------------------------
    | Include Soft-Deleted Records
    |--------------------------------------------------------------------------
    |
    | When a model uses SoftDeletes, this flag controls whether already
    | soft-deleted records are also included in the export. Typically
    | true: the access obligation covers data the application still
    | holds even if normally hidden by a global scope.
    |
    */
    'include_soft_deleted' => true,
];
