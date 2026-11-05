<?php

declare(strict_types=1);

// Measurement only (M5.2, notes/step-24-evidence-in-omeka.md), never shipped.
// Usage: php measure.php run <label> | stats | acl | headers | big
// Drives EvidenceRouteProbe in the PHP 8.3 test environment.
require dirname(__DIR__, 4).'/vendor/autoload.php';

use ContentCredentials\Tests\Integration\Support\OmekaInstallation;

$o = OmekaInstallation::php83();
$o->logIn();
const BASE = 'http://127.0.0.1:8088';

/** A small client that keeps headers: [status, headers (lower-case name => list), body]. */
final class Client
{
    private string $jar;

    public function __construct()
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'm52');
    }

    /** @return array{int, array<string, list<string>>, string} */
    public function request(string $path, ?array $post = null): array
    {
        $headers = [];
        $c = curl_init(BASE.$path);
        curl_setopt_array($c, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_HEADERFUNCTION => static function ($c, string $line) use (&$headers): int {
                if (str_contains($line, ':')) {
                    [$k, $v] = explode(':', $line, 2);
                    $headers[strtolower(trim($k))][] = trim($v);
                }

                return strlen($line);
            },
        ] + ($post === null ? [] : [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($post)]));
        $body = (string) curl_exec($c);

        return [curl_getinfo($c, CURLINFO_RESPONSE_CODE), $headers, $body];
    }

    public function logIn(string $email, string $password): void
    {
        [, , $page] = $this->request('/login');
        preg_match('#name="loginform_csrf" value="([^"]+)"#', $page, $m);
        [$status] = $this->request('/login', ['email' => $email, 'password' => $password, 'loginform_csrf' => $m[1] ?? '', 'submit' => 'Log in']);
        if ($status !== 302) {
            throw new RuntimeException("login failed for {$email}: {$status}");
        }
    }

    public function configure(array $fields): int
    {
        [, , $page] = $this->request('/admin/module/configure?id=EvidenceRouteProbe');
        preg_match('#name="module_EvidenceRouteProbe_configure_csrf" value="([^"]+)"#', $page, $m);

        return $this->request('/admin/module/configure?id=EvidenceRouteProbe', $fields + ['module_EvidenceRouteProbe_configure_csrf' => $m[1] ?? ''])[0];
    }
}

$admin = new Client;
$admin->logIn(OmekaInstallation::EMAIL, OmekaInstallation::PASSWORD);
$stats = function () use ($o): void {
    $o->sql('ANALYZE TABLE evidence_route_probe');
    echo $o->sql("SELECT CONCAT('rows=', COUNT(*), ' media=', COUNT(DISTINCT media_id), ' document_bytes=', SUM(LENGTH(document)), ' max=', MAX(LENGTH(document)), ' sha_equal=', SUM(SHA2(document, 256) = sha256)) FROM evidence_route_probe"), "\n";
    echo $o->sql("SELECT CONCAT('data_length=', DATA_LENGTH, ' index_length=', INDEX_LENGTH, ' row_format=', ROW_FORMAT) FROM information_schema.TABLES WHERE TABLE_SCHEMA = 'omeka' AND TABLE_NAME = 'evidence_route_probe'"), "\n";
    echo trim(shell_exec('docker compose exec -T db ls -l /var/lib/mysql/omeka/evidence_route_probe.ibd') ?? ''), "\n";
};
$status = fn (array $r): string => $r[0].(in_array($r[0], [301, 302, 303], true) ? '->'.parse_url($r[1]['location'][0] ?? '', PHP_URL_PATH) : '');

