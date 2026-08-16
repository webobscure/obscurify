<?php

use App\Domain\RussianCommerce\Enums\LegalEntityType;
use App\Domain\RussianCommerce\Support\InnKppValidator;

beforeEach(function () {
    $this->validator = app(InnKppValidator::class);
});

it('validates a real 10-digit legal entity INN via the ФНС checksum', function () {
    // Sberbank's real, publicly known INN.
    expect($this->validator->isValidInn10('7707083893'))->toBeTrue()
        ->and($this->validator->isValidInn('7707083893', LegalEntityType::LegalEntity))->toBeTrue();
});

it('validates a real 12-digit individual entrepreneur INN via the ФНС checksum', function () {
    expect($this->validator->isValidInn12('500100732259'))->toBeTrue()
        ->and($this->validator->isValidInn('500100732259', LegalEntityType::IndividualEntrepreneur))->toBeTrue()
        ->and($this->validator->isValidInn('500100732259', LegalEntityType::SelfEmployed))->toBeTrue();
});

it('rejects an INN with a wrong check digit', function () {
    expect($this->validator->isValidInn10('1234567890'))->toBeFalse();
});

it('rejects an INN with the wrong digit count for its type', function () {
    expect($this->validator->isValidInn('7707083893', LegalEntityType::IndividualEntrepreneur))->toBeFalse()
        ->and($this->validator->isValidInn('500100732259', LegalEntityType::LegalEntity))->toBeFalse();
});

it('rejects a non-numeric INN', function () {
    expect($this->validator->isValidInn10('770708389X'))->toBeFalse();
});

it('validates a well-formed 9-digit KPP with no checksum', function () {
    expect($this->validator->isValidKpp('770701001'))->toBeTrue()
        ->and($this->validator->isValidKpp('77070100'))->toBeFalse()
        ->and($this->validator->isValidKpp('7707010011'))->toBeFalse();
});

it('validates OGRN/OGRNIP by digit count only', function () {
    expect($this->validator->isValidOgrn('1027700132195'))->toBeTrue()
        ->and($this->validator->isValidOgrn('102770013219'))->toBeFalse()
        ->and($this->validator->isValidOgrnip('304500116000157'))->toBeTrue()
        ->and($this->validator->isValidOgrnip('30450011600015'))->toBeFalse();
});
