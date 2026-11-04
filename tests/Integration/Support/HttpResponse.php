<?php

declare(strict_types=1);

namespace ContentCredentials\Tests\Integration\Support;

/** One HTTP answer: its status, its body, and its Location header ('' when none). */
final readonly class HttpResponse
{
    public function __construct(
        public int $status,
        public string $body,
        public string $location,
    ) {}
}
