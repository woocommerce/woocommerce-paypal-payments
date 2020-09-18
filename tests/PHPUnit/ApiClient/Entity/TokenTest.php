<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Entity;

use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;

class TokenTest extends TestCase
{

    /**
     * @dataProvider dataForTestDefault
     * @param \stdClass $data
     */
    public function testDefault(\stdClass $data)
    {
        $token = new Token($data);
        $this->assertEquals($data->token, $token->token());
        $this->assertTrue($token->is_valid());
    }

    public function dataForTestDefault() : array
    {
        return [
            'default' => [
                (object)[
                    'created' => time(),
                    'expires_in' => 100,
                    'token' => 'abc',
                ],
            ],
            'created_not_needed' => [
                (object)[
                    'expires_in' => 100,
                    'token' => 'abc',
                ],
            ],
        ];
    }

    public function testIsValid()
    {
        $data = (object) [
            'created' => time() - 100,
            'expires_in' => 99,
            'token' => 'abc',
        ];

        $token = new Token($data);
        $this->assertFalse($token->is_valid());
    }

    public function testFromBearerJson()
    {
        $data = json_encode([
            'expires_in' => 100,
            'access_token' => 'abc',
        ]);

        $token = Token::from_json($data);
        $this->assertEquals('abc', $token->token());
        $this->assertTrue($token->is_valid());
    }

    public function testFromIdentityJson()
    {
        $data = json_encode([
            'expires_in' => 100,
            'client_token' => 'abc',
        ]);

        $token = Token::from_json($data);
        $this->assertEquals('abc', $token->token());
        $this->assertTrue($token->is_valid());
    }

    public function testAsJson()
    {
	    expect('wp_json_encode')->andReturnUsing('json_encode');
        $data = (object) [
            'created' => 100,
            'expires_in' => 100,
            'token' => 'abc',
        ];

        $token = new Token($data);
        $json = json_decode($token->as_json());
        $this->assertEquals($data->token, $json->token);
        $this->assertEquals($data->created, $json->created);
        $this->assertEquals($data->expires_in, $json->expires_in);
    }

    /**
     * @dataProvider dataForTestExceptions
     * @param \stdClass $data
     */
    public function testExceptions(\stdClass $data)
    {
        $this->expectException(RuntimeException::class);
        new Token($data);
    }

    public function dataForTestExceptions() : array
    {
        return [
            'created_is_not_integer' => [
                (object) [
                    'created' => 'abc',
                    'expires_in' => 123,
                    'token' => 'abc',
                ],
            ],
            'expires_in_is_not_integer' => [
                (object) [
                    'expires_in' => 'abc',
                    'token' => 'abc',
                ],
            ],
            'access_token_is_not_string' => [
                (object) [
                    'expires_in' => 123,
                    'token' => ['abc'],
                ],
            ],
            'access_token_does_not_exist' => [
                (object) [
                    'expires_in' => 123,
                ],
            ],
            'expires_in_does_not_exist' => [
                (object) [
                    'token' => 'abc',
                ],
            ],
        ];
    }
}
