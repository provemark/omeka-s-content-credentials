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

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $dbService,
    ) {}

    public static function php83(): self
    {
        return new self('http://127.0.0.1:'.(getenv('CC_TEST_PORT') ?: '8088'), 'db');
    }

    public static function php82(): self
    {
        return new self('http://127.0.0.1:'.(getenv('CC_TEST_PORT_PHP82') ?: '8089'), 'db-php82');
    }

    public function logIn(): void
    {
        $this->cookies = [];
        $page = $this->get('/login');
        $response = $this->post('/login', [
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
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
    public function snapshot(): string
    {
        $schema = (string) preg_replace('/ AUTO_INCREMENT=\d+/', '', self::run([
            'docker', 'compose', 'exec', '-T', $this->dbService,
            'mariadb-dump', '-uomeka', '-pomeka', '--no-data', '--skip-comments', '--skip-dump-date', 'omeka',
        ]));
        $counts = [];
        foreach (explode("\n", $this->sql('SHOW TABLES')) as $table) {
            if ($table !== 'session') {
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

    private function sql(string $query): string
    {
        return trim(self::run([
            'docker', 'compose', 'exec', '-T', $this->dbService,
            'mariadb', '-N', '-uomeka', '-pomeka', 'omeka', '-e', $query,
        ]));
    }

    /**
     * @param  array<string, string>  $fields
     */
    private function request(string $method, string $path, array $fields): HttpResponse
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
        if ($method === 'POST') {
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
        foreach ($responseHeaders as $header) {
            if (preg_match('/^Set-Cookie:\s*([^=;\s]+)=([^;]*)/i', $header, $c) === 1) {
                $this->cookies[$c[1]] = $c[2];
            } elseif (preg_match('/^Location:\s*(\S+)/i', $header, $l) === 1) {
                $location = $l[1];
            }
        }

        return new HttpResponse($status, $body, $location);
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
