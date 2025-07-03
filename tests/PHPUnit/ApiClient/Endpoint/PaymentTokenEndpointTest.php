<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Endpoint;

use Psr\Log\LoggerInterface;
use Requests_Utility_CaseInsensitiveDictionary;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\Bearer;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentToken;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Token;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PaymentTokenActionLinksFactory;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PaymentTokenFactory;
use WooCommerce\PayPalCommerce\ApiClient\Repository\CustomerRepository;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use WooCommerce\PayPalCommerce\ApiClient\Repository\PayPalRequestIdRepository;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;

class PaymentTokenEndpointTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private $host;
    private $bearer;
    private $factory;
	private $payment_token_action_links_factory;
    private $customer_repository;
	private $request_id_repository;
    private $sut;

    public function setUp(): void
    {
        parent::setUp();

        $this->host = 'https://example.com/';
        $this->bearer = Mockery::mock(Bearer::class);
        $this->factory = Mockery::mock(PaymentTokenFactory::class);
        $this->payment_token_action_links_factory = Mockery::mock(PaymentTokenActionLinksFactory::class);
        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->customer_repository = Mockery::mock(CustomerRepository::class);
        $this->request_id_repository = Mockery::mock(PayPalRequestIdRepository::class);
        $this->sut = new PaymentTokenEndpoint(
            $this->host,
            $this->bearer,
            $this->factory,
			$this->payment_token_action_links_factory,
            $this->logger,
			$this->customer_repository,
			$this->request_id_repository
        );
    }

    public function testForUserReturnsToken()
    {
        $id = 1;
		$token = Mockery::mock(Token::class);
		$headers = Mockery::mock(Requests_Utility_CaseInsensitiveDictionary::class);
		$headers->shouldReceive('getAll');
        $rawResponse = [
        	'body' => '{"payment_tokens":[{"id": "123abc"}]}',
			'headers' => $headers,
			];
	    $paymentToken = Mockery::mock(PaymentToken::class);
	    $paymentToken->shouldReceive('id')
		    ->andReturn('foo');

        $this->bearer->shouldReceive('bearer')
            ->andReturn($token);
        $token->shouldReceive('token')
            ->andReturn('bearer');

        $this->ensureRequestForUser($rawResponse, '123abc');
        expect('is_wp_error')->with($rawResponse)->andReturn(false);
        expect('wp_remote_retrieve_response_code')->with($rawResponse)->andReturn(200);

        $this->factory->shouldReceive('from_paypal_response')
            ->andReturn($paymentToken);

		$this->logger->shouldReceive('debug');

		$this->customer_repository->shouldReceive('customer_id_for_user')->andReturn('123abc');

        $result = $this->sut->for_user($id);
        $this->assertInstanceOf(PaymentToken::class, $result[0]);

    }

    public function testForUserFailsBecauseOfWpError()
    {
        $id = 1;
        $token = Mockery::mock(Token::class);
		$headers = Mockery::mock(Requests_Utility_CaseInsensitiveDictionary::class);
		$headers->shouldReceive('getAll');
        $rawResponse = ['headers' => $headers,];
        $this->bearer->shouldReceive('bearer')
            ->andReturn($token);
        $token->shouldReceive('token')
            ->andReturn('bearer');
        $this->ensureRequestForUser($rawResponse, '123abc');

        expect('wp_remote_get')->andReturn($rawResponse);
        expect('is_wp_error')->with($rawResponse)->andReturn(true);
        $this->logger->shouldReceive('log');
        $this->logger->shouldReceive('debug');

		$this->customer_repository->shouldReceive('customer_id_for_user')->andReturn('123abc');

        $this->expectException(RuntimeException::class);
        $this->sut->for_user($id);
    }

    public function testForUserFailsBecauseResponseCodeIsNot200()
    {
        $id = 1;
        $token = Mockery::mock(Token::class);
		$headers = Mockery::mock(Requests_Utility_CaseInsensitiveDictionary::class);
		$headers->shouldReceive('getAll');
        $rawResponse = [
        	'body' => '{"some_error":true}',
			'headers' => $headers,
			];
        $this->bearer->shouldReceive('bearer')
            ->andReturn($token);
        $token->shouldReceive('token')
            ->andReturn('bearer');
        $this->ensureRequestForUser($rawResponse, '123abc');


        expect('wp_remote_get')->andReturn($rawResponse);
        expect('is_wp_error')->with($rawResponse)->andReturn(false);
        expect('wp_remote_retrieve_response_code')->with($rawResponse)->andReturn(500);
        $this->logger->shouldReceive('log');
        $this->logger->shouldReceive('debug');

		$this->customer_repository->shouldReceive('customer_id_for_user')->andReturn('123abc');

        $this->expectException(PayPalApiException::class);
        $this->sut->for_user($id);
    }

	/**
	 * @doesNotPerformAssertions
	 */
	public function testDeleteToken()
    {
        $paymentToken = Mockery::mock(PaymentToken::class);
	    $paymentToken->shouldReceive('id')
		    ->andReturn('foo');
        $token = Mockery::mock(Token::class);
        $this->bearer->shouldReceive('bearer')
            ->andReturn($token);
        $token->shouldReceive('token')
            ->andReturn('bearer');

		$headers = Mockery::mock(Requests_Utility_CaseInsensitiveDictionary::class);
		$headers->shouldReceive('getAll');
        expect('wp_remote_get')->andReturn([
        	'headers' => $headers,
		]);
        expect('is_wp_error')->andReturn(false);
        expect('wp_remote_retrieve_response_code')->andReturn(204);
		$this->logger->shouldReceive('debug');

        $this->sut->delete_token($paymentToken);
    }

    public function testDeleteTokenFails()
    {
        $paymentToken = Mockery::mock(PaymentToken::class);
        $paymentToken->shouldReceive('id')
	        ->andReturn('foo');
        $token = Mockery::mock(Token::class);
        $this->bearer->shouldReceive('bearer')
            ->andReturn($token);
        $token->shouldReceive('token')
            ->andReturn('bearer');

		$headers = Mockery::mock(Requests_Utility_CaseInsensitiveDictionary::class);
		$headers->shouldReceive('getAll');
		expect('wp_remote_get')->andReturn([
			'headers' => $headers,
		]);
        expect('is_wp_error')->andReturn(true);
        $this->logger->shouldReceive('log');
		$this->logger->shouldReceive('debug');

        $this->expectException(RuntimeException::class);
        $this->sut->delete_token($paymentToken);
    }

    /**
     * @param array $rawResponse
     * @param int $id
     * @throws \Brain\Monkey\Expectation\Exception\ExpectationArgsRequired
     */
    private function ensureRequestForUser(array $rawResponse, string $id): void
    {
        $host = $this->host;
        expect('wp_remote_get')->andReturnUsing(
            function ($url, $args) use ($rawResponse, $host, $id) {
                if ($url !== $host . 'v2/vault/payment-tokens/?customer_id=' . $id) {
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
