<?php

declare(strict_types=1);

namespace ContentCredentials\Trust;

use DateTimeImmutable;
use DateTimeZone;

/**
 * The record of the bundled trust lists (ADR-0004, SPEC-004 AC1, AC9):
 * which files are in trust/, their hashes, the upstream commit and its
 * date, and the date the copy was last compared with upstream. A unit test
 * checks the files against this record.
 */
final class BundledLists
{
    public const C2PA = 'C2PA-TRUST-LIST.pem';

    public const TSA = 'C2PA-TSA-TRUST-LIST.pem';

    public const DIGICERT = 'DigiCertTrustedRootG4.crt.pem';

    public const FILES = [
        self::C2PA => '148c1c167e98dc909a0d5793d2feee1022aa60fef5c83a0d6b08f67f4446bc37',
        self::TSA => 'eca8347ee71e31d226e00c85b15aca356116fefe6c26ed8c0ee1f954115b1990',
        self::DIGICERT => 'ce7d6b44f5d510391be98c8d76b18709400a30cd87659bfebe1c6f97ff5181ee',
    ];

    public const DIGICERT_FINGERPRINT = '55:2F:7B:DC:F1:A7:AF:9E:6C:E6:72:01:7F:4F:12:AB:F7:72:40:C7:8E:76:1A:C2:03:D1:D9:D2:0A:C8:99:88';

    /** The upstream commit that last changed the two C2PA lists. */
    public const COMMIT = '64b3f9c';

    /** That commit's date: the list date shown and stored in `trust`. */
    public const LIST_DATE = '2026-11-04';

    /** The day the copy was last compared with upstream. */
    public const COMPARED = '2026-11-06';

    public const STALE_AFTER_DAYS = 183;

    public static function isStale(DateTimeImmutable $now): bool
    {
        $compared = new DateTimeImmutable(self::COMPARED.'T00:00:00', new DateTimeZone('UTC'));

        return $now >= $compared->modify('+'.self::STALE_AFTER_DAYS.' days');
    }

    /** The `trust` value of a check with the bundled lists. */
    public static function source(bool $digicert): string
    {
        return 'c2pa-'.self::LIST_DATE.($digicert ? '+digicert' : '');
    }
}
