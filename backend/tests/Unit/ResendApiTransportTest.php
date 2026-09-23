<?php

namespace Tests\Unit;

use App\Mail\Transport\ResendApiTransport;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class ResendApiTransportTest extends TestCase
{
    #[Test]
    public function it_sends_laravel_mail_through_resends_https_api(): void
    {
        Http::fake([
            'api.resend.com/emails' => Http::response(['id' => 'resend-message-id'], 200),
        ]);

        $transport = new ResendApiTransport(app(Factory::class), 'test-api-key');
        $message = (new Email)
            ->from(new Address('no-reply@nutriscope.live', 'NutriScope'))
            ->to('recipient@example.com')
            ->subject('Verify your email')
            ->text('Verification code: 123456');

        $sent = $transport->send($message);

        $this->assertSame('resend-message-id', $sent?->getMessageId());
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.resend.com/emails'
            && $request->hasHeader('Authorization', 'Bearer test-api-key')
            && $request['from'] === (new Address('no-reply@nutriscope.live', 'NutriScope'))->toString()
            && $request['to'] === ['recipient@example.com']
            && $request['subject'] === 'Verify your email'
            && $request['text'] === 'Verification code: 123456');
    }
}
