<?php

declare(strict_types=1);

use Ginkelsoft\DataSubjectAccess\Tests\Models\ExportAndForgetUser;
use Ginkelsoft\DataSubjectAccess\Tests\Models\ExportProfile;
use Ginkelsoft\DataSubjectAccess\Tests\Models\ExportUnconfigured;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::create('export_profiles', function ($table): void {
        $table->id();
        $table->string('user_id', 64)->index();
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('email', 128)->nullable();
        $table->string('internal_note')->nullable();
        $table->timestamps();
    });

    Schema::create('export_unconfigureds', function ($table): void {
        $table->id();
        $table->string('user_id', 64)->index();
        $table->timestamps();
    });

    Schema::create('export_and_forget_users', function ($table): void {
        $table->id();
        $table->string('user_id', 64)->index();
        $table->string('email')->nullable();
        $table->timestamps();
    });
});

it('resolves subjectColumn() from the ExportableConfig policy and builds WHERE column = subject', function (): void {
    ExportProfile::create(['user_id' => 'alice', 'first_name' => 'Alice']);
    ExportProfile::create(['user_id' => 'bob', 'first_name' => 'Bob']);

    expect(ExportProfile::subjectColumn())->toBe('user_id');

    $rows = ExportProfile::forSubjectQuery('alice')->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->first_name)->toBe('Alice');
});

it('throws when subjectColumn()/forSubjectQuery() is called without a declared Exportable policy', function (): void {
    expect(fn () => ExportUnconfigured::subjectColumn())
        ->toThrow(InvalidArgumentException::class, 'no Exportable policy is declared');

    expect(fn () => ExportUnconfigured::forSubjectQuery('anyone'))
        ->toThrow(InvalidArgumentException::class, 'no Exportable policy is declared');
});

it('lets a model combine Exportable with another subject-driven trait without an insteadof', function (): void {
    ExportAndForgetUser::create(['user_id' => 'alice', 'email' => 'alice@example.com']);
    ExportAndForgetUser::create(['user_id' => 'bob', 'email' => 'bob@example.com']);

    // The model's own subjectColumn() wins over both traits' — no insteadof needed.
    expect(ExportAndForgetUser::subjectColumn())->toBe('user_id');

    $rows = ExportAndForgetUser::forSubjectQuery('alice')->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->email)->toBe('alice@example.com');
});
