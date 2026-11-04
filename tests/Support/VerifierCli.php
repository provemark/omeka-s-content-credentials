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
    public static function report(string $path): array
    {
        $process = proc_open([PHP_BINARY, dirname(__DIR__, 2).'/vendor/bin/c2pa-verify', $path], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
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
}