switch ($argv[1] ?? '') {
    case 'run':
        $before = (int) $o->sql('SELECT COALESCE(MAX(id), 0) FROM job');
        $admin->configure(['args' => json_encode(['label' => $argv[2] ?? 'run'])]);
        $job = (int) $o->sql('SELECT MAX(id) FROM job');
        $job > $before || throw new RuntimeException('no job dispatched');
        while (! in_array($s = $o->sql("SELECT status FROM job WHERE id = {$job}"), ['completed', 'error', 'stopped'], true)) {
            usleep(500000);
        }
        echo "job {$job} {$s}\n", $o->rootShell('grep END /tmp/evidence-probe.log | tail -1');
        $stats();
        break;

    case 'stats':
        $stats();
        break;

    case 'acl':
        // P: a public media item with documents; Q: a private item's media, made here.
        $p = (int) $o->sql('SELECT MIN(e.media_id) FROM evidence_route_probe e JOIN media m ON m.id = e.media_id JOIN resource rm ON rm.id = m.id JOIN resource ri ON ri.id = m.item_id WHERE rm.is_public = 1 AND ri.is_public = 1');
        $o->startFixtureServer();
        $item = (int) ($argv[2] ?? 0) ?: OmekaInstallation::idFrom($o->api('POST', '/api/items', ['o:is_public' => false, 'o:media' => [['o:ingester' => 'url', 'o:is_public' => false, 'ingest_url' => 'http://127.0.0.1:'.OmekaInstallation::FIXTURE_PORT.'/fixture-signed.jpg']]]));
        $q = $o->mediaIdsOf($item)[0];
        $crafted = "{\n    \"active_manifest\": \"<script>alert('m52')</script>\"\n}";
        $admin->configure(['insert' => json_encode(['media_id' => $q, 'document_b64' => base64_encode($crafted)])]);
        echo "P={$p} (public), Q={$q} (private item {$item}, owned by the global admin)\n";
        $users = ['anonymous' => null, 'global_admin' => [OmekaInstallation::EMAIL, OmekaInstallation::PASSWORD]];
        $made = [];
        foreach (['site_admin', 'editor', 'reviewer', 'author', 'researcher'] as $role) {
            $email = "m52-{$role}-".getmypid().'@example.com';
            $id = OmekaInstallation::idFrom($o->api('POST', '/api/users', ['o:email' => $email, 'o:name' => $role, 'o:role' => $role, 'o:is_active' => true]));
            $o->sql("UPDATE user SET password_hash = '".password_hash('m52-test-only', PASSWORD_DEFAULT)."' WHERE id = {$id}");
            $users[$role] = [$email, 'm52-test-only'];
            $made[] = $id;
        }
        foreach (['none', 'all'] as $mode) {
            $admin->configure(['acl' => $mode]);
            sleep(3);   // opcache, as in step 21
            echo "--- ACL mode {$mode}\n";
            foreach ($users as $role => $login) {
                $u = new Client;
                if ($login !== null) {
                    $u->logIn(...$login);
                }
                $cells = [];
                foreach (['P' => $p, 'Q' => $q] as $name => $id) {
                    $page = $u->request("/admin/media/{$id}");
                    $link = preg_match('#data-allowed="(\w+)"><a href="([^"]+)">EVIDENCE-PROBE-LINK#', $page[2], $m) ? " link({$m[1]})" : '';
                    $cells[] = "{$name}: media ".$status($page).$link
                        .', raw '.$status($u->request("/admin/evidence-probe/{$id}/raw"))
                        .', show '.$status($u->request("/admin/evidence-probe/{$id}/show"))
                        .', download '.$status($u->request("/admin/evidence-probe/{$id}/download"));
                }
                printf("%-12s %s\n", $role, implode(' | ', $cells));
            }
        }
        $admin->configure(['acl' => 'none']);
        foreach ($made as $id) {
            $o->sql("DELETE FROM user WHERE id = {$id}");
        }
        echo "users deleted; item {$item} kept for 'headers', delete it afterwards\n";
        break;

    case 'headers':
        $p = (int) $o->sql('SELECT MIN(e.media_id) FROM evidence_route_probe e JOIN media m ON m.id = e.media_id JOIN resource rm ON rm.id = m.id JOIN resource ri ON ri.id = m.item_id WHERE rm.is_public = 1 AND ri.is_public = 1');
        $q = (int) $o->sql("SELECT media_id FROM evidence_route_probe WHERE document LIKE '%m52%' ORDER BY id DESC LIMIT 1");
        [$s, $h, $body] = $admin->request("/admin/evidence-probe/{$p}/download");
        echo "download P={$p}: {$s}\n";
        foreach ($h as $k => $v) {
            echo "  {$k}: ".implode(' | ', $v)."\n";
        }
        $row = $o->sql("SELECT sha256 FROM evidence_route_probe WHERE media_id = {$p} ORDER BY id DESC LIMIT 1");
        printf("  body %d bytes, sha256 %s; stored %s; equal %s\n", strlen($body), hash('sha256', $body), $row, hash('sha256', $body) === $row ? 'yes' : 'NO');
        [$s, $h, $body] = $admin->request("/admin/evidence-probe/{$q}/show");
        printf("show Q=%d: %d, content-type %s; raw <script> %s; escaped %s; in the admin layout %s\n", $q, $s, $h['content-type'][0] ?? '-', str_contains($body, "<script>alert('m52')") ? 'PRESENT' : 'absent', str_contains($body, '&lt;script&gt;alert(&#039;m52&#039;)') ? 'present' : 'absent', str_contains($body, 'id="user-bar"') || str_contains($body, 'class="o-icon-') ? 'yes' : 'no');
        [$s, $h, $body] = $admin->request("/admin/evidence-probe/{$q}/raw");
        printf("raw Q: %d, content-type %s, x-content-type-options %s, body as stored %s\n", $s, implode(' | ', $h['content-type'] ?? []), $h['x-content-type-options'][0] ?? '-', str_contains($body, "<script>alert('m52')") ? 'yes' : 'no');
        [$s] = $admin->request('/admin/evidence-probe/999999999/download');
        echo "download of a media id that does not exist: {$s}\n";
        [$s, , $body] = $admin->request('/admin/evidence-probe/abc/download');
        echo "download with a non-numeric id: {$s}\n";
        break;

    case 'big':
        $p = (int) $o->sql('SELECT MIN(e.media_id) FROM evidence_route_probe e JOIN media m ON m.id = e.media_id JOIN resource rm ON rm.id = m.id JOIN resource ri ON ri.id = m.item_id WHERE rm.is_public = 1 AND ri.is_public = 1');
        $chars = implode('', array_map('chr', range(0x20, 0x7E)))."\n";
        $document = substr(str_repeat($chars, intdiv(5 * 1048576, strlen($chars)) + 1), 0, 5 * 1048576);
        $s = $admin->configure(['insert' => json_encode(['media_id' => $p, 'document_b64' => base64_encode($document)])]);
        [$st, $h, $body] = $admin->request("/admin/evidence-probe/{$p}/download");
        printf("configure %d; 5 MiB document: download %d, %d bytes, identical %s\n", $s, $st, strlen($body), $body === $document ? 'yes' : 'NO');
        $o->sql("DELETE FROM evidence_route_probe WHERE media_id = {$p} AND LENGTH(document) = ".strlen($document));
        break;

    default:
        fwrite(STDERR, "usage: measure.php run <label> | stats | acl | headers | big\n");
        exit(1);
}
