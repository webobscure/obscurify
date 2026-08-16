<?php

use App\Domain\RussianCommerce\Support\RussianPhoneNormalizer;

beforeEach(function () {
    $this->normalizer = app(RussianPhoneNormalizer::class);
});

it('normalizes an 8-prefixed number to canonical +7 form', function () {
    expect($this->normalizer->normalize('89261234567'))->toBe('+79261234567');
});

it('normalizes a 7-prefixed number to canonical +7 form', function () {
    expect($this->normalizer->normalize('79261234567'))->toBe('+79261234567');
});

it('normalizes an already-plus-7 number to canonical +7 form', function () {
    expect($this->normalizer->normalize('+79261234567'))->toBe('+79261234567');
});

it('normalizes a bare 10-digit number to canonical +7 form', function () {
    expect($this->normalizer->normalize('9261234567'))->toBe('+79261234567');
});

it('normalizes a number with separators to canonical +7 form', function () {
    expect($this->normalizer->normalize('+7 (926) 123-45-67'))->toBe('+79261234567');
});

it('returns null for a non-Russian or malformed number rather than guessing', function () {
    expect($this->normalizer->normalize('+1 415 555 0100'))->toBeNull()
        ->and($this->normalizer->normalize('123'))->toBeNull()
        ->and($this->normalizer->normalize(''))->toBeNull();
});

it('treats two differently-formatted inputs as the same identity after normalization', function () {
    $a = $this->normalizer->normalize('89261234567');
    $b = $this->normalizer->normalize('+7 (926) 123-45-67');

    expect($a)->toBe($b);
});

it('formats a canonical number for display only, without changing its identity', function () {
    expect($this->normalizer->format('+79261234567'))->toBe('+7 (926) 123-45-67');
});
