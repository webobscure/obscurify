<?php

namespace App\Domain\RussianCommerce\Models;

use App\Domain\RussianCommerce\Enums\RussianPaymentMethod;
use App\Shared\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Which RussianPaymentMethod values a store currently accepts (spec
 * section 17's "Payment Methods" admin page).
 *
 * @property string $id
 * @property string $store_id
 * @property list<string> $enabled_methods
 */
class PaymentMethodSettings extends Model
{
    use BelongsToTenant, HasUlids;

    protected $fillable = ['enabled_methods'];

    protected function casts(): array
    {
        return ['enabled_methods' => 'array'];
    }

    public function isEnabled(RussianPaymentMethod $method): bool
    {
        return in_array($method->value, $this->enabled_methods, true);
    }
}
