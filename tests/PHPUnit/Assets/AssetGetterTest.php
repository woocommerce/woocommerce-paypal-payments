<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Assets;

use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\when;

class AssetGetterTest extends TestCase
{
    private string $plugin_folder_path;

    public function setUp(): void
    {
        parent::setUp();

        when('trailingslashit')->alias(static function (string $path): string {
            return rtrim($path, '/') . '/';
        });

        $this->plugin_folder_path = sys_get_temp_dir() . '/ppcp-asset-getter-test-' . uniqid('', true);
        mkdir($this->plugin_folder_path . '/assets', 0777, true);
    }

    public function tearDown(): void
    {
        $assets_dir = $this->plugin_folder_path . '/assets';
        if (is_dir($assets_dir)) {
            foreach (glob($assets_dir . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($assets_dir);
        }
        if (is_dir($this->plugin_folder_path)) {
            rmdir($this->plugin_folder_path);
        }

        parent::tearDown();
    }

    private function createTestee(string $module_name = 'test-module'): AssetGetter
    {
        return new AssetGetter('https://example.com/', $this->plugin_folder_path, $module_name);
    }

    private function write_asset_file(string $module_name, string $asset_name, string $php_content): void
    {
        $compiled_name = str_replace('/', '-', $asset_name);
        $type          = pathinfo($asset_name, PATHINFO_EXTENSION);
        $without_ext   = pathinfo("{$module_name}-{$type}-{$compiled_name}", PATHINFO_FILENAME);

        file_put_contents(
            $this->plugin_folder_path . "/assets/{$without_ext}.asset.php",
            $php_content
        );
    }

    /**
     * GIVEN a webpack-generated .asset.php file declaring dependencies and a content-hash version
     * WHEN the asset data is requested
     * THEN the dependencies and version are returned exactly as recorded by webpack
     */
    public function test_get_asset_data_returns_dependencies_and_version_from_existing_file(): void
    {
        $this->write_asset_file(
            'test-module',
            'boot.js',
            "<?php return array('dependencies' => array('wp-data', 'wp-element'), 'version' => 'abc123');"
        );

        $testee = $this->createTestee();
        $data   = $testee->get_asset_data('boot.js', '1.0.0');

        $this->assertSame(['wp-data', 'wp-element'], $data['dependencies']);
        $this->assertSame('abc123', $data['version']);
    }

    /**
     * GIVEN no compiled .asset.php file exists for the requested asset (e.g. an unbuilt checkout)
     * WHEN the asset data is requested
     * THEN an empty dependency list and the caller-supplied fallback version are returned
     */
    public function test_get_asset_data_falls_back_when_file_is_missing(): void
    {
        $testee = $this->createTestee();
        $data   = $testee->get_asset_data('boot.js', 'fallback-version');

        $this->assertSame([], $data['dependencies']);
        $this->assertSame('fallback-version', $data['version']);
    }

    /**
     * GIVEN a .asset.php file whose contents do not match the expected shape
     * WHEN the asset data is requested
     * THEN each key falls back independently rather than the whole result erroring or
     *      being discarded
     *
     * @dataProvider malformed_asset_file_provider
     */
    public function test_get_asset_data_falls_back_per_key_on_malformed_file(
        string $php_content,
        array $expected_dependencies,
        string $expected_version
    ): void {
        $this->write_asset_file('test-module', 'boot.js', $php_content);

        $testee = $this->createTestee();
        $data   = $testee->get_asset_data('boot.js', 'fallback-version');

        $this->assertSame($expected_dependencies, $data['dependencies']);
        $this->assertSame($expected_version, $data['version']);
    }

    public function malformed_asset_file_provider(): array
    {
        return [
            'file returns a non-array value entirely' => [
                '<?php return null;',
                [],
                'fallback-version',
            ],
            'dependencies key missing falls back to empty array' => [
                "<?php return array('version' => 'abc123');",
                [],
                'abc123',
            ],
            'version key missing falls back to the caller-supplied version' => [
                "<?php return array('dependencies' => array('wp-data'));",
                ['wp-data'],
                'fallback-version',
            ],
            'dependencies is not an array falls back to empty array' => [
                "<?php return array('dependencies' => 'wp-data', 'version' => 'abc123');",
                [],
                'abc123',
            ],
            'version is not a string falls back to the caller-supplied version' => [
                "<?php return array('dependencies' => array('wp-data'), 'version' => 123);",
                ['wp-data'],
                'fallback-version',
            ],
        ];
    }
}
