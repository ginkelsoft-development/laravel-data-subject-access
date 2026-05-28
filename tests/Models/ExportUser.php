<?php

declare(strict_types=1);

namespace Ginkelsoft\DataSubjectAccess\Tests\Models;

use Ginkelsoft\DataSubjectAccess\Attributes\Exportable;
use Ginkelsoft\DataSubjectAccess\Concerns\Exportable as ExportableTrait;
use Ginkelsoft\DataSubjectAccess\Contracts\Exportable as ExportableContract;
use Illuminate\Database\Eloquent\Model;

/**
 * Test model representing the subject themselves. Exports the
 * subject's id and email under explicit labels.
 *
 * In the larger compliance family this same shape of model is
 * typically also Forgettable; the trait-conflict resolution that
 * makes both work together lives in the README's Gotchas section.
 */
#[Exportable(column: 'id')]
class ExportUser extends Model implements ExportableContract
{
    use ExportableTrait;

    /** @var string */
    protected $table = 'export_users';

    /** @var list<string> */
    protected $fillable = ['id', 'email'];

    /** @var bool */
    public $incrementing = false;

    /** @var string */
    protected $keyType = 'string';

    /** @var array<string, mixed> */
    protected $exportable = [
        'fields' => [
            'id' => 'Subject identifier',
            'email' => 'E-mailadres',
        ],
    ];
}
