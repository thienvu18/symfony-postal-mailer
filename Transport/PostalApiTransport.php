<?php

namespace Kyle\PostalMailer\Transport;

use Postal\Client;
use Postal\Send\Message;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\RuntimeException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;

final class PostalApiTransport extends AbstractTransport
{
    public function __construct(
        protected ?Client $client = null,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($dispatcher, $logger);
    }

    public function __toString(): string
    {
        return \sprintf('postal+api://%s', '');
    }

    protected function doSend(SentMessage $message): void
    {
        try {
            $email = MessageConverter::toEmail($message->getOriginalMessage());
            $envelope = $message->getEnvelope();

            $postalSendMessage = $this->getMessage($email, $envelope);
            $result = $this->client->send->message($postalSendMessage);

            $message->setMessageId($result->message_id);
        } catch (\Exception $e) {
            throw new RuntimeException(\sprintf('Unable to send message with the "%s" transport: ', __CLASS__) . $e->getMessage(), 0, $e);
        }
    }

    private function getMessage(Email $email, Envelope $envelope): Message
    {
        $message = new Message;

        $sender = $envelope->getSender() ?? $email->getFrom()[0] ?? null;
        if ($sender instanceof Address) {
            $message->from($sender->toString());
        }
        foreach ($email->getTo() as $address) {
            $message->to($address->toString());
        }
        if (null !== $email->getSubject()) {
            $message->subject($email->getSubject());
        }

        foreach ($email->getCc() as $address) {
            $message->cc($address->toString());
        }
        if ($emails = $email->getBcc()) {
            foreach ($emails as $address) {
                $message->bcc($address->toString());
            }
        }
        if ($email->getTextBody()) {
            $message->plainBody($email->getTextBody());
        }
        if ($email->getHtmlBody()) {
            $message->htmlBody($email->getHtmlBody());
        }
        foreach ($email->getAttachments() as $index => $attachment) {
            $filename = $attachment->getFilename() ?? \sprintf('attachment-%d', $index + 1);
            $content = $attachment->getBody();

            if (\is_resource($content)) {
                $content = stream_get_contents($content);
            }

            if (!\is_string($content)) {
                throw new RuntimeException('Unable to access attachment body as string.');
            }

            $message->attach($filename, $attachment->getContentType(), $content);
        }
        foreach ($this->getCustomHeaders($email) as $header) {
            $message->header($header['key'], $header['value']);
        }
        if ($emails = $email->getReplyTo()) {
            $message->replyTo($emails[0]->toString());
        }

        return $message;
    }

    private function getCustomHeaders(Email $email): array
    {
        $headers = [];
        $headersToBypass = ['from', 'to', 'cc', 'bcc', 'subject', 'content-type', 'sender', 'reply-to'];
        foreach ($email->getHeaders()->all() as $name => $header) {
            if (\in_array($name, $headersToBypass, true)) {
                continue;
            }

            $headers[] = [
                'key' => $header->getName(),
                'value' => $header->getBodyAsString(),
            ];
        }

        return $headers;
    }
}
