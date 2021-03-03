<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Endpoint;

use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentToken;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Token;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PaymentTokenFactory;
use WooCommerce\PayPalCommerce\ApiClient\TestCase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use function Brain\Monkey\Functions\expect;

class PaymentTokenEndpointTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private $host;
    private $bearer;
    private $factory;
    private $logger;
    private $prefix;
    private $sut;

    public function setUp(): void
    {
        parent::setUp();

        $this->host = 'https://example.com/';
        $this->bearer = Mockery::mock(Bearer::class);
        $this->factory = Mockery::mock(PaymentTokenFactory::class);
        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->prefix = 'prefix';
        $this->sut = new PaymentTokenEndpoint(
            $this->host,
            $this->bearer,
            $this->factory,
            $this->logger,
            $this->prefix
        );
    }

    public function testForUserReturnsToken()
    {
        $id = 1;
		$token = Mockery::mock(Token::class);
        $rawResponse = ['body' => '{"payment_tokens":[{"id": "123abc"}]}'];
        $paymentToken = new PaymentToken('foo', 'PAYMENT_METHOD_TOKEN');

        $this->bearer->shouldReceive('bearer')
            ->andReturn($token);
        $token->shouldReceive('token')
            ->andReturn('bearer');

        $this->ensureRequestForUser($rawResponse, $id);
        expect('is_wp_error')->with($rawResponse)->andReturn(false);
        expect('wp_remote_retrieve_response_code')->with($rawResponse)->andReturn(200);

        $this->factory->shouldReceive('from_paypal_response')
            ->andReturn($paymentToken);

        $result = $this->sut->for_user($id);
        $this->assertInstanceOf(PaymentToken::class, $result[0]);

    }

    public function testForUserFailsBecauseOfWpError()
    {
        $id = 1;
        $token = Mockery::mock(Token::class);
        $rawResponse = [];
        $this->bearer->shouldReceive('bearer')
            ->andReturn($token);
        $token->shouldReceive('token')
            ->andReturn('bearer');
        $this->ensureRequestForUser($rawResponse, $id);

        expect('wp_remote_get')->andReturn($rawResponse);
        expect('is_wp_error')->with($rawResponse)->andReturn(true);
        $this->logger->shouldReceive('log');

        $this->expectException(RuntimeException::class);
        $this->sut->for_user($id);
    }

    public function testForUserFailsBecauseResponseCodeIsNot200()
    {
        $id = 1;
        $token = Mockery::mock(Token::class);
        $rawResponse = ['body' => '{"some_error":true}'];
        $this->bearer->shouldReceive('bearer')
            ->andReturn($token);
        $token->shouldReceive('token')
            ->andReturn('bearer');
        $this->ensureRequestForUser($rawResponse, $id);


        expect('wp_remote_get')->andReturn($rawResponse);
        expect('is_wp_error')->with($rawResponse)->andReturn(false);
        expect('wp_remote_retrieve_response_code')->with($rawResponse)->andReturn(500);
        $this->logger->shouldReceive('log');

        $this->expectException(PayPalApiException::class);
        $this->sut->for_user($id);
    }

    public function testForUserFailBecauseEmptyTokens()
    {
        $id = 1;
        $token = Mockery::mock(Token::class);
        $rawResponse = ['body' => '{"payment_tokens":[]}'];
        $this->bearer->shouldReceive('bearer')
            ->andReturn($token);
        $token->shouldReceive('token')
            ->andReturn('bearer');
        $this->ensureRequestForUser($rawResponse, $id);


        expect('wp_remote_get')->andReturn($rawResponse);
        expect('is_wp_error')->with($rawResponse)->andReturn(false);
        expect('wp_remote_retrieve_response_code')->with($rawResponse)->andReturn(200);
        $this->logger->shouldReceive('log');

        $this->expectException(RuntimeException::class);
        $this->sut->for_user($id);
    }

    public function testDeleteToken()
    {
        $paymentToken = new PaymentToken('foo', 'PAYMENT_METHOD_TOKEN');
        $token = Mockery::mock(Token::class);
        $this->bearer->shouldReceive('bearer')
            ->andReturn($token);
        $token->shouldReceive('token')
            ->andReturn('bearer');

        expect('wp_remote_get')->andReturn();
        expect('is_wp_error')->andReturn(false);
        expect('wp_remote_retrieve_response_code')->andReturn(204);

        $this->sut->delete_token($paymentToken);
    }

    public function testDeleteTokenFails()
    {
        $paymentToken = new PaymentToken('foo', 'PAYMENT_METHOD_TOKEN');
        $token = Mockery::mock(Token::class);
        $this->bearer->shouldReceive('bearer')
            ->andReturn($token);
        $token->shouldReceive('token')
            ->andReturn('bearer');

        expect('wp_remote_get')->andReturn();
        expect('is_wp_error')->andReturn(true);
        $this->logger->shouldReceive('log');

        $this->expectException(RuntimeException::class);
        $this->sut->delete_token($paymentToken);
    }

    /**
     * @param array $rawResponse
     * @param int $id
     * @throws \Brain\Monkey\Expectation\Exception\ExpectationArgsRequired
     */
    private function ensureRequestForUser(array $rawResponse, int $id): void
    {
        $host = $this->host;
        $prefix = $this->prefix;
        expect('wp_remote_get')->andReturnUsing(
            function ($url, $args) use ($rawResponse, $host, $prefix, $id) {
                if ($url !== $host . 'v2/vault/payment-tokens/?customer_id=' . $prefix . $id) {
                    return false;
                }
                if ($args['headers']['Authorization'] !== 'Bearer bearer') {
                    return false;
                }
                if ($args['headers']['Content-Type'] !== 'application/json') {
                    return false;
                }

                return $rawResponse;
            }
        );
    }
}
