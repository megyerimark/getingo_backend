<?php

namespace App\Services\CodeRunner;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class Judge0CodeRunner extends AbstractCodeRunnerProvider
{
    public function __construct(private readonly SqlSandboxPrelude $sqlPrelude) {}
    public function name(): string { return 'judge0'; }
    public function isConfigured(): bool { return trim((string) config('services.judge0.url')) !== ''; }

    public function run(string $language, string $code, string $stdin): array
    {
        $baseUrl = rtrim((string) config('services.judge0.url'), '/');
        if ($baseUrl === '') throw new RuntimeException('A Judge0 nincs konfigurálva.');
        $languageInfo = $this->language($baseUrl, $language);
        if (! $languageInfo) throw new RuntimeException('A kiválasztott nyelv nem érhető el a Judge0 sandboxban.');
        $source = $language === 'sql' ? $this->sqlPrelude->for('sqlite')."\n\n".$code : $code;
        $response = $this->client()->timeout(20)->post($baseUrl.'/submissions?base64_encoded=false&wait=true', [
            'language_id' => $languageInfo['id'], 'source_code' => $source, 'stdin' => $stdin,
            'cpu_time_limit' => 5, 'wall_time_limit' => 10, 'memory_limit' => 262144,
        ]);
        if ($response->failed()) throw new RuntimeException('A Judge0 HTTP hibát adott.');
        $payload = $response->json();
        if (! is_array($payload)) throw new RuntimeException('Érvénytelen Judge0 válasz.');

        $stdout = $this->limitOutput((string) ($payload['stdout'] ?? ''));
        $stderr = $this->limitOutput((string) ($payload['stderr'] ?? ''));
        $compileOutput = $this->limitOutput((string) ($payload['compile_output'] ?? ''));
        $message = $this->limitOutput((string) ($payload['message'] ?? ''));
        $parts = array_values(array_filter([
            $compileOutput !== '' ? "Fordító:\n".$compileOutput : '',
            $stderr !== '' ? "Hiba:\n".$stderr : '', $stdout,
            $message !== '' ? "Üzenet:\n".$message : '',
        ]));
        $warning = null;
        if ($language === 'csharp' && stripos($languageInfo['name'], 'Mono') !== false) $warning = 'Ez a tartalék Judge0 C# runtime Mono alapú. Újabb C#/.NET nyelvi elemekhez állíts be OneCompiler vagy saját modern Piston futtatót.';
        if ($language === 'sql') $warning = 'A tartalék SQL futtató SQLite-ot használ; néhány MySQL/MariaDB-specifikus utasítás eltérhet.';
        return [
            'language' => $language, 'provider' => $this->name(), 'runtime' => $languageInfo['name'].' · Judge0 sandbox',
            'status' => (string) data_get($payload, 'status.description', 'Ismeretlen'),
            'output' => $parts !== [] ? implode("\n\n", $parts) : 'A program lefutott, de nem adott kimenetet.',
            'stdout' => $stdout, 'stderr' => $stderr, 'compile_output' => $compileOutput,
            'time' => isset($payload['time']) ? (string) $payload['time'] : null,
            'memory' => isset($payload['memory']) ? (int) $payload['memory'] : null,
            'sql_dialect' => $language === 'sql' ? 'SQLite' : null, 'warning' => $warning,
        ];
    }

    private function language(string $baseUrl, string $language): ?array
    {
        $languages = Cache::remember('judge0:languages:'.sha1($baseUrl), now()->addHour(), function () use ($baseUrl) {
            $response = $this->client()->timeout(10)->get($baseUrl.'/languages');
            if ($response->failed()) throw new RuntimeException('Nem sikerült lekérni a Judge0 nyelveit.');
            return $response->json();
        });
        $items = is_array($languages) ? $languages : [];
        if ($language === 'csharp') {
            $dotnet = array_values(array_filter($items, fn ($item) => preg_match('/^C# .*\.NET/i', (string) ($item['name'] ?? ''))));
            if ($dotnet !== []) {
                usort($dotnet, fn ($a, $b) => ((int) $b['id']) <=> ((int) $a['id']));
                return ['id' => (int) $dotnet[0]['id'], 'name' => (string) $dotnet[0]['name']];
            }
        }
        $patterns = ['python' => '/^Python \(3/i', 'csharp' => '/^C# \(/i', 'sql' => '/^SQL \(SQLite/i'];
        $matches = array_values(array_filter($items, fn ($item) => preg_match($patterns[$language], (string) ($item['name'] ?? ''))));
        if ($matches === []) return null;
        usort($matches, fn ($a, $b) => ((int) $b['id']) <=> ((int) $a['id']));
        return ['id' => (int) $matches[0]['id'], 'name' => (string) $matches[0]['name']];
    }

    private function client(): PendingRequest
    {
        $headers = [];
        $token = trim((string) config('services.judge0.auth_token'));
        if ($token !== '') $headers['X-Auth-Token'] = $token;
        return Http::acceptJson()->asJson()->withHeaders($headers);
    }
}
