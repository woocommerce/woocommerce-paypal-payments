<?php
declare( strict_types=1 );

namespace WooCommerce\PayPalCommerce\FraudProtection;

use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use WooCommerce\PayPalCommerce\TestCase;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\WcInboxNotes\InboxNoteFactory;
use WooCommerce\PayPalCommerce\WcGateway\WcInboxNotes\InboxNoteInterface;
use function Brain\Monkey\Functions\when;

/**
 * Covers the 'wcgateway.settings.inbox-notes' extension.
 */
class InboxNotesExtensionTest extends TestCase {
	use MockeryPHPUnitIntegration;

	const REMINDER_NOTE_NAME = 'ppcp-recaptcha-protection-note12';
	const LEGACY_NOTE_NAME   = 'ppcp-recaptcha-protection-note';

	/**
	 * @return array<string, InboxNoteInterface> Notes keyed by name.
	 */
	private function build_notes( array $recaptcha_settings ): array {
		when( 'get_option' )->justReturn( $recaptcha_settings );
		when( 'admin_url' )->returnArg();

		$extensions = require ROOT_DIR . '/modules/ppcp-fraud-protection/extensions.php';
		$callback   = $extensions['wcgateway.settings.inbox-notes'];

		$container = Mockery::mock( ContainerInterface::class );
		$container->shouldReceive( 'get' )
			->with( 'wcgateway.settings.inbox-note-factory' )
			->andReturn( new InboxNoteFactory() );

		$notes = $callback( array(), $container );

		$by_name = array();
		foreach ( $notes as $note ) {
			$by_name[ $note->name() ] = $note;
		}

		return $by_name;
	}

	/**
	 * @dataProvider recaptcha_settings_provider
	 */
	public function test_reminder_note_reflects_recaptcha_setting( array $recaptcha_settings, bool $expected_reminder_enabled ): void {
		$notes = $this->build_notes( $recaptcha_settings );

		$this->assertArrayHasKey( self::REMINDER_NOTE_NAME, $notes );
		$this->assertSame( $expected_reminder_enabled, $notes[ self::REMINDER_NOTE_NAME ]->is_enabled() );

		$this->assertArrayHasKey( self::LEGACY_NOTE_NAME, $notes );
		$this->assertFalse( $notes[ self::LEGACY_NOTE_NAME ]->is_enabled() );
	}

	public function recaptcha_settings_provider(): array {
		return array(
			'recaptcha enabled via "yes" string'                 => array( array( 'enabled' => 'yes' ), false ),
			'recaptcha enabled via numeric "1" string (regression)' => array( array( 'enabled' => '1' ), false ),
			'recaptcha enabled via native boolean true (regression)' => array( array( 'enabled' => true ), false ),
			'recaptcha disabled via "no" string'                 => array( array( 'enabled' => 'no' ), true ),
			'recaptcha settings missing defaults to disabled'    => array( array(), true ),
		);
	}
}
