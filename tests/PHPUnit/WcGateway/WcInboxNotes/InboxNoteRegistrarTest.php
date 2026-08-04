<?php

declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\WcGateway\WcInboxNotes;

use Mockery;
use RuntimeException;
use WooCommerce\PayPalCommerce\TestCase;

use function Brain\Monkey\Functions\when;

class InboxNoteRegistrarTest extends TestCase {

	public function test_register_skips_inbox_notes_during_ajax_requests(): void {
		when( 'wp_doing_ajax' )->justReturn( true );

		$inbox_note = Mockery::mock( InboxNoteInterface::class );
		$inbox_note->shouldNotReceive( 'name' );

		$registrar = new InboxNoteRegistrar( array( $inbox_note ), 'woocommerce-paypal-payments' );

		self::assertNull( $registrar->register() );
	}

	public function test_register_continues_registering_inbox_notes_outside_ajax_requests(): void {
		when( 'wp_doing_ajax' )->justReturn( false );

		$inbox_note = Mockery::mock( InboxNoteInterface::class );
		$inbox_note
			->shouldReceive( 'name' )
			->once()
			->andThrow( new RuntimeException( 'Inbox note registration reached.' ) );

		$registrar = new InboxNoteRegistrar( array( $inbox_note ), 'woocommerce-paypal-payments' );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Inbox note registration reached.' );

		$registrar->register();
	}
}
