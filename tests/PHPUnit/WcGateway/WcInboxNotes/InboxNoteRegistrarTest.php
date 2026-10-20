<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WcGateway\WcInboxNotes;

use Automattic\WooCommerce\Admin\Notes\Notes;
use Mockery;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

class InboxNoteRegistrarTest extends TestCase
{
    /**
     * GIVEN the current request is a WordPress AJAX request
     * WHEN the inbox notes are registered
     * THEN registration exits early and no configured note is inspected or persisted
     */
    public function testRegisterSkipsNotesDuringAjaxRequest(): void
    {
        when('wp_doing_ajax')->justReturn(true);

        $inbox_note = Mockery::mock(InboxNoteInterface::class);
        $inbox_note->shouldNotReceive('name');
        $inbox_note->shouldNotReceive('is_enabled');

        $testee = new InboxNoteRegistrar([$inbox_note], 'woocommerce-paypal-payments/woocommerce-paypal-payments.php');

        $testee->register();

        $this->addToAssertionCount(1);
    }

    /**
     * GIVEN a non-AJAX admin request with a note that already exists and is enabled
     * WHEN the inbox notes are registered
     * THEN the existing note is looked up and processing short-circuits without creating a new note
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testRegisterProcessesNotesOutsideAjaxRequest(): void
    {
        when('wp_doing_ajax')->justReturn(false);

        Mockery::mock('alias:' . Notes::class)
            ->shouldReceive('get_note_by_name')
            ->once()
            ->andReturn((object) array());

        $inbox_note = Mockery::mock(InboxNoteInterface::class);
        $inbox_note->shouldReceive('name')->once()->andReturn('ppcp-inbox-note');
        $inbox_note->shouldReceive('is_enabled')->andReturn(true);

        $testee = new InboxNoteRegistrar([$inbox_note], 'woocommerce-paypal-payments/woocommerce-paypal-payments.php');

        $testee->register();

        $this->addToAssertionCount(1);
    }
}
