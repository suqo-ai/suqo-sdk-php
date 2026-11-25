<?php

declare(strict_types=1);

namespace Suqo\Resource;

use Suqo\Cancellation;
use Suqo\Endpoints;
use Suqo\Model\ByopFee;
use Suqo\Model\DetailResponse;
use Suqo\Model\PaymentMethod;
use Suqo\Params\ByopCredentialsParams;
use Suqo\Params\ByopFeeParams;

/**
 * Bring-your-own-payment — openapi's `byop_*` operations.
 *
 * On a BYOP account a payment method collects into your own gateway account.
 * {@see self::paymentMethods()} lists the methods and their `paymentTypeId`,
 * which the two writes take. Switching an account to BYOP is done by SUQO, not
 * through this API: the writes answer 403 until it is.
 */
final class Byop extends AbstractResource
{
    /**
     * GET byop/payment-methods — every payment method on your account, hidden
     * ones included (`isActive` false).
     *
     * Not paginated: the API answers with a bare JSON array.
     *
     * @return list<PaymentMethod>
     *
     * @throws \Suqo\Exception\SuqoError
     */
    public function paymentMethods(?Cancellation $cancellation = null): array
    {
        $response = $this->transport->request(
            'GET',
            Endpoints::BYOP_PAYMENT_METHODS,
            null,
            [],
            $cancellation,
        );

        return array_map(
            static fn (array $record): PaymentMethod => PaymentMethod::fromWire($record),
            self::bareList($response->body),
        );
    }

    /**
     * PATCH byop/credentials — save your gateway credentials for one payment
     * method. The full set replaces what is stored.
     *
     * Credentials are never returned. Each save is checked against the gateway
     * in the background: read `isVerified` on {@see self::paymentMethods()}.
     *
     * @param string $paymentTypeId The `paymentTypeId` (UUID) from {@see self::paymentMethods()}.
     *
     * @throws \Suqo\Exception\SuqoError
     */
    public function updateCredentials(
        string $paymentTypeId,
        ByopCredentialsParams $params,
        ?Cancellation $cancellation = null,
    ): DetailResponse {
        $response = $this->transport->request(
            'PATCH',
            Endpoints::byopCredentials($paymentTypeId),
            $params->toWire(),
            [],
            $cancellation,
        );

        return DetailResponse::fromWire($response->object());
    }

    /**
     * PATCH byop/fees — set the percent your own gateway takes from each
     * payment on this method. Refused for Khalti and NPS, which report their own
     * fee on each payment.
     *
     * @param string $paymentTypeId The `paymentTypeId` (UUID) from {@see self::paymentMethods()}.
     *
     * @throws \Suqo\Exception\SuqoError
     */
    public function updateFee(
        string $paymentTypeId,
        ByopFeeParams $params,
        ?Cancellation $cancellation = null,
    ): ByopFee {
        $response = $this->transport->request(
            'PATCH',
            Endpoints::byopFees($paymentTypeId),
            $params->toWire(),
            [],
            $cancellation,
        );

        return ByopFee::fromWire($response->object());
    }
}
