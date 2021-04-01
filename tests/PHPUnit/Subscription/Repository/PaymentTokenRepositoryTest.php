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
        $paymentToken = new PaymentToken('foo', 'PAYMENT_METHOD_TOKEN', $source);

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
}
