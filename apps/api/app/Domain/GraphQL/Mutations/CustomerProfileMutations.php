<?php

namespace App\Domain\GraphQL\Mutations;

use App\Domain\Customers\Application\CreateCustomerAddress;
use App\Domain\Customers\Application\DeleteCustomerAddress;
use App\Domain\Customers\Application\UpdateCustomerAddress;
use App\Domain\Customers\Application\UpdateCustomerProfile;
use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerAddress;
use App\Domain\GraphQL\Exceptions\GraphQLUserError;
use App\Domain\GraphQL\Support\GraphQLContext;
use App\Domain\GraphQL\Support\MutationFieldRegistry;
use App\Domain\GraphQL\Support\TypeRegistry;
use App\Domain\GraphQL\Types\CustomerTypes;
use GraphQL\Type\Definition\Type;
use Illuminate\Validation\ValidationException;

/**
 * `updateProfile`/`createCustomerAddress`/`updateCustomerAddress`/
 * `deleteCustomerAddress` — mirrors CustomerAccountController::update/
 * CustomerAddressController exactly, including the ownership check
 * (`address->customer_id !== $customer->id`) every address mutation
 * replicates from the REST controller.
 */
final class CustomerProfileMutations
{
    public static function register(MutationFieldRegistry $mutations, TypeRegistry $types): void
    {
        $mutations->register('updateProfile', [
            'type' => $types->get('Customer'),
            'args' => [
                'firstName' => Type::string(),
                'lastName' => Type::string(),
                'phone' => Type::string(),
            ],
            'resolve' => function (mixed $root, array $args, GraphQLContext $context) {
                $customer = self::requireCustomer($context);

                $data = array_filter([
                    'first_name' => $args['firstName'] ?? null,
                    'last_name' => $args['lastName'] ?? null,
                    'phone' => $args['phone'] ?? null,
                ], fn ($v) => $v !== null);

                try {
                    return app(UpdateCustomerProfile::class)->handle($customer, $data);
                } catch (ValidationException $e) {
                    throw new GraphQLUserError(collect($e->errors())->flatten()->implode(' '));
                }
            },
        ]);

        $mutations->register('createCustomerAddress', [
            'type' => $types->get('CustomerAddress'),
            'args' => [
                'address' => Type::nonNull($types->get('AddressInput')),
                'isDefaultBilling' => Type::boolean(),
                'isDefaultShipping' => Type::boolean(),
            ],
            'resolve' => function (mixed $root, array $args, GraphQLContext $context) {
                $customer = self::requireCustomer($context);

                $data = CustomerTypes::addressInputToSnakeCase($args['address']);
                $data['is_default_billing'] = $args['isDefaultBilling'] ?? false;
                $data['is_default_shipping'] = $args['isDefaultShipping'] ?? false;

                try {
                    return app(CreateCustomerAddress::class)->handle($customer, $data);
                } catch (ValidationException $e) {
                    throw new GraphQLUserError(collect($e->errors())->flatten()->implode(' '));
                }
            },
        ]);

        $mutations->register('updateCustomerAddress', [
            'type' => $types->get('CustomerAddress'),
            'args' => [
                'addressId' => Type::nonNull(Type::id()),
                'address' => Type::nonNull($types->get('AddressInput')),
                'isDefaultBilling' => Type::boolean(),
                'isDefaultShipping' => Type::boolean(),
            ],
            'resolve' => function (mixed $root, array $args, GraphQLContext $context) {
                $customer = self::requireCustomer($context);
                $address = self::resolveOwnedAddress($customer, $args['addressId']);

                $data = CustomerTypes::addressInputToSnakeCase($args['address']);
                if (isset($args['isDefaultBilling'])) {
                    $data['is_default_billing'] = $args['isDefaultBilling'];
                }
                if (isset($args['isDefaultShipping'])) {
                    $data['is_default_shipping'] = $args['isDefaultShipping'];
                }

                try {
                    return app(UpdateCustomerAddress::class)->handle($customer, $address, $data);
                } catch (ValidationException $e) {
                    throw new GraphQLUserError(collect($e->errors())->flatten()->implode(' '));
                }
            },
        ]);

        $mutations->register('deleteCustomerAddress', [
            'type' => Type::boolean(),
            'args' => ['addressId' => Type::nonNull(Type::id())],
            'resolve' => function (mixed $root, array $args, GraphQLContext $context) {
                $customer = self::requireCustomer($context);
                $address = self::resolveOwnedAddress($customer, $args['addressId']);

                app(DeleteCustomerAddress::class)->handle($customer, $address);

                return true;
            },
        ]);
    }

    private static function requireCustomer(GraphQLContext $context): Customer
    {
        if (! $context->isCustomer()) {
            throw GraphQLUserError::forbidden(__('graphql.must_be_logged_in_as_customer'));
        }

        return $context->requireCustomer();
    }

    private static function resolveOwnedAddress(Customer $customer, string $addressId): CustomerAddress
    {
        $address = CustomerAddress::query()->find($addressId);

        if ($address === null || $address->customer_id !== $customer->id) {
            throw GraphQLUserError::notFound('Address');
        }

        return $address;
    }
}
