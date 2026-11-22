<?php

declare(strict_types=1);

use App\Enums\DocumentType;
use App\Models\Member;
use Illuminate\Database\QueryException;

it('stores the phone with digits only (RN-11)', function () {
    $member = Member::factory()->create(['phone' => '+57 (300) 123-4567']);

    expect($member->fresh()->phone)->toBe('573001234567');
});

it('rejects a duplicate document type and number (RN-11)', function () {
    Member::factory()->create([
        'document_type' => DocumentType::CC,
        'document_number' => '1234567890',
    ]);

    expect(fn () => Member::factory()->create([
        'document_type' => DocumentType::CC,
        'document_number' => '1234567890',
    ]))->toThrow(QueryException::class);
});

it('allows the same number with a different document type', function () {
    Member::factory()->create([
        'document_type' => DocumentType::CC,
        'document_number' => '1234567890',
    ]);

    $other = Member::factory()->create([
        'document_type' => DocumentType::PA,
        'document_number' => '1234567890',
    ]);

    expect($other->exists)->toBeTrue();
});

it('rejects reusing the document of a soft deleted member (RN-11)', function () {
    $member = Member::factory()->create([
        'document_type' => DocumentType::CC,
        'document_number' => '1234567890',
    ]);
    $member->delete();

    expect(fn () => Member::factory()->create([
        'document_type' => DocumentType::CC,
        'document_number' => '1234567890',
    ]))->toThrow(QueryException::class);
});

it('builds the full name and links the trainer (RN-13)', function () {
    $member = Member::factory()->withTrainer()->create([
        'first_name' => 'Ana',
        'last_name' => 'Gómez',
    ]);

    expect($member->full_name)->toBe('Ana Gómez')
        ->and($member->trainer)->not->toBeNull();
});
