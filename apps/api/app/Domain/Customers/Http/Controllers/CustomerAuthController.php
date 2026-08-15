<?php

namespace App\Domain\Customers\Http\Controllers;

use App\Domain\Customers\Application\AuthenticateCustomer;
use App\Domain\Customers\Application\LogoutCustomer;
use App\Domain\Customers\Application\RefreshCustomerToken;
use App\Domain\Customers\Application\RegisterCustomer;
use App\Domain\Customers\Application\RequestEmailVerification;
use App\Domain\Customers\Application\RequestPasswordReset;
use App\Domain\Customers\Application\ResetPassword;
use App\Domain\Customers\Application\VerifyCustomerEmail;
use App\Domain\Customers\Http\Requests\LoginCustomerRequest;
use App\Domain\Customers\Http\Requests\RefreshCustomerTokenRequest;
use App\Domain\Customers\Http\Requests\RegisterCustomerRequest;
use App\Domain\Customers\Http\Requests\RequestPasswordResetRequest;
use App\Domain\Customers\Http\Requests\ResetPasswordRequest;
use App\Domain\Customers\Http\Requests\VerifyCustomerEmailRequest;
use App\Domain\Customers\Http\Resources\CustomerResource;
use App\Domain\Customers\Mail\CustomerVerificationMail;
use App\Domain\Customers\Support\CurrentCustomerContext;
use App\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;

final class CustomerAuthController extends Controller
{
    public function register(RegisterCustomerRequest $request, RegisterCustomer $action): JsonResponse
    {
        $result = $action->handle($request->validated(), $request->ip(), $request->userAgent());

        return (new CustomerResource($result['customer']))
            ->additional([
                'access_token' => $result['access_token'],
                'refresh_token' => $result['refresh_token'],
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function login(LoginCustomerRequest $request, AuthenticateCustomer $action): JsonResponse
    {
        $result = $action->handle(
            $request->validated('email'),
            $request->validated('password'),
            $request->ip(),
            $request->userAgent(),
        );

        return (new CustomerResource($result['customer']))
            ->additional([
                'access_token' => $result['access_token'],
                'refresh_token' => $result['refresh_token'],
            ])
            ->response();
    }

    public function refresh(RefreshCustomerTokenRequest $request, RefreshCustomerToken $action): JsonResponse
    {
        $result = $action->handle($request->validated('refresh_token'));

        return response()->json(['data' => $result]);
    }

    public function logout(LogoutCustomer $action, CurrentCustomerContext $currentCustomerContext): JsonResponse
    {
        $action->handle($currentCustomerContext->session());

        return response()->json(null, 204);
    }

    public function forgotPassword(RequestPasswordResetRequest $request, RequestPasswordReset $action): JsonResponse
    {
        $action->handle($request->validated('email'));

        return response()->json(['message' => 'If that email is registered, a reset link has been sent.']);
    }

    public function resetPassword(ResetPasswordRequest $request, ResetPassword $action): JsonResponse
    {
        $action->handle($request->validated('token'), $request->validated('password'));

        return response()->json(['message' => 'Password reset. Please log in again.']);
    }

    public function verifyEmail(VerifyCustomerEmailRequest $request, VerifyCustomerEmail $action): JsonResponse
    {
        $customer = $action->handle($request->validated('token'));

        return (new CustomerResource($customer))->response();
    }

    public function resendVerification(
        RequestEmailVerification $requestEmailVerification,
        CurrentCustomerContext $currentCustomerContext,
        TenantContext $tenantContext,
    ): JsonResponse {
        $customer = $currentCustomerContext->customer();

        if ($customer->isVerified()) {
            return response()->json(['message' => 'This email is already verified.']);
        }

        $token = $requestEmailVerification->handle($customer);

        Mail::to($customer->email)->queue(new CustomerVerificationMail($token, $tenantContext->store()->name));

        return response()->json(['message' => 'Verification email sent.']);
    }
}
