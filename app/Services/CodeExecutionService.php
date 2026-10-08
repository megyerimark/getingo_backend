<?php

namespace App\Services;

use App\Services\CodeRunner\CodeRunnerProvider;
use App\Services\CodeRunner\Judge0CodeRunner;
use App\Services\CodeRunner\OneCompilerCodeRunner;
use App\Services\CodeRunner\PistonCodeRunner;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CodeExecutionService
{
    public function __construct(
        private readonly Judge0CodeRunner $judge0,
        private readonly OneCompilerCodeRunner $oneCompiler,
        private readonly PistonCodeRunner $piston,
    ) {}

    public function capabilities(): array
    {
        $languages = [];
        foreach (['python', 'csharp', 'sql'] as $language) {
            $primaryName = $this->primaryProviderName($language);
            $fallbackName = $this->fallbackProviderName();
            $primary = $this->provider($primaryName);
            $fallback = $this->provider($fallbackName);
            $languages[$language] = [
                'provider' => $primaryName,
                'configured' => $primary->isConfigured(),
                'fallback_provider' => $fallbackName !== $primaryName ? $fallbackName : null,
                'fallback_configured' => $fallbackName !== $primaryName ? $fallback->isConfigured() : false,
                'sandboxed' => true,
            ];
        }
        return ['languages' => $languages];
    }

    public function execute(string $language, string $code, string $stdin = ''): array
    {
        $providerNames = array_values(array_unique([$this->primaryProviderName($language), $this->fallbackProviderName()]));
        $lastInfrastructureError = null;
        foreach ($providerNames as $index => $providerName) {
            $provider = $this->provider($providerName);
            if (! $provider->isConfigured()) continue;
            try {
                $result = $provider->run($language, $code, $stdin);
                if ($index > 0) $result['warning'] = trim(($result['warning'] ?? '')."\nAz elsődleges futtató nem volt elérhető, ezért tartalék sandbox futott.");
                return $result;
            } catch (ConnectionException|RuntimeException $exception) {
                $lastInfrastructureError = $exception;
                Log::warning('Code runner provider failed', ['provider' => $providerName, 'language' => $language, 'message' => $exception->getMessage()]);
            } catch (\Throwable $exception) {
                report($exception);
                $lastInfrastructureError = $exception;
            }
        }
        throw new RuntimeException($lastInfrastructureError
            ? 'A kódfuttató szolgáltatás most nem elérhető. Próbáld újra később.'
            : 'Ehhez a nyelvhez nincs beállítva használható sandbox futtató.');
    }

    private function provider(string $name): CodeRunnerProvider
    {
        return match ($name) {
            'onecompiler' => $this->oneCompiler,
            'piston' => $this->piston,
            default => $this->judge0,
        };
    }

    private function primaryProviderName(string $language): string
    {
        return $this->normalizeProvider((string) config('services.code_runner.providers.'.$language));
    }

    private function fallbackProviderName(): string
    {
        return $this->normalizeProvider((string) config('services.code_runner.fallback_provider', 'judge0'));
    }

    private function normalizeProvider(string $provider): string
    {
        $provider = strtolower(trim($provider));
        return in_array($provider, ['judge0', 'onecompiler', 'piston'], true) ? $provider : 'judge0';
    }
}
