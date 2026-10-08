<?php

namespace App\Services\CodeRunner;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OneCompilerCodeRunner extends AbstractCodeRunnerProvider
{
    public function __construct(private readonly SqlSandboxPrelude $sqlPrelude) {}
    public function name(): string { return 'onecompiler'; }
    public function isConfigured(): bool { return trim((string) config('services.onecompiler.url')) !== '' && trim((string) config('services.onecompiler.api_key')) !== ''; }

    public function run(string $language, string $code, string $stdin): array
    {
        $baseUrl = rtrim((string) config('services.onecompiler.url'), '/');
        $apiKey = trim((string) config('services.onecompiler.api_key'));
        if ($baseUrl === '' || $apiKey === '') throw new RuntimeException('A OneCompiler nincs konfigurálva.');

        $remoteLanguage = match ($language) { 'python' => 'python', 'csharp' => 'csharp', 'sql' => 'mysql' };
        $source = $language === 'sql' ? $this->sqlPrelude->for('mysql')."\n\n".$code : $code;
        $fileName = match ($language) { 'python' => 'main.py', 'csharp' => 'Program.cs', 'sql' => 'query.sql' };
        $response = Http::acceptJson()->asJson()->withHeaders(['X-API-Key' => $apiKey])->timeout(25)->post($baseUrl.'/run', [
            'language' => $remoteLanguage,
            'stdin' => $stdin,
            'files' => [['name' => $fileName, 'content' => $source]],
        ]);
        if ($response->failed()) throw new RuntimeException('A OneCompiler HTTP hibát adott.');

        $payload = $response->json();
        if (! is_array($payload) || (($payload['status'] ?? 'success') === 'failed')) {
            throw new RuntimeException((string) ($payload['error'] ?? 'A OneCompiler nem tudta elindítani a futtatást.'));
        }

        $stdout = $this->limitOutput((string) ($payload['stdout'] ?? ''));
        $stderr = $this->limitOutput((string) ($payload['stderr'] ?? ''));
        $exception = $this->limitOutput((string) ($payload['exception'] ?? $payload['error'] ?? ''));
        $parts = array_values(array_filter([
            $exception !== '' ? "Fordítási / futási hiba:\n".$exception : '',
            $stderr !== '' ? "Hiba:\n".$stderr : '',
            $stdout,
        ]));
        $runtime = match ($language) { 'python' => 'Python · OneCompiler sandbox', 'csharp' => 'C# · OneCompiler sandbox', 'sql' => 'MySQL · OneCompiler sandbox' };

        return [
            'language' => $language, 'provider' => $this->name(), 'runtime' => $runtime,
            'status' => ($exception !== '' || $stderr !== '') ? 'Hiba' : 'Sikeres',
            'output' => $parts !== [] ? implode("\n\n", $parts) : 'A program lefutott, de nem adott kimenetet.',
            'stdout' => $stdout, 'stderr' => $stderr, 'compile_output' => $exception,
            'time' => isset($payload['executionTime']) ? number_format(((float) $payload['executionTime']) / 1000, 3, '.', '') : null,
            'memory' => isset($payload['memory']) ? (int) $payload['memory'] : null,
            'sql_dialect' => $language === 'sql' ? 'MySQL' : null, 'warning' => null,
        ];
    }
}
