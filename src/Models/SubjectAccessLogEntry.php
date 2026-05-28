<?php

declare(strict_types=1);

namespace Ginkelsoft\DataSubjectAccess\Models;

use Ginkelsoft\ComplianceCore\Support\HashChain;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Class SubjectAccessLogEntry
 *
 * Append-only audit row that records one subject-access (inzageverzoek)
 * export. One row is written per model that held data about the subject
 * at the time of the export, so the breakdown can be reconstructed
 * later without storing the exported content.
 *
 * Each entry forms part of a SHA-256 hash chain, sharing its primitives
 * (compute / verify, shared `compliance.log_secret`) with every other
 * audit log in the GinkelSoft compliance family via
 * `Ginkelsoft\ComplianceCore\Support\HashChain`.
 *
 * The model deliberately blocks `update()` and `delete()` so the
 * application itself cannot mutate the audit trail through Eloquent.
 * Bypassing the model (e.g. direct DB writes) is still detectable via
 * {@see HashChain::verify()}.
 *
 * Privacy: the log MUST NOT contain personal data. Only the subject
 * hash (one-way SHA-256 of the subject identifier + secret), the source
 * model class, and a record count are stored, never field values.
 *
 * @property int $id
 * @property string $subject_hash
 * @property string $model_type
 * @property int $record_count
 * @property string $format
 * @property Carbon $performed_at
 * @property string $previous_hash
 * @property string $hash
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @method static static create(array<string, mixed> $attributes = [])
 */
class SubjectAccessLogEntry extends Model
{
    /** @var string */
    protected $table = 'subject_access_log';

    /** @var list<string> */
    protected $fillable = [
        'subject_hash',
        'model_type',
        'record_count',
        'format',
        'performed_at',
        'previous_hash',
        'hash',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'performed_at' => 'datetime',
        'record_count' => 'integer',
    ];

    /**
     * Boot the model and block any mutation after creation.
     */
    protected static function booted(): void
    {
        static::updating(function (): bool {
            throw new \RuntimeException(
                'SubjectAccessLogEntry is append-only and cannot be updated. '
                .'The audit trail is tamper-evident; modify the database directly '
                .'only if you understand that doing so invalidates the hash chain.'
            );
        });

        static::deleting(function (): bool {
            throw new \RuntimeException(
                'SubjectAccessLogEntry is append-only and cannot be deleted. '
                .'The audit trail must be preserved for the full statutory retention period.'
            );
        });
    }
}
