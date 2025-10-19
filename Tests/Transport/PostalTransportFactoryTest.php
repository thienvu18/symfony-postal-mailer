<?php

declare(strict_types=1);

namespace Kyle\PostalMailer\Tests\Transport;

use Kyle\PostalMailer\Transport\PostalApiTransport;
use Kyle\PostalMailer\Transport\PostalTransportFactory;
use PHPUnit\Framework\TestCase;
use Postal\Client;
use Psr\Http\Message\UriInterface;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\Dsn;

final class PostalTransportFactoryTest extends TestCase
{
    public function testCreateBuildsPostalApiTransportWithConfiguredClient(): void
    {
        $factory = new PostalTransportFactory();
        $dsn = new Dsn('postal', 'postal.test', null, 'api-token', 2525);

        $transport = $factory->create($dsn);

        $this->assertInstanceOf(PostalApiTransport::class, $transport);

        $client = $this->readClientFromTransport($transport);
        $this->assertInstanceOf(Client::class, $client);

        $baseUri = $client->getHttpClient()->getConfig('base_uri');
        $this->assertInstanceOf(UriInterface::class, $baseUri);
        $this->assertSame('postal.test', $baseUri->getHost());
        $this->assertSame(2525, $baseUri->getPort());
        $this->assertSame('/api/v1/', $baseUri->getPath());

        $headers = $client->getHttpClient()->getConfig('headers');
        $this->assertIsArray($headers);
        $this->assertArrayHasKey('X-Server-API-Key', $headers);
        $this->assertSame('api-token', $headers['X-Server-API-Key']);
    }

    public function testCreateWithPostalApiSchemeIsSupported(): void
    {
        $factory = new PostalTransportFactory();
        $dsn = new Dsn('postal+api', 'postal.test', null, 'token');

        $transport = $factory->create($dsn);

        $this->assertInstanceOf(PostalApiTransport::class, $transport);
    }

    public function testCreateWithUnsupportedSchemeThrowsException(): void
    {
        $factory = new PostalTransportFactory();
        $dsn = new Dsn('smtp', 'postal.test', null, 'token');

        $this->expectException(UnsupportedSchemeException::class);
        $factory->create($dsn);
    }

    public function testSupportsUsesSupportedSchemes(): void
    {
        $factory = new PostalTransportFactory();

        $this->assertTrue($factory->supports(new Dsn('postal', 'postal.test')));
        $this->assertTrue($factory->supports(new Dsn('postal+api', 'postal.test')));
        $this->assertFalse($factory->supports(new Dsn('smtp', 'postal.test')));
    }

    private function readClientFromTransport(PostalApiTransport $transport): Client
    {
        $property = new \ReflectionProperty(PostalApiTransport::class, 'client');
        $property->setAccessible(true);

        $client = $property->getValue($transport);
        $this->assertInstanceOf(Client::class, $client);

        return $client;
    }
}
