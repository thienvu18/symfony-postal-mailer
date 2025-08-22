<?php

namespace Kyle\PostalMailer\Transport;

use Postal\Client;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

final class PostalTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        $scheme = $dsn->getScheme();

        if (!\in_array($scheme, $this->getSupportedSchemes(), true)) {
            throw new UnsupportedSchemeException($dsn, 'postal', $this->getSupportedSchemes());
        }

        $host = $dsn->getHost();
        $port = $dsn->getPort();
        $apiToken = $this->getPassword($dsn);

        $client = new Client($host . ($port ? ':' . $port : ''), $apiToken);

        return new PostalApiTransport($client, $this->dispatcher, $this->logger);
    }

    protected function getSupportedSchemes(): array
    {
        return ['postal', 'postal+api'];
    }
}
