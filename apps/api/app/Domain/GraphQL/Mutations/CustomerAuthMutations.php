<?php

namespace App\Domain\GraphQL\Mutations;

use App\Domain\Customers\Application\AuthenticateCustomer;
use App\Domain\Customers\Application\LogoutCustomer;
use App\Domain\Customers\Application\RefreshCustomerToken;
use App\Domain\Customers\Application\RegisterCustomer;
use App\Domain\Customers\Application\RequestPasswordReset;
use App\Domain\Customers\Application\ResetPassword;
use App\Domain\Customers\Exceptions\InvalidActionTokenException;
use App\Domain\Customers\Exceptions\InvalidCredentialsException;
use App\Domain\GraphQL\Exceptions\GraphQLUserError;
use App\Domain\GraphQL\Support\GraphQLActorType;
use App\Domain\GraphQL\Support\GraphQLContext;
use App\Domain\GraphQL\Support\MutationFieldRegistry;
use App\Domain\GraphQL\Support\TypeRegistry;
use GraphQL\Type\Definition\Type;
use Illuminate\Validation\ValidationException;

/**
 * `registerCustomer`/`loginCustomer`/`refreshCustomerToken`/
 * `logoutCustomer`/`requestPasswordReset`/`resetPassword` — mirrors
 * CustomerAuthController exactly, including RequestPasswordReset's
 * anti-enumeration behavior (always returns true, even for an unknown
 * email).
 */
final class CustomerAuthMutations
{
    public static function register(MutationFieldRegistry $mutations, TypeRegistry $types): void
    {
        $mutations->register('registerCustomer', [
            'type' => $types->get('AuthPayload'),
            'args' => [
                'email' => Type::nonNull(Type::string()),
                'password' => Type::nonNull(Type::string()),
                'firstName' => Type::string(),
                'lastName' => Type::string(),
                'phone' => Type::string(),
            ],
            'resolve' => function (mixed $root, array $args) {
                try {
                    $result = app(RegisterCustomer::class)->handle([
                        'email' => $args['email'],
                        'password' => $args['password'],
                        'first_name' => $args['firstName'] ?? null,
                        'last_name' => $args['lastName'] ?? null,
                        'phone' => $args['phone'] ?? null,
                    ], request()->ip(), request()->userAgent());
                } catch (ValidationException $e) {
                    throw new GraphQLUserError(collect($e->errors())->flatten()->implode(' '));
                }

                return ['customer' => $result['customer'], 'accessToken' => $result['access_token'], 'refreshToken' => $result['refresh_token']];
            },
        ]);

        $mutations->register('loginCustomer', [
            'type' => $types->get('AuthPayload'),
            'args' => [
                'email' => Type::nonNull(Type::string()),
                'password' => Type::nonNull(Type::string()),
            ],
            'resolve' => function (mixed $root, array $args) {
                try {
                    $result = app(AuthenticateCustomer::class)->handle($args['email'], $args['password'], request()->ip(), request()->userAgent());
                } catch (InvalidCredentialsException $e) {
                    throw new GraphQLUserError($e->getMessage());
                }

                return ['customer' => $result['customer'], 'accessToken' => $result['access_token'], 'refreshToken' => $result['refresh_token']];
            },
        ]);

        $mutations->register('refreshCustomerToken', [
            'type' => $types->get('TokenPair'),
            'args' => ['refreshToken' => Type::nonNull(Type::string())],
            'resolve' => function (mixed $root, array $args) {
                try {
                    $result = app(RefreshCustomerToken::class)->handle($args['refreshToken']);
                } catch (InvalidCredentialsException $e) {
                    throw new GraphQLUserError($e->getMessage());
                }

                return ['accessToken' => $result['access_token'], 'refreshToken' => $result['refresh_token']];
            },
        ]);

        $mutations->register('logoutCustomer', [
            'type' => Type::boolean(),
            'resolve' => function (mixed $root, array $args, GraphQLContext $context) {
                if ($context->actor !== GraphQLActorType::Customer || $context->customerSession === null) {
                    throw GraphQLUserError::forbidden('You must be logged in to log out.');
                }

                app(LogoutCustomer::class)->handle($context->customerSession);

                return true;
            },
        ]);

        $mutations->register('requestPasswordReset', [
            'type' => Type::boolean(),
            'args' => ['email' => Type::nonNull(Type::string())],
            'resolve' => function (mixed $root, array $args) {
                app(RequestPasswordReset::class)->handle($args['email']);

                return true;
            },
        ]);

        $mutations->register('resetPassword', [
            'type' => Type::boolean(),
            'args' => [
                'token' => Type::nonNull(Type::string()),
                'newPassword' => Type::nonNull(Type::string()),
            ],
            'resolve' => function (mixed $root, array $args) {
                try {
                    app(ResetPassword::class)->handle($args['token'], $args['newPassword']);
                } catch (InvalidActionTokenException|ValidationException $e) {
                    throw new GraphQLUserError($e->getMessage());
                }

                return true;
            },
        ]);
    }
}
