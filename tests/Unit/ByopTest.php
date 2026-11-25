<?php

declare(strict_types=1);

namespace Suqo\Tests\Unit;

use Suqo\Exception\PermissionDeniedError;
use Suqo\Params\ByopCredentialsParams;
use Suqo\Params\ByopFeeParams;
use Suqo\Resource\Byop;
use Suqo\SuqoClient;
use Suqo\Tests\Support\MockHttpClient;
use Suqo\Tests\Support\TransportTestCase;

/**
 * BYOP — `paymentMethods`, `updateCredentials`, `updateFee`.
 */
final class ByopTest extends TransportTestCase
{
    private const METHOD = '0b7e4c2a-1f3d-4e5a-9b8c-7d6e5f4a3b2c';

    public function testPaymentMethodsDecodesABareArray(): void
    {
        $client = (new MockHttpClient())->pushJson(200, [self::method()]);

        $methods = (new Byop($this->transport($client)))->paymentMethods();

        self::assertSame([self::METHOD, 'esewa'], [$methods[0]->paymentTypeId, $methods[0]->paymentType]);
    }

    public function testPaymentMethodsHitsItsRoute(): void
    {
        $client = (new MockHttpClient())->pushJson(200, []);

        (new Byop($this->transport($client)))->paymentMethods();

        self::assertStringEndsWith('/api/v1/byop/payment-methods/', $client->lastRequest()->url);
    }

    /** `company_id` arrives as a number; it is kept as its string spelling. */
    public function testANumericCompanyIdReadsAsAString(): void
    {
        $client = (new MockHttpClient())->pushJson(200, [self::method()]);

        self::assertSame('118', (new Byop($this->transport($client)))->paymentMethods()[0]->companyId);
    }

    /** `is_verified` is tri-state: null means the check has not run. */
    public function testAnUnrunVerificationReadsAsNull(): void
    {
        $client = (new MockHttpClient())->pushJson(200, [self::method()]);

        $method = (new Byop($this->transport($client)))->paymentMethods()[0];

        self::assertSame(['1.75', true, null], [$method->fee, $method->isConfigured, $method->isVerified]);
    }

    public function testUpdateCredentialsSendsTheFieldsVerbatim(): void
    {
        $client = (new MockHttpClient())->pushJson(200, ['detail' => 'Credentials saved successfully.']);

        (new Byop($this->transport($client)))->updateCredentials(
            self::METHOD,
            new ByopCredentialsParams(['product_code' => 'EPAYTEST', 'merchant_secret' => 's3cret']),
        );

        self::assertSame(['product_code' => 'EPAYTEST', 'merchant_secret' => 's3cret'], self::decodeBody($client));
    }

    public function testUpdateCredentialsPatchesItsRoute(): void
    {
        $client = (new MockHttpClient())->pushJson(200, ['detail' => 'Credentials saved successfully.']);

        $result = (new Byop($this->transport($client)))->updateCredentials(self::METHOD, new ByopCredentialsParams([]));

        self::assertSame(
            ['PATCH', true, 'Credentials saved successfully.'],
            [$client->lastRequest()->method, str_ends_with($client->lastRequest()->url, '/api/v1/byop/credentials/' . self::METHOD . '/'), $result->detail],
        );
    }

    public function testUpdateFeeSendsAndReadsTheFee(): void
    {
        $client = (new MockHttpClient())->pushJson(200, ['processing_fee' => '1.75']);

        $fee = (new Byop($this->transport($client)))->updateFee(self::METHOD, new ByopFeeParams('1.75'));

        self::assertSame([['processing_fee' => '1.75'], '1.75'], [self::decodeBody($client), $fee->processingFee]);
    }

    /** null clears the fee, so it is sent rather than omitted. */
    public function testANullFeeIsSentExplicitly(): void
    {
        $client = (new MockHttpClient())->pushJson(200, ['processing_fee' => null]);

        (new Byop($this->transport($client)))->updateFee(self::METHOD, new ByopFeeParams(null));

        self::assertSame('{"processing_fee":null}', $client->lastRequest()->body);
    }

    public function testUpdateFeePatchesItsRoute(): void
    {
        $client = (new MockHttpClient())->pushJson(200, ['processing_fee' => '0.00']);

        (new Byop($this->transport($client)))->updateFee(self::METHOD, new ByopFeeParams('0'));

        self::assertStringEndsWith('/api/v1/byop/fees/' . self::METHOD . '/', $client->lastRequest()->url);
    }

    public function testAnAccountNotOnByopIsPermissionDenied(): void
    {
        $client = (new MockHttpClient())->pushJson(403, ['detail' => "Your own payment account isn't enabled."]);

        $this->expectException(PermissionDeniedError::class);

        (new Byop($this->transport($client)))->updateFee(self::METHOD, new ByopFeeParams('1.00'));
    }

    public function testTheByopResourceIsWiredOntoTheClient(): void
    {
        self::assertInstanceOf(Byop::class, (new SuqoClient(apiKey: self::KEY, httpClient: new MockHttpClient()))->byop);
    }

    /** @return array<string, mixed> */
    private static function method(): array
    {
        return [
            'company_id' => 118,
            'payment_type_id' => self::METHOD,
            'payment_type' => 'esewa',
            'alias' => 'eSewa',
            'logo' => null,
            'order' => 1,
            'is_active' => true,
            'fee' => '1.75',
            'is_configured' => true,
            'is_verified' => null,
        ];
    }

    private static function decodeBody(MockHttpClient $client): mixed
    {
        return json_decode((string) $client->lastRequest()->body, true);
    }
}
