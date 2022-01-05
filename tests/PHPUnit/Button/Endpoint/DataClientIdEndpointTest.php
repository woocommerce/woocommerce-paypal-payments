<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Endpoint;

use WooCommerce\PayPalCommerce\ApiClient\Endpoint\IdentityToken;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Token;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\TestCase;
use Mockery;
use WooCommerce\WooCommerce\Logging\Logger\NullLogger;
use function Brain\Monkey\Functions\when;
use function Brain\Monkey\Functions\expect;

class DataClientIdEndpointTest extends TestCase
{
    private $requestData;
    private $identityToken;
    private $sut;

    public function setUp(): void
    {
        parent::setUp();
        $this->requestData = Mockery::mock(RequestData::class);
        $this->identityToken = Mockery::mock(IdentityToken::class);
        $this->sut = new DataClientIdEndpoint($this->requestData, $this->identityToken, new NullLogger());
    }

    public function testHandleRequestSuccess()
    {
        $userId = 1;
        $token = Mockery::mock(Token::class);

        $this->requestData->shouldReceive('read_request')
            ->with($this->sut::nonce());
        when('get_current_user_id')->justReturn($userId);
        $this->identityToken->shouldReceive('generate_for_user')
            ->with($userId)
            ->andReturn($token);

        $token->shouldReceive('token')
            ->andReturn('token');
        $token->shouldReceive('expiration_timestamp')
            ->andReturn(3600);
        expect('wp_send_json')->with([
            'token'      => $token->token(),
            'expiration' => $token->expiration_timestamp(),
            'user'       => $userId,
        ]);

        $result = $this->sut->handle_request();
        $this->assertTrue($result);
    }

    public function testHandleRequestFails()
    {
        $userId = 1;
        $this->requestData->shouldReceive('read_request')
            ->with($this->sut::nonce());
        when('get_current_user_id')->justReturn($userId);
        $this->identityToken->shouldReceive('generate_for_customer')
            ->andThrows(RuntimeException::class);
        expect('wp_send_json_error');

        $result = $this->sut->handle_request();
        $this->assertFalse($result);
    }
}
