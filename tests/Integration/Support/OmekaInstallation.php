<?php

declare(strict_types=1);

namespace ContentCredentials\Tests\Integration\Support;

use RuntimeException;

/**
 * One Omeka S installation of the Docker environment (tools/env/), driven as
 * its test administrator through the admin interface, and its database read
 * through `docker compose exec` (SPEC-001). It touches nothing outside the
 * compose project content-credentials-test.
 *
 * HTTP goes through PHP's own stream wrapper, with a cookie jar kept here, so
 * the tests need no extension beyond what PHP brings.
 */
final class OmekaInstallation
{
    public const EMAIL = 'admin@example.com';

    public const PASSWORD = 'test-only-admin-password';

    /** @var array<string, string> */
    private array $cookies = [];

    /** The repository as the containers see it (compose.yaml mounts it read-only). */
    public const MODULE_DIR = '/var/www/html/modules/ContentCredentials';

    /** The port of the fixture server inside the PHP 8.3 container (SPEC-002 AC2). */
    public const FIXTURE_PORT = 9000;

    private ?string $apiQuery = null;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $dbService,
        private readonly string $omekaService,
    ) {}

    public static function php83(): self
    {
        return new self('http://127.0.0.1:'.(getenv('CC_TEST_PORT') ?: '8088'), 'db', 'omeka');
    }

    public static function php82(): self
    {
        return new self('http://127.0.0.1:'.(getenv('CC_TEST_PORT_PHP82') ?: '8089'), 'db-php82', 'omeka-php82');
    }

    public function logIn(string $email = self::EMAIL, string $password = self::PASSWORD): void
    {
        $this->cookies = [];
        $page = $this->get('/login');
        $response = $this->post('/login', [
            'email' => $email,
            'password' => $password,
            'loginform_csrf' => self::field($page->body, 'loginform_csrf'),
            'submit' => 'Log in',
        ]);
        if ($response->status !== 302 || ! str_ends_with($response->location, '/admin')) {
            throw new RuntimeException("Logging in failed: HTTP {$response->status} to '{$response->location}'.");
        }
    }

    public function get(string $path): HttpResponse
    {
        return $this->request('GET', $path, []);
    }

    /**
     * @param  array<string, string>  $fields
     */
    public function post(string $path, array $fields): HttpResponse
    {
        return $this->request('POST', $path, $fields);
    }

    /**
     * Installs, activates, deactivates or uninstalls a module the way the
     * modules page does: the form of that action and module, with its CSRF
     * token. Uninstall is offered in a confirmation sidebar instead.
     */
    public function moduleAction(string $action, string $module): HttpResponse
    {
        $target = "/admin/module/{$action}?id={$module}";
        if ($action === 'uninstall') {
            $page = $this->get("/admin/module/uninstall-confirm?id={$module}");

            return $this->post($target, [
                'confirmform_csrf' => self::field(self::form($page->body, $target), 'confirmform_csrf'),
                'submit' => 'Confirm uninstall',
            ]);
        }
        $page = $this->get('/admin/module');

        return $this->post($target, ['csrf' => self::field(self::form($page->body, $target), 'csrf'), 'id' => '']);
    }

    /**
     * The `module` row of one module, or null.
     *
     * @return array{id: string, is_active: string, version: string}|null
     */
    public function moduleRow(string $module): ?array
    {
        $out = $this->sql('SELECT id, is_active, version FROM module WHERE id = '.self::quote($module));
        if ($out === '') {
            return null;
        }
        $cells = explode("\t", $out);
        if (count($cells) !== 3) {
            throw new RuntimeException("Unexpected module row: {$out}");
        }

        return ['id' => $cells[0], 'is_active' => $cells[1], 'version' => $cells[2]];
    }

    /**
     * The database as SPEC-001 compares it: the schema without
     * auto-increment values, the row count of every table but `session`,
     * every `setting` row, and the `module` table.
     */
    /**
     * @param  list<string>  $exceptTables  tables left out of the schema and the counts
     */
    public function snapshot(array $exceptTables = []): string
    {
        $ignore = array_map(static fn (string $table): string => '--ignore-table=omeka.'.$table, $exceptTables);
        $schema = (string) preg_replace('/ AUTO_INCREMENT=\d+/', '', self::run([
            'docker', 'compose', 'exec', '-T', $this->dbService,
            'mariadb-dump', '-uomeka', '-pomeka', '--no-data', '--skip-comments', '--skip-dump-date', ...$ignore, 'omeka',
        ]));
        $counts = [];
        foreach (explode("\n", $this->sql('SHOW TABLES')) as $table) {
            if ($table !== 'session' && ! in_array($table, $exceptTables, true)) {
                $counts[] = $table.' '.$this->sql('SELECT COUNT(*) FROM `'.$table.'`');
            }
        }

        return $schema."\n".implode("\n", $counts)."\n"
            .$this->sql('SELECT id, value FROM setting ORDER BY id')."\n"
            .$this->sql('SELECT id, is_active, version FROM module ORDER BY id');
    }

    /**
     * The messages the messenger shows on a page, as text.
     *
     * @return list<string>
     */
    public static function messages(string $html): array
    {
        if (preg_match_all('#<li class="(?:success|error|warning)">(.*?)</li>#s', $html, $m) === 0) {
            return [];
        }

        return array_map(static fn (string $raw): string => trim($raw), $m[1]);
    }

    /**
     * Adds an item with one uploaded file through the admin item form, as a
     * browser sends it, and returns the new item's id.
     */
    public function uploadThroughAdminForm(string $path, string $filename, string $mediaType): HttpResponse
    {
        $page = $this->get('/admin/item/add');

        return $this->postMultipart('/admin/item/add', [
            'csrf' => self::field(self::form($page->body, '/admin/item/add'), 'csrf'),
            'values_json' => '{}',
            'o:is_public' => '1',
            'o:media[0][o:ingester]' => 'upload',
            'o:media[0][file_index]' => '0',
        ], ['file[0]' => [$path, $filename, $mediaType]]);
    }

    /** The item id a redirect to /admin/item/<id> names. */
    public static function itemIdFrom(HttpResponse $response): int
    {
        if ($response->status !== 302 || preg_match('#/admin/item/(\d+)$#', $response->location, $m) !== 1) {
            throw new RuntimeException("Not a redirect to a new item: HTTP {$response->status} to '{$response->location}'.");
        }

        return (int) $m[1];
    }

    /** @return list<int> */
    public function mediaIdsOf(int $itemId): array
    {
        $out = $this->sql('SELECT id FROM media WHERE item_id = '.$itemId.' ORDER BY position, id');

        return $out === '' ? [] : array_map('intval', explode("\n", $out));
    }

    /**
     * A request to the REST API as the test administrator, with a key made
     * for this run; $data is sent as JSON, or as the multipart field "data"
     * when there are files.
     *
     * @param  array<mixed>|null  $data
     * @param  array<string, array{string, string, string}>  $files
     */
    public function api(string $method, string $path, ?array $data = null, array $files = []): HttpResponse
    {
        $this->apiQuery ??= $this->makeApiKey();
        $url = $path.(str_contains($path, '?') ? '&' : '?').$this->apiQuery;
        $json = $data === null ? '' : json_encode($data, JSON_THROW_ON_ERROR);
        if ($files !== []) {
            return $this->postMultipart($url, ['data' => $json], $files);
        }

        return $this->request($method, $url, [], $data === null ? null : ['Content-Type: application/json', $json]);
    }

    /** The id of the item or media a REST API response describes. */
    public static function idFrom(HttpResponse $response): int
    {
        $body = json_decode($response->body, true);
        if ($response->status !== 200 || ! is_array($body) || ! is_int($body['o:id'] ?? null)) {
            throw new RuntimeException("Not a created resource: HTTP {$response->status}: ".substr($response->body, 0, 300));
        }

        return $body['o:id'];
    }

    /**
     * Serves tests/Fixtures/c2pa/ inside the PHP 8.3 container on
     * FIXTURE_PORT, for the URL ingester; no network outside the container.
     */
    public function startFixtureServer(): void
    {
        $probe = 'echo @file_get_contents("http://127.0.0.1:'.self::FIXTURE_PORT.'/README.md") === false ? "down" : "up";';
        if (trim($this->php(['-r', $probe])) === 'up') {
            return;
        }
        self::run(['docker', 'compose', 'exec', '-T', '-d', '-u', 'www-data', $this->omekaService,
            'php', '-S', '127.0.0.1:'.self::FIXTURE_PORT, '-t', self::MODULE_DIR.'/tests/Fixtures/c2pa']);
        for ($i = 0; $i < 20 && trim($this->php(['-r', $probe])) !== 'up'; $i++) {
            usleep(100_000);
        }
    }

    /**
     * Runs PHP inside this installation's Omeka S container, as www-data.
     *
     * @param  list<string>  $arguments
     */
    public function php(array $arguments): string
    {
        return self::run(['docker', 'compose', 'exec', '-T', '-u', 'www-data', $this->omekaService, 'php', ...$arguments]);
    }

    /**
     * The verdict row of a media item, every column as the database holds
     * it (JSON_OBJECT keeps text exact), or null.
     *
     * @return array<string, mixed>|null
     */
    public function verdictRow(int $mediaId): ?array
    {
        $out = $this->sql("SELECT JSON_OBJECT('media_id', media_id, 'state', state, 'format', format, 'signer_issuer', signer_issuer, 'signer_common_name', signer_common_name, 'signed_at', signed_at, 'codes', codes, 'remote_manifest_url', remote_manifest_url, 'reason', reason, 'verifier', verifier, 'trust', trust, 'checked_sha256', checked_sha256, 'checked_at', checked_at) FROM content_credentials_verdict WHERE media_id = ".$mediaId);
        if ($out === '') {
            return null;
        }
        $row = json_decode($out, true, 512, JSON_THROW_ON_ERROR);

        return is_array($row) ? array_combine(array_map('strval', array_keys($row)), $row) : null;
    }

    /**
     * Runs a shell script as root inside this installation's Omeka S
     * container (SPEC-005 AC8 changes and restores local.config.php).
     */
    public function rootShell(string $script): string
    {
        return self::run(['docker', 'compose', 'exec', '-T', $this->omekaService, 'sh', '-c', $script]);
    }

    public function sql(string $query): string
    {
        return trim(self::run([
            'docker', 'compose', 'exec', '-T', $this->dbService,
            // --raw: values as stored, not escaped for batch output (SPEC-002 AC14)
            'mariadb', '-N', '--raw', '-uomeka', '-pomeka', 'omeka', '-e', $query,
        ]));
    }

    /**
     * @param  array<string, string>  $fields
     * @param  array<string, array{string, string, string}>  $files  field => [path, filename, media type]
     */
    private function postMultipart(string $path, array $fields, array $files): HttpResponse
    {
        $boundary = 'spec002'.bin2hex(random_bytes(8));
        $body = '';
        foreach ($fields as $name => $value) {
            $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$name}\"\r\n\r\n{$value}\r\n";
        }
        foreach ($files as $name => [$file, $filename, $mediaType]) {
            $body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$name}\"; filename=\"{$filename}\"\r\n"
                ."Content-Type: {$mediaType}\r\n\r\n".file_get_contents($file)."\r\n";
        }
        $body .= "--{$boundary}--\r\n";

        return $this->request('POST', $path, [], ['Content-Type: multipart/form-data; boundary='.$boundary, $body]);
    }

    /** Makes an API key for the test administrator, inside the container. */
    private function makeApiKey(): string
    {
        $code = <<<'PHP'
            require getenv('OMEKA_ROOT').'/bootstrap.php';
            $services = Omeka\Mvc\Application::init(require OMEKA_PATH.'/application/config/application.config.php')->getServiceManager();
            $em = $services->get('Omeka\EntityManager');
            $key = new Omeka\Entity\ApiKey;
            $key->setId();
            $credential = $key->setCredential();
            $key->setLabel('SPEC-002 tests');
            $key->setOwner($em->find('Omeka\Entity\User', 1));
            $em->persist($key);
            $em->flush();
            echo $key->getId(), ' ', $credential;
            PHP;
        $out = trim($this->php(['-r', $code]));
        if (preg_match('/^(\w+) (\w+)$/', $out, $m) !== 1) {
            throw new RuntimeException("No API key: {$out}");
        }

        return 'key_identity='.$m[1].'&key_credential='.$m[2];
    }

    /**
     * @param  array<string, string>  $fields
     * @param  array{string, string}|null  $raw  [Content-Type header, body], instead of $fields
     */
    private function request(string $method, string $path, array $fields, ?array $raw = null): HttpResponse
    {
        $headers = [];
        if ($this->cookies !== []) {
            $pairs = [];
            foreach ($this->cookies as $name => $value) {
                $pairs[] = "{$name}={$value}";
            }
            $headers[] = 'Cookie: '.implode('; ', $pairs);
        }
        $options = ['method' => $method, 'follow_location' => 0, 'ignore_errors' => true, 'timeout' => 30];
        if ($raw !== null) {
            $headers[] = $raw[0];
            $options['content'] = $raw[1];
        } elseif ($method === 'POST') {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            $options['content'] = http_build_query($fields);
        }
        $options['header'] = implode("\r\n", $headers);

        // fopen and the stream's wrapper_data give the headers on PHP 8.3 to
        // 8.5 alike (http_get_last_response_headers() is 8.4+, and
        // $http_response_header is deprecated in 8.5).
        $stream = @fopen($this->baseUrl.$path, 'r', false, stream_context_create(['http' => $options]));
        if ($stream === false) {
            throw new RuntimeException("No answer from {$this->baseUrl}{$path}: is the environment up (tools/env/up.sh)?");
        }
        $wrapperData = stream_get_meta_data($stream)['wrapper_data'] ?? [];
        $body = (string) stream_get_contents($stream);
        fclose($stream);
        $responseHeaders = array_values(array_filter(is_array($wrapperData) ? $wrapperData : [], 'is_string'));
        if ($responseHeaders === []) {
            throw new RuntimeException("No headers from {$this->baseUrl}{$path}.");
        }

        $status = preg_match('#^HTTP/\S+ (\d{3})#', $responseHeaders[0], $m) === 1 ? (int) $m[1] : 0;
        $location = '';
        $byName = [];
        foreach ($responseHeaders as $header) {
            if (preg_match('/^([^:\s]+):\s*(.*)$/', $header, $h) === 1) {
                $byName[strtolower($h[1])][] = trim($h[2]);
            }
            if (preg_match('/^Set-Cookie:\s*([^=;\s]+)=([^;]*)/i', $header, $c) === 1) {
                $this->cookies[$c[1]] = $c[2];
            } elseif (preg_match('/^Location:\s*(\S+)/i', $header, $l) === 1) {
                $location = $l[1];
            }
        }

        return new HttpResponse($status, $body, $location, $byName);
    }

    /**
     * The HTML of the one form whose action is $action (attribute values
     * decoded), up to its closing tag.
     */
    private static function form(string $html, string $action): string
    {
        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5);
        $start = strpos($decoded, 'action="'.$action.'"');
        if ($start === false) {
            throw new RuntimeException("No form with action {$action} on the page.");
        }
        $end = strpos($decoded, '</form>', $start);

        return substr($decoded, $start, $end === false ? null : $end - $start);
    }

    private static function field(string $html, string $name): string
    {
        if (preg_match('/name="'.preg_quote($name, '/').'" value="([^"]+)"/', $html, $m) !== 1) {
            throw new RuntimeException("No field {$name} on the page.");
        }

        return $m[1];
    }

    private static function quote(string $value): string
    {
        return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";
    }

    /**
     * Runs a command (an argument list, no shell) in the repository root
     * and returns its standard output; throws on a non-zero exit.
     *
     * @param  list<string>  $command
     */
    private static function run(array $command): string
    {
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 3));
        if (! is_resource($process)) {
            throw new RuntimeException('Could not start: '.implode(' ', $command));
        }
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        if ($exit !== 0) {
            throw new RuntimeException('Exit '.$exit.' from '.implode(' ', $command).': '.trim($err));
        }

        return $out;
    }
}
