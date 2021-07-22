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
                'label' => 'Foo',
                'value' => 'Bar'
            ],
        ];

        when('esc_attr')->returnArg();

        $testee = new Renderer();
        $result = $testee->render('Some title here', $items);

        self::assertStringContainsString('<h2>Some title here</h2>', $result);
        self::assertStringContainsString('<td data-export-label="Foo">Foo</td>', $result);
        self::assertStringContainsString('<td>Bar</td>', $result);
    }
}