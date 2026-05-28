<?php

declare(strict_types=1);

namespace Ginkelsoft\DataSubjectAccess\Tests\Models;

use Ginkelsoft\DataSubjectAccess\Concerns\Exportable;
use Ginkelsoft\DataSubjectAccess\Contracts\Exportable as ExportableContract;
use Illuminate\Database\Eloquent\Model;

/**
 * Exportable test model that exercises an explicit, opt-in field list:
 * `internal_note` is deliberately not listed and therefore must not
 * appear in the export.
 *
 * Also exercises the array-form of a field spec, with an inline
 * `label` key.
 */
class ExportProfile extends Model implements ExportableContract
{
    use Exportable;

    /** @var string */
    protected $table = 'export_profiles';

    /** @var list<string> */
    protected $fillable = ['user_id', 'first_name', 'last_name', 'email', 'internal_note'];

    /** @var array<string, mixed> */
    protected $exportable = [
        'column' => 'user_id',
        'fields' => [
            'first_name' => 'Voornaam',
            'last_name' => 'Achternaam',
            'email' => ['label' => 'E-mailadres'],
            // 'internal_note' is intentionally absent: internal field, not exported.
        ],
    ];
}
