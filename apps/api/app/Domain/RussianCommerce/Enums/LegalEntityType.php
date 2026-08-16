<?php

namespace App\Domain\RussianCommerce\Enums;

/**
 * Spec section 1. Which fields are meaningful/required differs by
 * type — a LegalEntity has both INN (10 digits) and KPP; an
 * IndividualEntrepreneur or SelfEmployed person has a 12-digit INN and
 * never a KPP (see InnKppValidator).
 */
enum LegalEntityType: string
{
    case LegalEntity = 'legal_entity';
    case IndividualEntrepreneur = 'individual_entrepreneur';
    case SelfEmployed = 'self_employed';
}
