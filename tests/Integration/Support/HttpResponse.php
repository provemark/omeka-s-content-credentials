<?php

declare(strict_types=1);

namespace ContentCredentials\Tests\Integration\Support;

/**
 * One HTTP answer: its status, its body, its Location header ('' when
 * none), and every header by its lower-case name (SPEC-006 AC8).
 */
final readonly class HttpResponse
{
    /**
     * @param  array<string, list<string>>  $headers
     */
    public function __construct(
        public int $status,
        public string $body,
        public string $location,
        public array $headers = [],
    ) {}
}
