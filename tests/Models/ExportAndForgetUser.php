<?php

declare(strict_types=1);

namespace Ginkelsoft\DataSubjectAccess\Tests\Models;

use Ginkelsoft\DataSubjectAccess\Attributes\Exportable as ExportableAttribute;
use Ginkelsoft\DataSubjectAccess\Concerns\Exportable as ExportableTrait;
use Ginkelsoft\DataSubjectAccess\Contracts\Exportable as ExportableContract;
use Ginkelsoft\DataSubjectAccess\Tests\Concerns\FakeForgettable;
use Illuminate\Database\Eloquent\Model;

/**
 * Proves a model can combine `Exportable` with another subject-driven
 * trait (standing in for `Forgettable` from
 * `laravel-data-right-to-be-forgotten`, see {@see FakeForgettable})
 * without any `insteadof`:
 *
 *  - `forSubjectQuery` never collides: both traits pull it from the
 *    exact same `HasSubjectQuery` source in compliance-core, so PHP
 *    does not see two different method bodies.
 *  - `subjectColumn` *does* collide (each trait resolves it from its
 *    own config), which this model resolves the plain way — one method
 *    declared directly on the model always wins over both traits, no
 *    `insteadof` syntax needed.
 */
#[ExportableAttribute(column: 'user_id')]
class ExportAndForgetUser extends Model implements ExportableContract
{
    use ExportableTrait, FakeForgettable;

    /** @var string */
    protected $table = 'export_and_forget_users';

    /** @var list<string> */
    protected $fillable = ['user_id', 'email'];

    /** @var array<string, mixed> */
    protected $exportable = [
        'fields' => [
            'email' => 'E-mailadres',
        ],
    ];

    public static function subjectColumn(): string
    {
        return 'user_id';
    }
}
