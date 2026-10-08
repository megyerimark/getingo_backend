<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CodeExecutionTest extends TestCase
{
    public function test_python_code_can_be_forwarded_to_judge0_sandbox(): void
    {
        config([
            'services.code_runner.providers.python' => 'judge0',
            'services.code_runner.fallback_provider' => 'judge0',
            'services.judge0.url' => 'https://runner.example',
        ]);
        Cache::forget('judge0:languages:'.sha1('https://runner.example'));

        Http::fake([
            'https://runner.example/languages' => Http::response([
                ['id' => 109, 'name' => 'Python (3.13.2)'],
                ['id' => 51, 'name' => 'C# (Mono 6.6.0.161)'],
                ['id' => 82, 'name' => 'SQL (SQLite 3.27.2)'],
            ]),
            'https://runner.example/submissions*' => Http::response([
                'stdout' => "hello\n",
                'stderr' => null,
                'compile_output' => null,
                'message' => null,
                'status' => ['description' => 'Accepted'],
                'time' => '0.01',
                'memory' => 1024,
            ]),
        ]);

        $this->postJson('/api/code/run', [
            'language' => 'python',
            'code' => "print('hello')",
            'stdin' => '',
        ])->assertOk()
            ->assertJsonPath('provider', 'judge0')
            ->assertJsonPath('status', 'Accepted')
            ->assertJsonPath('output', 'hello');
    }

    public function test_csharp_prefers_onecompiler_when_api_key_is_configured(): void
    {
        config([
            'services.code_runner.providers.csharp' => 'onecompiler',
            'services.code_runner.fallback_provider' => 'judge0',
            'services.onecompiler.url' => 'https://api.onecompiler.test/v1',
            'services.onecompiler.api_key' => 'secret-test-key',
        ]);

        Http::fake([
            'https://api.onecompiler.test/v1/run' => Http::response([
                'status' => 'success',
                'stdout' => "Hello modern C#\n",
                'stderr' => null,
                'exception' => null,
                'executionTime' => 75,
            ]),
        ]);

        $this->postJson('/api/code/run', [
            'language' => 'csharp',
            'code' => 'Console.WriteLine("Hello modern C#");',
        ])->assertOk()
            ->assertJsonPath('provider', 'onecompiler')
            ->assertJsonPath('status', 'Sikeres')
            ->assertJsonPath('output', 'Hello modern C#')
            ->assertJsonPath('time', '0.075');

        Http::assertSent(fn ($request) =>
            $request->url() === 'https://api.onecompiler.test/v1/run'
            && $request->hasHeader('X-API-Key', 'secret-test-key')
            && data_get($request->data(), 'language') === 'csharp'
        );
    }

    public function test_sql_uses_mysql_on_onecompiler(): void
    {
        config([
            'services.code_runner.providers.sql' => 'onecompiler',
            'services.onecompiler.url' => 'https://api.onecompiler.test/v1',
            'services.onecompiler.api_key' => 'secret-test-key',
        ]);

        Http::fake([
            'https://api.onecompiler.test/v1/run' => Http::response([
                'status' => 'success',
                'stdout' => "name\nAnna\n",
                'stderr' => null,
                'exception' => null,
                'executionTime' => 20,
            ]),
        ]);

        $this->postJson('/api/code/run', [
            'language' => 'sql',
            'code' => 'SELECT name FROM users WHERE id = 1;',
        ])->assertOk()
            ->assertJsonPath('provider', 'onecompiler')
            ->assertJsonPath('sql_dialect', 'MySQL');

        Http::assertSent(function ($request) {
            $data = $request->data();
            return data_get($data, 'language') === 'mysql'
                && str_contains((string) data_get($data, 'files.0.content'), 'CREATE TABLE users')
                && str_contains((string) data_get($data, 'files.0.content'), 'SELECT name FROM users WHERE id = 1;');
        });
    }

    public function test_code_runner_rejects_unknown_languages(): void
    {
        $this->postJson('/api/code/run', [
            'language' => 'php',
            'code' => '<?php echo 1;',
        ])->assertUnprocessable();
    }
}
