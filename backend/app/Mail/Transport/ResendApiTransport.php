<?php

namespace App\Mail\Transport;

use Illuminate\Http\Client\Factory;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Throwable;

class ResendApiTransport extends AbstractTransport
{
    public function __construct(
        private readonly Factory $http,
        private readonly string $apiKey,
        private readonly string $endpoint = 'https://api.resend.com/emails',
        private readonly int $timeout = 10,
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return 'resend-api';
    }

    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();

        if (! $email instanceof Email) {
            throw new TransportException('The Resend API transport requires an email message.');
        }

        $payload = array_filter([
            'from' => $this->stringifyAddresses($email->getFrom())[0] ?? null,
            'to' => $this->stringifyAddresses($email->getTo()),
            'cc' => $this->stringifyAddresses($email->getCc()),
            'bcc' => $this->stringifyAddresses($email->getBcc()),
            'reply_to' => $this->stringifyAddresses($email->getReplyTo()),
            'subject' => $email->getSubject() ?? '',
            'html' => $email->getHtmlBody(),
            'text' => $email->getTextBody(),
        ], static fn (mixed $value): bool => $value !== null && $value !== []);

        try {
            $response = $this->http
                ->withToken($this->apiKey)
                ->acceptJson()
                ->asJson()
                ->timeout($this->timeout)
                ->post($this->endpoint, $payload)
                ->throw();
        } catch (Throwable $exception) {
            throw new TransportException('Resend API delivery failed.', previous: $exception);
        }

        if (is_string($response->json('id'))) {
            $message->setMessageId($response->json('id'));
        }
    }
}
