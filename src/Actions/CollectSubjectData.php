<?php

declare(strict_types=1);

namespace Ginkelsoft\DataSubjectAccess\Actions;

use Ginkelsoft\ComplianceCore\Config\LogSecret;
use Ginkelsoft\ComplianceCore\Support\HashChain;
use Ginkelsoft\ComplianceCore\Support\SubjectHash;
use Ginkelsoft\DataSubjectAccess\Contracts\Exportable;
use Ginkelsoft\DataSubjectAccess\Models\SubjectAccessLogEntry;
use Ginkelsoft\DataSubjectAccess\Support\ExportableConfig;
use Ginkelsoft\DataSubjectAccess\Support\SubjectExportDataset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Collects all personal data the application holds about one subject
 * across every Exportable model, and returns it as an immutable
 * {@see SubjectExportDataset}.
 *
 * This action is strictly **read-only**: it never modifies, deletes,
 * or anonymizes anything. The exporters consume the dataset and
 * render it in their chosen format (JSON, Markdown, ...).
 *
 * For accountability, the action appends one row per matched model to
 * the dedicated `subject_access_log` chain, recording that the access
 * happened — without the exported content. The subject identifier
 * never lands in the log; only its irreversible {@see SubjectHash}.
 */
final class CollectSubjectData
{
    /**
     * Collect every Exportable record linked to the given subject.
     *
     * Soft-deleted records are included when
     * `subject-access.include_soft_deleted` is true — the access
     * obligation covers data the application still holds even if
     * normally hidden by a global scope.
     *
     * @param  string  $subjectId  Identifier the application uses
     *                             to reference this subject across
     *                             models (typically a primary key
     *                             or ULID).
     * @param  bool  $logAccess  When true, append rows to
     *                           `subject_access_log` so the access is
     *                           itself accountable. Default true.
     * @param  string  $format  Format identifier persisted in the log
     *                          (e.g. 'json', 'markdown'). Has no
     *                          effect on what is returned — that is
     *                          the exporter's job.
     */
    public function collect(string $subjectId, bool $logAccess = true, string $format = 'json'): SubjectExportDataset
    {
        if ($subjectId === '') {
            throw new \InvalidArgumentException('Subject identifier must not be empty.');
        }

        $collectedAt = Carbon::now();
        $perModel = [];
        $totalRecords = 0;

        foreach ($this->resolveModels() as $modelClass) {
            $policy = ExportableConfig::for($modelClass);

            if ($policy === null) {
                continue;
            }

            $query = $modelClass::forSubjectQuery($subjectId);

            if (in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)
                && config('subject-access.include_soft_deleted', true)) {
                /** @var Builder<Model> $query */
                $query = $query->withTrashed(); // @phpstan-ignore-line method.notFound
            }

            $records = $query->get();

            if ($records->isEmpty()) {
                continue;
            }

            $rows = [];
            foreach ($records as $record) {
                $rows[] = $this->extractFields($record, $policy);
            }

            $perModel[$modelClass] = $rows;
            $totalRecords += count($rows);
        }

        $dataset = new SubjectExportDataset(
            subjectId: $subjectId,
            collectedAt: $collectedAt,
            perModel: $perModel,
            totalRecords: $totalRecords,
        );

        if ($logAccess && $perModel !== []) {
            $this->logAccess($subjectId, $perModel, $collectedAt, $format);
        }

        return $dataset;
    }

    /**
     * Extract every configured field from the record, applying the
     * field's transform if present. Returns a `label => value` map.
     *
     * @return array<string, mixed>
     */
    private function extractFields(Model $record, ExportableConfig $policy): array
    {
        $row = [];

        foreach ($policy->fields as $field => $spec) {
            $raw = $record->getAttribute($field);
            $value = $spec['transform'] !== null
                ? ($spec['transform'])($raw, $field, $record)
                : $raw;

            $row[$spec['label']] = $value;
        }

        return $row;
    }

    /**
     * Append one row per matched model to `subject_access_log`,
     * recording that an access export happened. The log holds no field
     * values — only counts and metadata, so privacy by design is preserved.
     *
     * @param  array<class-string, list<array<string, mixed>>>  $perModel
     */
    private function logAccess(string $subjectId, array $perModel, Carbon $performedAt, string $format): void
    {
        $secret = LogSecret::value();
        $subjectHash = SubjectHash::compute($subjectId, $secret);
        $performedAtString = $performedAt->utc()->format('Y-m-d H:i:s');

        DB::transaction(function () use ($perModel, $subjectHash, $performedAtString, $format, $secret): void {
            foreach ($perModel as $modelClass => $rows) {
                $previous = SubjectAccessLogEntry::query()
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                $previousHash = $previous instanceof SubjectAccessLogEntry ? $previous->hash : '';

                $payload = [
                    'subject_hash' => $subjectHash,
                    'model_type' => $modelClass,
                    'record_count' => count($rows),
                    'format' => $format,
                    'performed_at' => $performedAtString,
                ];

                $hash = HashChain::compute($payload, $previousHash, $secret);

                SubjectAccessLogEntry::query()->create($payload + [
                    'previous_hash' => $previousHash,
                    'hash' => $hash,
                ]);
            }
        });
    }

    /**
     * Read the configured exportable model classes, filtering out
     * anything that is not actually a model implementing the contract.
     *
     * @return list<class-string<Model&Exportable>>
     */
    private function resolveModels(): array
    {
        $configured = config('subject-access.models', []);

        if (! is_array($configured)) {
            return [];
        }

        $valid = [];

        foreach ($configured as $class) {
            if (! is_string($class) || ! class_exists($class)) {
                continue;
            }

            if (! is_subclass_of($class, Model::class) || ! is_subclass_of($class, Exportable::class)) {
                continue;
            }

            /** @var class-string<Model&Exportable> $class */
            $valid[] = $class;
        }

        return $valid;
    }
}
