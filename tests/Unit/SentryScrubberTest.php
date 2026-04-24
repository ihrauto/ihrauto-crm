<?php

namespace Tests\Unit;

use App\Support\SentryScrubber;
use PHPUnit\Framework\TestCase;
use Sentry\Event;

class SentryScrubberTest extends TestCase
{
    public function test_it_masks_sensitive_request_data_keys(): void
    {
        $event = Event::createEvent();
        $event->setRequest([
            'data' => [
                'email' => 'user@example.com',
                'password' => 'super-secret',
                'current_password' => 'old-secret',
                'iban' => 'CH00 SECRETIBAN',
                'phone' => '+15551234567',
                'nested' => [
                    'api_token' => 'tk_leak',
                    'benign' => 'ok',
                ],
            ],
            'headers' => [
                'Authorization' => 'Bearer eyJleHA…',
                'X-API-Key' => 'abcdef',
                'Accept' => 'application/json',
            ],
            'cookies' => [
                'session' => 'xxx',
                'remember_token' => 'yyy',
            ],
        ]);

        SentryScrubber::handle($event, null);
        $request = $event->getRequest();

        $this->assertSame('[filtered]', $request['data']['password']);
        $this->assertSame('[filtered]', $request['data']['current_password']);
        $this->assertSame('[filtered]', $request['data']['iban']);
        $this->assertSame('[filtered]', $request['data']['phone']);
        $this->assertSame('[filtered]', $request['data']['nested']['api_token']);
        $this->assertSame('ok', $request['data']['nested']['benign']);

        $this->assertSame('[filtered]', $request['headers']['Authorization']);
        $this->assertSame('[filtered]', $request['headers']['X-API-Key']);
        $this->assertSame('application/json', $request['headers']['Accept']);

        $this->assertSame('[filtered]', $request['cookies']['remember_token']);
        // 'session' is not on the list, left intact (session cookie is marked
        // HttpOnly + Secure elsewhere; Sentry's send_default_pii already
        // filters cookies unless explicitly enabled).
        $this->assertSame('xxx', $request['cookies']['session']);

        // Non-sensitive body fields stay visible.
        $this->assertSame('user@example.com', $request['data']['email']);
    }

    public function test_it_tolerates_empty_request(): void
    {
        $event = Event::createEvent();
        $event->setRequest([]);

        $result = SentryScrubber::handle($event, null);

        $this->assertNotNull($result);
        $this->assertSame([], $result->getRequest());
    }
}
