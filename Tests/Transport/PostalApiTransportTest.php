<?php

declare(strict_types=1);

namespace Kyle\PostalMailer\Tests\Transport;

use Kyle\PostalMailer\Transport\PostalApiTransport;
use PHPUnit\Framework\TestCase;
use Postal\Client;
use Postal\MessagesService;
use Postal\Send\Message;
use Postal\Send\Result;
use Postal\SendService;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\RuntimeException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final class PostalApiTransportTest extends TestCase
{
    public function testToStringReturnsPostalApiScheme(): void
    {
        $transport = new PostalApiTransport($this->createClientStub(new SendServiceStub()));

        $this->assertSame('postal+api://', (string) $transport);
    }

    public function testSendBuildsPostalMessageAndSetsMessageId(): void
    {
        $result = new Result([
            'message_id' => 'postal-id',
            'messages' => [],
        ]);
        $sendService = new SendServiceStub($result);
        $transport = new PostalApiTransport($this->createClientStub($sendService));

        $email = (new Email())
            ->from('from@example.com')
            ->to('to1@example.com', 'to2@example.com')
            ->cc('cc@example.com')
            ->bcc('bcc@example.com')
            ->subject('Greetings')
            ->text('Plain body')
            ->html('<p>HTML body</p>');
        $email->replyTo('reply@example.com');
        $email->getHeaders()->addTextHeader('X-Custom', 'custom-value');
        $email->attach('attachment body', 'notes.txt', 'text/plain');

        $envelope = new Envelope(new Address('sender@example.com'), [
            new Address('to1@example.com'),
            new Address('to2@example.com'),
        ]);

        $sentMessage = $transport->send($email, $envelope);

        $this->assertSame('postal-id', $sentMessage->getMessageId());

        $postalMessage = $sendService->lastMessage();

        $this->assertSame('sender@example.com', $postalMessage->from);
        $this->assertSame(['to1@example.com', 'to2@example.com'], $postalMessage->to);
        $this->assertSame(['cc@example.com'], $postalMessage->cc);
        $this->assertSame(['bcc@example.com'], $postalMessage->bcc);
        $this->assertSame('Greetings', $postalMessage->subject);
        $this->assertSame('Plain body', $postalMessage->plain_body);
        $this->assertSame('<p>HTML body</p>', $postalMessage->html_body);
        $this->assertSame('reply@example.com', $postalMessage->reply_to);

        $this->assertNotNull($postalMessage->headers);
        $this->assertArrayHasKey('X-Custom', $postalMessage->headers);
        $this->assertSame('custom-value', $postalMessage->headers['X-Custom']);
        $this->assertArrayNotHasKey('Reply-To', $postalMessage->headers);

        $this->assertCount(1, $postalMessage->attachments);
        $attachment = $postalMessage->attachments[0];
        $this->assertSame('notes.txt', $attachment['name']);
        $this->assertSame('text/plain', $attachment['content_type']);
        $this->assertSame(base64_encode('attachment body'), $attachment['data']);
    }

    public function testSendWrapsClientExceptions(): void
    {
        $sendService = new SendServiceStub(null, new \RuntimeException('boom'));
        $transport = new PostalApiTransport($this->createClientStub($sendService));

        $email = (new Email())
            ->from('from@example.com')
            ->to('to@example.com')
            ->subject('Fails on postal send')
            ->text('Exception trigger body');
        $envelope = new Envelope(new Address('from@example.com'), [new Address('to@example.com')]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to send message with the "Kyle\PostalMailer\Transport\PostalApiTransport" transport: boom');

        $transport->send($email, $envelope);
    }

    public function testSendHandlesMissingSubjectAndAttachmentFilename(): void
    {
        $sendService = new SendServiceStub();
        $transport = new PostalApiTransport($this->createClientStub($sendService));

        $email = (new Email())
            ->from('from@example.com')
            ->to('to@example.com')
            ->text('Body without subject');
        $email->attach('attachment body', null, 'text/plain');

        $transport->send($email);

        $postalMessage = $sendService->lastMessage();

        $this->assertNull($postalMessage->subject);
        $this->assertCount(1, $postalMessage->attachments);
        $this->assertSame('attachment-1', $postalMessage->attachments[0]['name']);
    }

    public function testSendSupportsResourceAttachments(): void
    {
        $sendService = new SendServiceStub();
        $transport = new PostalApiTransport($this->createClientStub($sendService));

        $resource = fopen('php://temp', 'r+');
        try {
            fwrite($resource, 'resource body');
            rewind($resource);

            $email = (new Email())
                ->from('from@example.com')
                ->to('to@example.com')
                ->subject('Has resource attachment')
                ->text('Body');
            $email->attach($resource, 'resource.txt', 'text/plain');

            $transport->send($email);
        } finally {
            fclose($resource);
        }

        $postalMessage = $sendService->lastMessage();

        $this->assertCount(1, $postalMessage->attachments);
        $this->assertSame(base64_encode('resource body'), $postalMessage->attachments[0]['data']);
    }

    /**
     * @param SendServiceStub $sendService
     */
    private function createClientStub(SendServiceStub $sendService): Client
    {
        return new class($sendService) extends Client {
            public function __construct(private SendServiceStub $sendService)
            {
                $this->send = $sendService;
                $this->messages = new class extends MessagesService {
                    public function __construct() {}
                };
            }
        };
    }
}

/**
 * @internal helper for tests
 */
final class SendServiceStub extends SendService
{
    /**
     * @var list<Message>
     */
    private array $messages = [];

    public function __construct(private ?Result $result = null, private ?\Throwable $exception = null) {}

    public function message(Message $message): Result
    {
        $this->messages[] = $message;

        if ($this->exception) {
            throw $this->exception;
        }

        if ($this->result === null) {
            return new Result([
                'message_id' => 'generated-id',
                'messages' => [],
            ]);
        }

        return $this->result;
    }

    public function lastMessage(): Message
    {
        if ($this->messages === []) {
            throw new \RuntimeException('No message was captured.');
        }

        return $this->messages[array_key_last($this->messages)];
    }
}
