<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Subscription\Repository;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\PaymentTokenEndpoint;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentToken;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\ApiClient\Factory\PaymentTokenFactory;
use WooCommerce\PayPalCommerce\TestCase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenRepository;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class PaymentTokenRepositoryTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private $factory;
    private $endpoint;
    private $sut;

    public function setUp(): void
    {
        parent::setUp();
        $this->factory = Mockery::mock(PaymentTokenFactory::class);
        $this->endpoint = Mockery::mock(PaymentTokenEndpoint::class);
        $this->sut = new PaymentTokenRepository($this->factory, $this->endpoint);
    }

    public function testForUserIdFromArray()
    {
        $id = 1;
        $token = ['id' => 'foo'];
	    $paymentToken = Mockery::mock(PaymentToken::class);
	    $paymentToken->shouldReceive('id')
		    ->andReturn('foo');

        expect('get_user_meta')->with($id, $this->sut::USER_META, true)
            ->andReturn($token);

        $this->factory->shouldReceive('from_array')->with($token)
            ->andReturn($paymentToken);

        $result = $this->sut->for_user_id($id);
        $this->assertInstanceOf(PaymentToken::class, $result);
    }

    public function testFetchForUserId()
    {
        $id = 1;
        $source = new \stdClass();
        $paymentToken = new PaymentToken('foo', $source, 'PAYMENT_METHOD_TOKEN');

        when('get_user_meta')->justReturn([]);
        $this->endpoint->shouldReceive('for_user')
            ->with($id)
            ->andReturn([$paymentToken]);
        expect('update_user_meta')->with($id, $this->sut::USER_META, $paymentToken->to_array());

        $result = $this->sut->for_user_id($id);
        $this->assertInstanceOf(PaymentToken::class, $result);
    }

    public function testForUserIdFails()
    {
        $id = 1;
        when('get_user_meta')->justReturn([]);

        $this->endpoint
            ->expects('for_user')
            ->with($id)
            ->andThrow(RuntimeException::class);

        $result = $this->sut->for_user_id($id);
        $this->assertNull($result);
    }

    public function testDeleteToken()
    {
        $id = 1;
	    $paymentToken = Mockery::mock(PaymentToken::class);
	    $paymentToken->shouldReceive('id')
		    ->andReturn('foo');

        expect('delete_user_meta')->with($id, $this->sut::USER_META);
        $this->endpoint->shouldReceive('delete_token')
            ->with($paymentToken);

        $this->sut->delete_token($id, $paymentToken);
    }

    public function testAllForUserId()
	{
		$id = 1;
		$tokens = [];

		$this->endpoint->shouldReceive('for_user')
			->with($id)
			->andReturn($tokens);
		expect('update_user_meta')->with($id, $this->sut::USER_META, $tokens);

		$result = $this->sut->all_for_user_id($id);
		$this->assertSame($tokens, $result);
	}

	public function test_AllForUserIdReturnsEmptyArrayIfGettingTokenFails()
	{
		$id = 1;
		$tokens = [];

		$this->endpoint
			->expects('for_user')
			->with($id)
			->andThrow(RuntimeException::class);

		$result = $this->sut->all_for_user_id($id);
		$this->assertSame($tokens, $result);
	}

	public function testTokensContainCardReturnsTrue()
	{
		$source = new \stdClass();
		$card = new \stdClass();
		$source->card = $card;
		$token = Mockery::mock(PaymentToken::class);
		$tokens = [$token];

		$token->shouldReceive('source')->andReturn($source);

		$this->assertTrue($this->sut->tokens_contains_card($tokens));
	}

	public function testTokensContainCardReturnsFalse()
	{
		$tokens = [];
		$this->assertFalse($this->sut->tokens_contains_card($tokens));
	}

	public function testTokensContainPayPalReturnsTrue()
	{
		$source = new \stdClass();
		$paypal = new \stdClass();
		$source->paypal = $paypal;
		$token = Mockery::mock(PaymentToken::class);
		$tokens = [$token];

		$token->shouldReceive('source')->andReturn($source);

		$this->assertTrue($this->sut->tokens_contains_paypal($tokens));
	}

	public function testTokensContainPayPalReturnsFalse()
	{
		$tokens = [];
		$this->assertFalse($this->sut->tokens_contains_paypal($tokens));
	}


}
