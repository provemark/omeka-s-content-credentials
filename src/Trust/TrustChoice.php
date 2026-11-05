<?php

declare(strict_types=1);

namespace ContentCredentials\Trust;

use Provemark\C2paVerifier\Trust\TrustException;
use Provemark\C2paVerifier\Trust\TrustSettings;
use RuntimeException;
use Throwable;

/**
 * Which settings a check uses (SPEC-004, the table in Behavior): custom
 * settings when there are any, else the bundled lists with or without the
 * DigiCert root. Settings that cannot be built mean none, never an error:
 * a check without trust settings still verifies, it just cannot reach
 * `Trusted` (AC6). Needs the verifier loadable.
 */
final class TrustChoice
{
    public const CUSTOM = 'custom';

    public const MAX_CUSTOM_BYTES = 1048576;

    public static function build(?string $custom, bool $digicert, string $trustDirectory): BuiltTrust
    {
        $isCustom = $custom !== null && trim($custom) !== '';
        try {
            $json = $isCustom ? $custom : self::bundledJson($digicert, $trustDirectory);
        } catch (RuntimeException $e) {
            return BuiltTrust::without($e->getMessage());
        }
        try {
            return new BuiltTrust(TrustSettings::fromJson($json), $isCustom ? self::CUSTOM : BundledLists::source($digicert), null, hash('sha256', $json));
        } catch (TrustException $e) {
            return BuiltTrust::without($e->getMessage());
        } catch (Throwable) {
            return BuiltTrust::without('the trust settings could not be built');
        }
    }

    /**
     * The settings JSON of the bundled lists, built as the verifier's
     * docs/trust-settings.md builds it.
     *
     * @throws RuntimeException when a bundled file cannot be read
     */
    public static function bundledJson(bool $digicert, string $trustDirectory): string
    {
        $files = [[BundledLists::C2PA, 'manifest'], [BundledLists::TSA, 'tsa']];
        if ($digicert) {
            $files[] = [BundledLists::DIGICERT, 'tsa'];
        }
        $anchors = [];
        foreach ($files as [$name, $kind]) {
            $path = $trustDirectory.'/'.$name;
            $pem = is_file($path) && is_readable($path) ? @file_get_contents($path) : false;
            if ($pem === false) {
                throw new RuntimeException('a bundled trust list could not be read ('.$name.')');
            }
            $anchors[] = ['trust_anchors' => $pem, 'trust_kind' => $kind];
        }

        return json_encode(['verify' => ['verify_trust' => true], 'trust' => ['anchors' => $anchors]], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Why custom settings cannot be saved, or null when they can (an empty
     * text can: it means the bundled lists). AC5.
     */
    public static function refusal(string $custom): ?string
    {
        if (strlen($custom) > self::MAX_CUSTOM_BYTES) {
            return 'larger than 1 MiB';
        }
        if (trim($custom) === '') {
            return null;
        }
        try {
            TrustSettings::fromJson($custom);

            return null;
        } catch (TrustException $e) {
            return $e->getMessage();
        } catch (Throwable) {
            return 'the trust settings could not be read';
        }
    }
}
