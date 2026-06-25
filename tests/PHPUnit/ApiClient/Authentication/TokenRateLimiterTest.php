<?php
declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\ApiClient\Authentication;

use Mockery;
use Psr\Log\LoggerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\TestCase;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class TokenRateLimiterTest extends TestCase
{
    private $cache;
    private $logger;
    private $sut;

    public function setUp(): void
    {
        parent::setUp();

        $this->cache = Mockery::mock(Cache::class);
        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->sut = new TokenRateLimiter($this->cache, $this->logger);
    }

    public function testNotBlockedWhenNoState()
    {
        $this->cache->shouldReceive('get')->andReturn(false);

        $this->assertNull($this->sut->retry_after_seconds('bearer'));
        $this->assertFalse($this->sut->is_blocked('bearer'));
    }

    public function testBlockedWithinCooldown()
    {
        $this->cache->shouldReceive('get')->andReturn(
            ['retry_at' => time() + 120, 'count' => 1, 'reason' => 'rate_limited_backoff']
        );

        $remaining = $this->sut->retry_after_seconds('bearer');
        $this->assertNotNull($remaining);
        $this->assertGreaterThan(0, $remaining);
        $this->assertLessThanOrEqual(120, $remaining);
        $this->assertTrue($this->sut->is_blocked('bearer'));
    }

    public function testNotBlockedAfterCooldownExpires()
    {
        $this->cache->shouldReceive('get')->andReturn(
            ['retry_at' => time() - 1, 'count' => 1, 'reason' => 'rate_limited_backoff']
        );

        $this->assertNull($this->sut->retry_after_seconds('bearer'));
    }

    public function testRegisterFailure429WithNumericRetryAfter()
    {
        when('wp_remote_retrieve_header')->justReturn('90');
        when('wp_remote_retrieve_body')->justReturn('{"error":"RATE_LIMIT_REACHED"}');
        $this->cache->shouldReceive('get')->andReturn(false);
        $this->cache->shouldReceive('set')->once();
        $this->logger->shouldReceive('warning')->once();

        $cooldown = $this->sut->register_failure('bearer', 429, []);

        $this->assertSame(90, $cooldown);
    }

    public function testRegisterFailure429WithHttpDateRetryAfter()
    {
        $future = gmdate('D, d M Y H:i:s', time() + 30) . ' GMT';
        when('wp_remote_retrieve_header')->justReturn($future);
        when('wp_remote_retrieve_body')->justReturn('');
        $this->cache->shouldReceive('get')->andReturn(false);
        $this->cache->shouldReceive('set')->once();
        $this->logger->shouldReceive('warning');

        $cooldown = $this->sut->register_failure('bearer', 429, []);

        $this->assertGreaterThanOrEqual(25, $cooldown);
        $this->assertLessThanOrEqual(31, $cooldown);
    }

    public function testRegisterFailure429WithoutRetryAfterUsesBackoff()
    {
        when('wp_remote_retrieve_header')->justReturn('');
        when('wp_remote_retrieve_body')->justReturn('');
        $this->cache->shouldReceive('get')->andReturn(false);
        $this->cache->shouldReceive('set')->once();
        $this->logger->shouldReceive('warning');

        $cooldown = $this->sut->register_failure('bearer', 429, []);

        // backoff(0): base 30 + jitter [0, 7].
        $this->assertGreaterThanOrEqual(30, $cooldown);
        $this->assertLessThanOrEqual(38, $cooldown);
    }

	public function testUnparseableRetryAfterFallsBackToBackoff()
	{
		when('wp_remote_retrieve_header')->justReturn('not-a-date');
		when('wp_remote_retrieve_body')->justReturn('');
		$this->cache->shouldReceive('get')->andReturn(false);
		$this->cache->shouldReceive('set')->once();
		$this->logger->shouldReceive('warning');

		$cooldown = $this->sut->register_failure('bearer', 429, []);

		$this->assertGreaterThanOrEqual(30, $cooldown);
		$this->assertLessThanOrEqual(38, $cooldown);
	}

    public function testBackoffGrowsWithFailureCount()
    {
        when('wp_remote_retrieve_header')->justReturn('');
        when('wp_remote_retrieve_body')->justReturn('');
        $this->cache->shouldReceive('get')->andReturn(
            ['retry_at' => time() - 1, 'count' => 2, 'reason' => 'rate_limited_backoff']
        );
        $this->cache->shouldReceive('set')->once();
        $this->logger->shouldReceive('warning');

        $cooldown = $this->sut->register_failure('bearer', 429, []);

        // backoff(2): base = 30 * 2^2 = 120, jitter [0, 30].
        $this->assertGreaterThanOrEqual(120, $cooldown);
        $this->assertLessThanOrEqual(151, $cooldown);
    }

    public function testBackoffCapsAtMax()
    {
        when('wp_remote_retrieve_header')->justReturn('');
        when('wp_remote_retrieve_body')->justReturn('');
        $this->cache->shouldReceive('get')->andReturn(
            ['retry_at' => time() - 1, 'count' => 30, 'reason' => 'server_error']
        );
        $this->cache->shouldReceive('set')->once();
        $this->logger->shouldNotReceive('warning');

        $cooldown = $this->sut->register_failure('bearer', 500, []);

        $this->assertSame(900, $cooldown);
    }

    public function testRegisterFailure401UsesDeadCredentialsCooldown()
    {
        when('wp_remote_retrieve_header')->justReturn('');
        when('wp_remote_retrieve_body')->justReturn('');
        $this->cache->shouldReceive('get')->andReturn(false);
        $this->cache->shouldReceive('set')->once();
        $this->logger->shouldReceive('warning')->once();

        $cooldown = $this->sut->register_failure('bearer', 401, []);

        $this->assertSame(1800, $cooldown);
    }

    public function testRegisterFailureInvalidClientBodyUsesDeadCredentials()
    {
        when('wp_remote_retrieve_header')->justReturn('');
        when('wp_remote_retrieve_body')->justReturn('{"error":"invalid_client"}');
        $this->cache->shouldReceive('get')->andReturn(false);
        $this->cache->shouldReceive('set')->once();
        $this->logger->shouldReceive('warning')->once();

        $cooldown = $this->sut->register_failure('sdk-client-token', 400, []);

        $this->assertSame(1800, $cooldown);
    }

    public function testRegisterFailureNetworkUsesBackoff()
    {
        when('wp_remote_retrieve_header')->justReturn('');
        when('wp_remote_retrieve_body')->justReturn('');
        $this->cache->shouldReceive('get')->andReturn(false);
        $this->cache->shouldReceive('set')->once();
        $this->logger->shouldNotReceive('warning');

        $cooldown = $this->sut->register_failure('id-token', 0, []);

        $this->assertGreaterThanOrEqual(30, $cooldown);
        $this->assertLessThanOrEqual(38, $cooldown);
    }

    public function testClearDeletesState()
    {
        $this->cache->expects('delete')->with('bearer-circuit-state');

        $this->sut->clear('bearer');
        $this->addToAssertionCount(1);
    }
}
