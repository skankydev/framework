<?php

namespace SkankyTest\TestCase\Utilities;

use PHPUnit\Framework\TestCase;
use SkankyDev\Utilities\UserAgent;

class UserAgentTest extends TestCase
{
    protected function setUp(): void {
        // Reset Singleton so each test gets a fresh instance
        $ref = new \ReflectionProperty(UserAgent::class, '_instance');
        $ref->setValue(null, null);
    }

    public function testConstructParsesServerAgent(): void {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:64.0) Gecko/20100101 Firefox/64.0';
        $agent = new UserAgent();

        $this->assertEquals('Windows', $agent->os);
        $this->assertEquals('Firefox', $agent->browser);
        $this->assertFalse($agent->mobile);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('userAgentProvider')]
    public function testParseUserAgent(string $agentString, string $os, string $browser, bool $mobile): void {
        $parsed = UserAgent::_parse($agentString);

        $this->assertEquals($os,     $parsed['os']);
        $this->assertEquals($browser,$parsed['browser']);
        $this->assertEquals($mobile, $parsed['mobile']);
        $this->assertEquals($mobile ? 'Mobile' : 'Desktop', UserAgent::_getDevice());
    }

    public static function userAgentProvider(): array {
        return [
            'Firefox Windows' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:64.0) Gecko/20100101 Firefox/64.0',
                'Windows', 'Firefox', false,
            ],
            'Chrome Windows' => [
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/71.0.3578.98 Safari/537.36',
                'Windows', 'Chrome', false,
            ],
            'Safari iOS' => [
                'Mozilla/5.0 (iPhone; CPU iPhone OS 11_0 like Mac OS X) AppleWebKit/604.1.34 (KHTML, like Gecko) Version/11.0 Mobile/15A5341f Safari/604.1',
                'iOS', 'Safari', true,
            ],
            'Chrome Linux' => [
                'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.157 Safari/537.36',
                'Linux', 'Chrome', false,
            ],
            'Chrome Android' => [
                'Mozilla/5.0 (Linux; Android 7.0; Moto G (4)) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/69.0.3497.100 Mobile Safari/537.36',
                'Android', 'Chrome', true,
            ],
            'Opera MacOS' => [
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/61.0.3153.0 Safari/537.36 OPR/48.0.2664.0',
                'MacOS', 'Opera', false,
            ],
            'Firefox Ubuntu' => [
                'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:57.0) Gecko/20100101 Firefox/57.0',
                'Ubuntu', 'Firefox', false,
            ],
        ];
    }
}
