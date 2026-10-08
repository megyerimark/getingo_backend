<?php

namespace App\Services\CodeRunner;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PistonCodeRunner extends AbstractCodeRunnerProvider
{
    public function __construct(private readonly SqlSandboxPrelude $sqlPrelude) {}
    public function name(): string { return 'piston'; }
    public function isConfigured(): bool { return trim((string) config('services.piston.url')) !== ''; }

    public function run(string $language, string $code, string $stdin): array
    {
        $baseUrl = rtrim((string) config('services.piston.url'), '/');
        if ($baseUrl === '') throw new RuntimeException('A Piston nincs konfigurálva.');
        $runtime = $this->runtime($baseUrl, $language);
        if (! $runtime) throw new RuntimeException('A kiválasztott nyelv nincs telepítve a Piston sandboxban.');

        $source = $language === 'sql' ? $this->sqlPrelude->for('sqlite')."\n\n".$code : $code;
        $fileName = match ($language) { 'python' => 'main.py', 'csharp' => 'Program.cs', 'sql' => 'query.sql' };
        $response = $this->client()->timeout(25)->post($baseUrl.'/execute', [
            'language' => $runtime['language'], 'version' => $runtime['version'],
            'files' => [['name' => $fileName, 'content' => $source]], 'stdin' => $stdin,
            'compile_timeout' => 10000, 'run_timeout' => 5000,
            'compile_cpu_time' => 10000, 'run_cpu_time' => 5000,
            'compile_memory_limit' => 268435456, 'run_memory_limit' => 268435456,
        ]);
        if ($response->failed()) throw new RuntimeException('A Piston HTTP hibát adott.');
        $payload = $response->json();
        if (! is_array($payload)) throw new RuntimeException('Érvénytelen Piston válasz.');

        $compile = is_array($payload['compile'] ?? null) ? $payload['compile'] : [];
        $run = is_array($payload['run'] ?? null) ? $payload['run'] : [];
        $compileOut = $this->limitOutput(trim((string) ($compile['stdout'] ?? '')."\n".(string) ($compile['stderr'] ?? '')));
        $stdout = $this->limitOutput((string) ($run['stdout'] ?? ''));
        $stderr = $this->limitOutput((string) ($run['stderr'] ?? ''));
        $compileFailed = isset($compile['code']) && (int) $compile['code'] !== 0;
        $runFailed = isset($run['code']) && (int) $run['code'] !== 0;
        $parts = array_values(array_filter([
            $compileOut !== '' ? "Fordító:\n".$compileOut : '',
            $stderr !== '' ? "Hiba:\n".$stderr : '', $stdout,
            ! empty($run['message']) ? "Üzenet:\n".$this->limitOutput((string) $run['message']) : '',
        ]));
        return [
            'language' => $language, 'provider' => $this->name(),
            'runtime' => ucfirst($runtime['language']).' '.$runtime['version'].' · Piston sandbox',
            'status' => $compileFailed ? 'Fordítási hiba' : ($runFailed ? 'Futási hiba' : 'Sikeres'),
            'output' => $parts !== [] ? implode("\n\n", $parts) : 'A program lefutott, de nem adott kimenetet.',
            'stdout' => $stdout, 'stderr' => $stderr, 'compile_output' => $compileOut,
            'time' => null, 'memory' => null, 'sql_dialect' => $language === 'sql' ? 'SQLite' : null, 'warning' => null,
        ];
    }

    private function runtime(string $baseUrl, string $language): ?array
    {
        $runtimes = Cache::remember('piston:runtimes:'.sha1($baseUrl), now()->addHour(), function () use ($baseUrl) {
            $response = $this->client()->timeout(10)->get($baseUrl.'/runtimes');
            if ($response->failed()) throw new RuntimeException('Nem sikerült lekérni a Piston runtime-okat.');
            return $response->json();
        });
        $items = is_array($runtimes) ? $runtimes : [];
        $wanted = match ($language) { 'python' => ['python', 'python3', 'py'], 'csharp' => ['csharp', 'c#', 'cs', 'dotnet'], 'sql' => ['sqlite', 'sqlite3', 'sql'] };
        $matches = array_values(array_filter($items, function ($item) use ($wanted) {
            $names = array_map('strtolower', array_filter(array_merge([(string) ($item['language'] ?? '')], is_array($item['aliases'] ?? null) ? $item['aliases'] : [])));
            return array_intersect($wanted, $names) !== [];
        }));
        if ($matches === []) return null;
        usort($matches, fn ($a, $b) => version_compare((string) ($b['version'] ?? '0'), (string) ($a['version'] ?? '0')));
        return ['language' => (string) $matches[0]['language'], 'version' => (string) $matches[0]['version']];
    }

    private function client(): PendingRequest
    {
        $headers = [];
        $token = trim((string) config('services.piston.auth_token'));
        $header = trim((string) config('services.piston.auth_header', 'Authorization'));
        if ($token !== '' && $header !== '') $headers[$header] = $token;
        return Http::acceptJson()->asJson()->withHeaders($headers);
    }
}
