<?php

namespace App\Domain\GraphQL\Types;

use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerAddress;
use App\Domain\GraphQL\Support\TypeRegistry;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

/**
 * Mirrors CustomerAccountController/CustomerAddressController. Never
 * exposes `password_hash` or any token/session internals — only what
 * REST's own CustomerResource-equivalent already returns.
 */
final class CustomerTypes
{
    public static function customerAddress(): ObjectType
    {
        return new ObjectType([
            'name' => 'CustomerAddress',
            'fields' => fn () => [
                'id' => Type::nonNull(Type::id()),
                'firstName' => Type::string(),
                'lastName' => Type::string(),
                'phone' => Type::string(),
                'countryCode' => Type::string(),
                'region' => Type::string(),
                'city' => Type::string(),
                'postalCode' => Type::string(),
                'addressLine1' => Type::string(),
                'addressLine2' => Type::string(),
                'isDefaultBilling' => Type::nonNull(Type::boolean()),
                'isDefaultShipping' => Type::nonNull(Type::boolean()),
            ],
            'resolveField' => fn (CustomerAddress $address, array $args, mixed $context, ResolveInfo $info) => match ($info->fieldName) {
                'firstName' => $address->first_name,
                'lastName' => $address->last_name,
                'countryCode' => $address->country_code,
                'postalCode' => $address->postal_code,
                'addressLine1' => $address->address_line1,
                'addressLine2' => $address->address_line2,
                'isDefaultBilling' => $address->is_default_billing,
                'isDefaultShipping' => $address->is_default_shipping,
                default => $address->{$info->fieldName},
            },
        ]);
    }

    public static function customer(TypeRegistry $types): ObjectType
    {
        return new ObjectType([
            'name' => 'Customer',
            'fields' => fn () => [
                'id' => Type::nonNull(Type::id()),
                'email' => Type::string(),
                'firstName' => Type::string(),
                'lastName' => Type::string(),
                'phone' => Type::string(),
                'addresses' => Type::listOf($types->get('CustomerAddress')),
            ],
            'resolveField' => function (Customer $customer, array $args, mixed $context, ResolveInfo $info) {
                return match ($info->fieldName) {
                    'id', 'email', 'phone' => $customer->{$info->fieldName},
                    'firstName' => $customer->first_name,
                    'lastName' => $customer->last_name,
                    'addresses' => $customer->relationLoaded('addresses') ? $customer->addresses->all() : [],
                    default => null,
                };
            },
        ]);
    }

    public static function authPayload(TypeRegistry $types): ObjectType
    {
        return new ObjectType([
            'name' => 'AuthPayload',
            'fields' => [
                'customer' => $types->get('Customer'),
                'accessToken' => Type::nonNull(Type::string()),
                'refreshToken' => Type::nonNull(Type::string()),
            ],
        ]);
    }

    /**
     * Shared by `updateCheckout`'s shipping_address/billing_address args
     * and Address CRUD (createCustomerAddress/updateCustomerAddress) —
     * both accept the exact same field set (see UpdateCheckoutRequest/
     * customer address requests), so one input type serves both rather
     * than two near-identical ones.
     */
    public static function addressInput(): InputObjectType
    {
        return new InputObjectType([
            'name' => 'AddressInput',
            'fields' => [
                'firstName' => Type::string(),
                'lastName' => Type::string(),
                'phone' => Type::string(),
                'countryCode' => Type::string(),
                'region' => Type::string(),
                'city' => Type::string(),
                'postalCode' => Type::string(),
                'addressLine1' => Type::string(),
                'addressLine2' => Type::string(),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function addressInputToSnakeCase(array $input): array
    {
        $map = [
            'firstName' => 'first_name',
            'lastName' => 'last_name',
            'phone' => 'phone',
            'countryCode' => 'country_code',
            'region' => 'region',
            'city' => 'city',
            'postalCode' => 'postal_code',
            'addressLine1' => 'address_line1',
            'addressLine2' => 'address_line2',
        ];

        $result = [];

        foreach ($map as $camel => $snake) {
            if (array_key_exists($camel, $input)) {
                $result[$snake] = $input[$camel];
            }
        }

        return $result;
    }

    public static function tokenPair(): ObjectType
    {
        return new ObjectType([
            'name' => 'TokenPair',
            'fields' => [
                'accessToken' => Type::nonNull(Type::string()),
                'refreshToken' => Type::nonNull(Type::string()),
            ],
        ]);
    }
}
