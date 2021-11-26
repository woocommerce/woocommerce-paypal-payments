<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\StatusReport;

use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

class RendererTest extends TestCase
{
    public function testRender()
    {
        $items = [
            [
                'label' => 'Translated Foo',
                'exported_label' => 'Foo',
                'description' => 'Bar',
                'value' => 'Baz'
            ],
        ];

        when('esc_attr')->returnArg();
        when('wp_kses_post')->returnArg();
        when('wc_help_tip')->returnArg();

        $testee = new Renderer();
        $result = $testee->render('Some title here', $items);

        self::assertStringContainsString('<h2>Some title here</h2>', $result);
        self::assertStringContainsString('data-export-label="Foo"', $result);
        self::assertStringContainsString('Translated Foo', $result);
        self::assertStringContainsString('<td class="help">Bar</td>', $result);
        self::assertStringContainsString('<td>Baz</td>', $result);
    }
}
