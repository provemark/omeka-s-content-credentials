<?php

declare(strict_types=1);

namespace ContentCredentials\Tests\Support;

use RuntimeException;

/**
 * The oracle of SPEC-002: vendor/bin/c2pa-verify on the same bytes, and the
 * fixtures it is run on.
 */
final class VerifierCli
{
    public static function fixture(string $name): string
    {
        return dirname(__DIR__).'/Fixtures/c2pa/'.$name;
    }

    /**
     * The CLI's report for a file, decoded.
     *
     * @return array<mixed>
     */
    public static function report(string $path, ?string $settingsFile = null): array
    {
        $command = [PHP_BINARY, dirname(__DIR__, 2).'/vendor/bin/c2pa-verify', $path];
        if ($settingsFile !== null) {
            array_push($command, '--settings', $settingsFile);
        }
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (! is_resource($process)) {
            throw new RuntimeException('c2pa-verify did not start');
        }
        $out = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        $report = json_decode($out, true);

        return is_array($report) ? $report : throw new RuntimeException("c2pa-verify gave no JSON for {$path}");
    }

    /**
     * The `code` of every entry of the report's validation_status, in order.
     *
     * @param  array<mixed>  $report
     * @return list<mixed>
     */
    public static function codes(array $report): array
    {
        $codes = [];
        foreach (is_array($report['validation_status'] ?? null) ? $report['validation_status'] : [] as $status) {
            $codes[] = is_array($status) ? ($status['code'] ?? null) : null;
        }

        return $codes;
    }

    /**
     * The active manifest's signature_info, or [] when there is none.
     *
     * @param  array<mixed>  $report
     * @return array<mixed>
     */
    public static function signature(array $report): array
    {
        $active = $report['active_manifest'] ?? null;
        $manifests = $report['manifests'] ?? null;
        $manifest = is_string($active) && is_array($manifests) ? ($manifests[$active] ?? null) : null;
        $info = is_array($manifest) ? ($manifest['signature_info'] ?? null) : null;

        return is_array($info) ? $info : [];
    }

    /**
     * A copy of fixture-signed.jpg with the byte 64 bytes before the end
     * flipped (SPEC-002 AC4), in the system's temporary directory; the
     * caller deletes it.
     */
    public static function alteredJpeg(): string
    {
        $bytes = (string) file_get_contents(self::fixture('fixture-signed.jpg'));
        $at = strlen($bytes) - 64;
        $bytes[$at] = chr(ord($bytes[$at]) ^ 0xFF);
        $path = sys_get_temp_dir().'/spec002-altered-'.getmypid().'.jpg';
        file_put_contents($path, $bytes);

        return $path;
    }

    /**
     * A trust-settings file built the way the verifier's
     * docs/trust-settings.md builds one (SPEC-004's oracle), from the
     * bundled lists in trust/, or with the given anchors only. Written to
     * tests/tmp/.
     *
     * @param  list<array{string, string}>|null  $anchors  [path, trust kind] pairs; null: the bundled lists
     */
    public static function settingsFile(bool $digicert = true, ?array $anchors = null): string
    {
        $trust = dirname(__DIR__, 2).'/trust/';
        $anchors ??= array_merge(
            [[$trust.'C2PA-TRUST-LIST.pem', 'manifest'], [$trust.'C2PA-TSA-TRUST-LIST.pem', 'tsa']],
            $digicert ? [[$trust.'DigiCertTrustedRootG4.crt.pem', 'tsa']] : [],
        );
        $entries = array_map(static fn (array $anchor): array => ['trust_anchors' => (string) file_get_contents($anchor[0]), 'trust_kind' => $anchor[1]], $anchors);
        $json = json_encode(['verify' => ['verify_trust' => true], 'trust' => ['anchors' => $entries]], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $dir = dirname(__DIR__).'/tmp';
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $file = $dir.'/settings-'.hash('sha256', $json).'.json';
        file_put_contents($file, $json);

        return $file;
    }
}
