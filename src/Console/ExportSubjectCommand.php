<?php

declare(strict_types=1);

namespace Ginkelsoft\DataSubjectAccess\Console;

use Ginkelsoft\DataSubjectAccess\Actions\CollectSubjectData;
use Ginkelsoft\DataSubjectAccess\Contracts\Exporter;
use Ginkelsoft\DataSubjectAccess\Exporters\JsonExporter;
use Ginkelsoft\DataSubjectAccess\Exporters\MarkdownExporter;
use Illuminate\Console\Command;

/**
 * Class ExportSubjectCommand
 *
 * Builds a GDPR art. 15 ("subject access") export for one subject
 * across every model registered in `subject-access.models`.
 *
 *   php artisan retention:export 01HXYZ
 *   php artisan retention:export 01HXYZ --format=markdown
 *   php artisan retention:export 01HXYZ --format=json --output=storage/exports/01HXYZ.json
 *
 * Without `--output` the rendered export is written to STDOUT, so it
 * can be piped or captured. With `--output` the export is written to
 * the given path; missing intermediate directories are created.
 *
 * The action is read-only — no model rows are modified. The fact that
 * the access happened is recorded in `subject_access_log` (one row per
 * model the subject had data in), an independent hash-chained trail
 * built on the same `Ginkelsoft\ComplianceCore\Support\HashChain` as
 * the rest of the compliance family.
 */
class ExportSubjectCommand extends Command
{
    /** @var string */
    protected $signature = 'retention:export
        {subject : Subject identifier (e.g. user ID, ULID, email)}
        {--format=json : Output format: json or markdown}
        {--output= : File path to write the export to; without this the export goes to STDOUT}';

    /** @var string */
    protected $description = 'Export every Exportable record belonging to a subject (GDPR art. 15).';

    public function handle(CollectSubjectData $collector): int
    {
        $subjectArg = $this->argument('subject');
        $subject = is_string($subjectArg) ? $subjectArg : '';

        if ($subject === '') {
            $this->error('Subject identifier must not be empty.');

            return self::FAILURE;
        }

        $formatOption = $this->option('format');
        $format = is_string($formatOption) ? strtolower($formatOption) : 'json';

        $exporter = $this->resolveExporter($format);

        if ($exporter === null) {
            $this->error("Unsupported format '{$format}'. Available formats: json, markdown.");

            return self::FAILURE;
        }

        $dataset = $collector->collect($subject, logAccess: true, format: $format);
        $rendered = $exporter->render($dataset);

        $outputOption = $this->option('output');
        $outputPath = is_string($outputOption) && $outputOption !== '' ? $outputOption : null;

        if ($outputPath !== null) {
            $this->writeToFile($outputPath, $rendered);
            $this->info(sprintf(
                'Exported %d record(s) across %d model(s) for subject %s to %s.',
                $dataset->totalRecords,
                count($dataset->perModel),
                $subject,
                $outputPath,
            ));
        } else {
            $this->line($rendered);
        }

        return self::SUCCESS;
    }

    private function resolveExporter(string $format): ?Exporter
    {
        return match ($format) {
            'json' => new JsonExporter,
            'markdown', 'md' => new MarkdownExporter,
            default => null,
        };
    }

    private function writeToFile(string $path, string $contents): void
    {
        $directory = dirname($path);

        if ($directory !== '' && $directory !== '.' && ! is_dir($directory)) {
            if (! mkdir($directory, 0o755, true) && ! is_dir($directory)) {
                throw new \RuntimeException("Could not create output directory: {$directory}");
            }
        }

        if (file_put_contents($path, $contents) === false) {
            throw new \RuntimeException("Could not write export to: {$path}");
        }
    }
}
