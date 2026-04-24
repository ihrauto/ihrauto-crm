<?php

namespace App\Support;

use Sentry\Event;
use Sentry\EventHint;

/**
 * Security review L-9: scrub sensitive request fields before an event
 * reaches Sentry.
 *
 * Laravel's own LogManager masks `password` and `password_confirmation`
 * but nothing strips the long-tail of secrets and PII that can land in
 * request bodies during an error — invite tokens, bearer headers, IBAN,
 * phone numbers, customer emails, API keys. Sentry sees everything unless
 * we preprocess. `send_default_pii` being `false` by default takes care
 * of some headers (cookies, auth), but it does NOT filter form fields.
 *
 * Wired in config/sentry.php as the `before_send` callback.
 */
class SentryScrubber
{
    /**
     * Field names whose values must be masked on the way out. Lowercased
     * exact match against both request-data keys and header names.
     */
    private const SENSITIVE_KEYS = [
        // authentication
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'remember_token',
        'invite_token',
        'api_token',
        'api_key',
        'bearer',
        'authorization',
        'cookie',
        'set-cookie',
        'x-api-key',
        'x-csrf-token',
        'x-xsrf-token',
        'xsrf-token',
        // banking / payment
        'iban',
        'bank_name',
        'account_holder',
        'bank_account',
        'card_number',
        'cvv',
        // miscellaneous PII that leaks in onboarding posts
        'phone',
        'invoice_phone',
    ];

    private const MASK = '[filtered]';

    /**
     * Entry point wired as Sentry's `before_send` callback. Declared static
     * so it can be referenced as `[self::class, 'handle']` in config —
     * config:cache serialises that shape, while a closure would break
     * caching.
     */
    public static function handle(Event $event, ?EventHint $hint): ?Event
    {
        $request = $event->getRequest();

        if (is_array($request) && $request !== []) {
            if (isset($request['data']) && is_array($request['data'])) {
                $request['data'] = self::scrubArray($request['data']);
            }

            if (isset($request['headers']) && is_array($request['headers'])) {
                $request['headers'] = self::scrubArray($request['headers']);
            }

            if (isset($request['cookies']) && is_array($request['cookies'])) {
                $request['cookies'] = self::scrubArray($request['cookies']);
            }

            $event->setRequest($request);
        }

        return $event;
    }

    /**
     * Recursively replace values whose key (lower-cased) matches the
     * sensitive list. Non-scalar values are descended into.
     *
     * @param  array<mixed, mixed>  $data
     * @return array<mixed, mixed>
     */
    private static function scrubArray(array $data): array
    {
        foreach ($data as $key => $value) {
            $needle = is_string($key) ? strtolower($key) : null;

            if ($needle !== null && in_array($needle, self::SENSITIVE_KEYS, true)) {
                $data[$key] = self::MASK;

                continue;
            }

            if (is_array($value)) {
                $data[$key] = self::scrubArray($value);
            }
        }

        return $data;
    }
}
