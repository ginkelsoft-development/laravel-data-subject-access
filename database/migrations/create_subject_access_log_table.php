<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates `subject_access_log`, the append-only, hash-chained audit
 * trail for GDPR art. 15 subject-access exports.
 *
 * Privacy: this table stores no personal data. Only the irreversible
 * subject hash, the source model class, and a record count per model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subject_access_log', function (Blueprint $table): void {
            $table->id();
            $table->char('subject_hash', 64);
            $table->string('model_type');
            $table->unsignedInteger('record_count');
            $table->string('format', 32);
            $table->dateTime('performed_at');
            $table->char('previous_hash', 64);
            $table->char('hash', 64)->unique();
            $table->timestamps();

            $table->index(['subject_hash', 'performed_at']);
            $table->index('performed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_access_log');
    }
};
