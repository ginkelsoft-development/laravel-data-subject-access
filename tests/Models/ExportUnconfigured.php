<?php

declare(strict_types=1);

namespace Ginkelsoft\DataSubjectAccess\Tests\Models;

use Ginkelsoft\DataSubjectAccess\Concerns\Exportable;
use Illuminate\Database\Eloquent\Model;

/**
 * Uses the `Exportable` trait but declares no policy at all: no
 * `#[Exportable]` attribute, no `$exportable` property. Exists to
 * exercise the explicit, tested "no policy declared" behaviour of
 * `subjectColumn()` / `forSubjectQuery()`.
 */
class ExportUnconfigured extends Model
{
    use Exportable;

    /** @var string */
    protected $table = 'export_unconfigureds';

    /** @var list<string> */
    protected $fillable = ['user_id'];
}
