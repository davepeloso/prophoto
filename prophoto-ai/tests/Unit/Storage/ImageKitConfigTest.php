<?php

namespace ProPhoto\AI\Tests\Unit\Storage;

use PHPUnit\Framework\TestCase;
use ProPhoto\AI\Storage\ImageKitConfig;

class ImageKitConfigTest extends TestCase
{
    public function test_construction(): void
    {
        $config = new ImageKitConfig(
            publicKey: 'public_test_key',
            privateKey: 'private_test_key',
            urlEndpoint: 'https://ik.imagekit.io/prophoto',
        );

        $this->assertSame('public_test_key', $config->publicKey());
        $this->assertSame('private_test_key', $config->privateKey());
        $this->assertSame('https://ik.imagekit.io/prophoto', $config->urlEndpoint());
    }

    public function test_url_endpoint_trims_trailing_slash(): void
    {
        $config = new ImageKitConfig(
            publicKey: 'pk',
            privateKey: 'sk',
            urlEndpoint: 'https://ik.imagekit.io/prophoto/',
        );

        $this->assertSame('https://ik.imagekit.io/prophoto', $config->urlEndpoint());
    }

    public function test_validate_returns_true_when_all_keys_present(): void
    {
        $config = new ImageKitConfig(
            publicKey: 'pk',
            privateKey: 'sk',
            urlEndpoint: 'https://ik.imagekit.io/prophoto',
        );

        $this->assertTrue($config->validate());
    }

    public function test_validate_returns_false_when_public_key_empty(): void
    {
        $config = new ImageKitConfig(publicKey: '', privateKey: 'sk', urlEndpoint: 'https://ik.imagekit.io/prophoto');

        $this->assertFalse($config->validate());
    }

    public function test_validate_returns_false_when_private_key_empty(): void
    {
        $config = new ImageKitConfig(publicKey: 'pk', privateKey: '', urlEndpoint: 'https://ik.imagekit.io/prophoto');

        $this->assertFalse($config->validate());
    }

    public function test_validate_returns_false_when_url_endpoint_empty(): void
    {
        $config = new ImageKitConfig(publicKey: 'pk', privateKey: 'sk', urlEndpoint: '');

        $this->assertFalse($config->validate());
    }

    public function test_from_config_factory(): void
    {
        $config = ImageKitConfig::fromConfig([
            'public_key' => 'pub_from_config',
            'private_key' => 'priv_from_config',
            'url_endpoint' => 'https://ik.imagekit.io/demo',
        ]);

        $this->assertSame('pub_from_config', $config->publicKey());
        $this->assertSame('priv_from_config', $config->privateKey());
        $this->assertSame('https://ik.imagekit.io/demo', $config->urlEndpoint());
        $this->assertTrue($config->validate());
    }

    public function test_from_config_uses_empty_defaults(): void
    {
        $config = ImageKitConfig::fromConfig([]);

        $this->assertSame('', $config->publicKey());
        $this->assertSame('', $config->privateKey());
        $this->assertSame('', $config->urlEndpoint());
        $this->assertFalse($config->validate());
    }
}
