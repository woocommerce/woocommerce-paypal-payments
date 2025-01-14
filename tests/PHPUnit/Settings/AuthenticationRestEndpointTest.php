<?php
declare( strict_types = 1 );

namespace WooCommerce\PayPalCommerce\Settings;

use Exception;
use Mockery;
use WP_REST_Request;
use WP_REST_Response;
use WooCommerce\PayPalCommerce\Settings\Endpoint\AuthenticationRestEndpoint;
use WooCommerce\PayPalCommerce\Settings\Service\AuthenticationManager;
use WooCommerce\PayPalCommerce\TestCase;

/**
 * Verify that the Authentication REST endpoints return expected response formats.
 */
class AuthenticationRestEndpointTest extends TestCase {
	/**
	 * Happy path - verify REST response if oauth was successful.
	 *
	 * @return void
	 */
	public function testSuccessfulOAuthAuthentication() : void {
		$authentication_manager = Mockery::mock( AuthenticationManager::class );
		$endpoint               = new AuthenticationRestEndpoint( $authentication_manager );

		$request = Mockery::mock( WP_REST_Request::class );
		$request->shouldReceive( 'get_param' )
			->with( 'sharedId' )
			->andReturn( 'test_shared_id' );
		$request->shouldReceive( 'get_param' )
			->with( 'authCode' )
			->andReturn( 'test_auth_code' );
		$request->shouldReceive( 'get_param' )
			->with( 'useSandbox' )
			->andReturn( true );

		$authentication_manager->shouldReceive( 'validate_id_and_auth_code' )
			->with( 'test_shared_id', 'test_auth_code' )
			->once()
			->andReturn( true );

		$authentication_manager->shouldReceive( 'authenticate_via_oauth' )
			->with( true, 'test_shared_id', 'test_auth_code' )
			->once();

		$authentication_manager->shouldReceive( 'get_account_details' )
			->once()
			->andReturn( [
				'merchant_id'    => 'test_merchant_id',
				'merchant_email' => 'test@example.com',
			] );

		$response = $endpoint->connect_oauth( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( true, $response->get_data()['success'] );
		$this->assertEquals( 'test_merchant_id', $response->get_data()['data']['merchantId'] );
		$this->assertEquals( 'test@example.com', $response->get_data()['data']['email'] );
	}

	/**
	 * Happy path - verify correct REST response for disconnect attempt.
	 *
	 * @return void
	 */
	public function testDisconnectSuccessfully() : void {
		$authentication_manager = Mockery::mock( AuthenticationManager::class );
		$endpoint               = new AuthenticationRestEndpoint( $authentication_manager );

		$authentication_manager->shouldReceive( 'disconnect' )
			->once()
			->withNoArgs();

		$response = $endpoint->disconnect();

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( true, $response->get_data()['success'] );
		$this->assertEquals( 'OK', $response->get_data()['data'] );
	}

	/**
	 * Verify direct connection validation error response.
	 *
	 * @return void
	 */
	public function testValidateIdAndSecretBeforeDirectAuthentication() : void {
		$authentication_manager = Mockery::mock( AuthenticationManager::class );
		$endpoint               = new AuthenticationRestEndpoint( $authentication_manager );

		$request = Mockery::mock( WP_REST_Request::class );
		$request->shouldReceive( 'get_param' )
			->with( 'clientId' )
			->andReturn( 'test_client_id' );
		$request->shouldReceive( 'get_param' )
			->with( 'clientSecret' )
			->andReturn( 'test_client_secret' );
		$request->shouldReceive( 'get_param' )
			->with( 'useSandbox' )
			->andReturn( true );

		$authentication_manager->shouldReceive( 'validate_id_and_secret' )
			->with( 'test_client_id', 'test_client_secret' )
			->once()
			->andThrow( new Exception( 'Mocked: Invalid credentials' ) );

		$authentication_manager->shouldNotReceive( 'authenticate_via_direct_api' );

		$response = $endpoint->connect_direct( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( false, $response->get_data()['success'] );
		$this->assertEquals( 'Mocked: Invalid credentials', $response->get_data()['message'] );
	}

	/**
	 * Verify error response for invalid OAuth parameters
	 *
	 * @return void
	 */
	public function testValidateSharedIdAndAuthCodeBeforeOAuthAuthentication() : void {
		$authentication_manager = Mockery::mock( AuthenticationManager::class );
		$endpoint               = new AuthenticationRestEndpoint( $authentication_manager );

		$request = Mockery::mock( WP_REST_Request::class );
		$request->shouldReceive( 'get_param' )
			->with( 'sharedId' )
			->andReturn( 'test_shared_id' );
		$request->shouldReceive( 'get_param' )
			->with( 'authCode' )
			->andReturn( '' );
		$request->shouldReceive( 'get_param' )
			->with( 'useSandbox' )
			->andReturn( true );

		$authentication_manager->shouldReceive( 'validate_id_and_auth_code' )
			->with( 'test_shared_id', '' )
			->once()
			->andThrow( new Exception( 'Mocked: Validation error' ) );

		$authentication_manager->shouldNotReceive( 'authenticate_via_oauth' );

		$response = $endpoint->connect_oauth( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( false, $response->get_data()['success'] );
		$this->assertEquals( 'Mocked: Validation error', $response->get_data()['message'] );
	}

	/**
     * Verify error REST response when OAuth connection fails.
     *
     * @return void
     */
	public function testHandleExceptionsDuringOAuthAuthentication() : void {
		$authentication_manager = Mockery::mock( AuthenticationManager::class );
		$endpoint               = new AuthenticationRestEndpoint( $authentication_manager );

		$request = Mockery::mock( WP_REST_Request::class );
		$request->shouldReceive( 'get_param' )
			->with( 'sharedId' )
			->andReturn( 'test_shared_id' );
		$request->shouldReceive( 'get_param' )
			->with( 'authCode' )
			->andReturn( 'test_auth_code' );
		$request->shouldReceive( 'get_param' )
			->with( 'useSandbox' )
			->andReturn( true );

		$authentication_manager->shouldReceive( 'validate_id_and_auth_code' )
			->with( 'test_shared_id', 'test_auth_code' )
			->once()
			->andThrow( new Exception( 'Mocked: OAuth failed' ) );

		$response = $endpoint->connect_oauth( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( false, $response->get_data()['success'] );
		$this->assertEquals( 'Mocked: OAuth failed', $response->get_data()['message'] );
	}
}


