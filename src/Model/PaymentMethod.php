<?php

declare(strict_types=1);

namespace Suqo\Model;

/**
 * One of your account's payment methods, as `byop->paymentMethods()` lists it.
 *
 * `paymentTypeId` (a UUID) is the id `byop->updateCredentials()` and
 * `byop->updateFee()` take. `fee` is a percent as a decimal string: on BYOP your
 * own processing fee (`0.00` when unset), `null` for gateways that report their
 * fee on each payment (Khalti, NPS) and for cash.
 *
 * `isConfigured` is true once credentials have been saved. `isVerified` is the
 * background check on them: `true` accepted, `false` refused, `null` not run
 * yet. `companyId` arrives as a number and is kept as its string spelling.
 */
final class PaymentMethod extends Model
{
    /** @param array<string, mixed> $wire */
    private function __construct(
        public readonly ?string $companyId,
        public readonly ?string $paymentTypeId,
        public readonly ?string $paymentType,
        public readonly ?string $alias,
        public readonly ?string $logo,
        public readonly ?int $order,
        public readonly ?bool $isActive,
        public readonly ?string $fee,
        public readonly ?bool $isConfigured,
        public readonly ?bool $isVerified,
        array $wire,
    ) {
        parent::__construct($wire);
    }

    /** @param array<string, mixed> $wire */
    public static function fromWire(array $wire): self
    {
        $companyId = $wire['company_id'] ?? null;

        return new self(
            is_int($companyId) ? (string) $companyId : Wire::nstr($wire, 'company_id'),
            Wire::nstr($wire, 'payment_type_id'),
            Wire::nstr($wire, 'payment_type'),
            Wire::nstr($wire, 'alias'),
            Wire::nstr($wire, 'logo'),
            Wire::count($wire, 'order'),
            Wire::nbool($wire, 'is_active'),
            Wire::decimal($wire, 'fee'),
            Wire::nbool($wire, 'is_configured'),
            Wire::nbool($wire, 'is_verified'),
            $wire,
        );
    }
}
