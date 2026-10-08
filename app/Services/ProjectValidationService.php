<?php

namespace App\Services;

use App\Models\Project;

class ProjectValidationService
{
    public const SERVER_VALIDATION_TYPES = [
        'html_contains',
        'css_contains',
        'javascript_contains',
        'source_contains',
    ];

    public const CLIENT_VALIDATION_TYPES = [
        'console_exact',
        'console_contains',
    ];

    public function validate(Project $project, array $payload): array
    {
        $expected = $this->expectedFragments((string) $project->expected_output);
        $type = (string) $project->validation_type;
        $actual = $this->normalizeConsole($payload['console_output'] ?? []);

        if ($expected === []) {
            return [
                'passed' => false,
                'trusted' => false,
                'console_output' => $actual,
            ];
        }

        if (in_array($type, self::SERVER_VALIDATION_TYPES, true)) {
            return [
                'passed' => $this->missingServerRequirements($type, $payload, $expected) === [],
                'trusted' => true,
                'console_output' => $actual,
            ];
        }

        return [
            'passed' => $this->passesClientConsoleValidation($type, $actual, $expected),
            'trusted' => false,
            'console_output' => $actual,
        ];
    }

    public function isTrustedType(?string $type): bool
    {
        return in_array((string) $type, self::SERVER_VALIDATION_TYPES, true);
    }

    public function missingRequirements(Project $project, array $payload): array
    {
        $expected = $this->expectedFragments((string) $project->expected_output);
        $type = (string) $project->validation_type;

        if (! in_array($type, self::SERVER_VALIDATION_TYPES, true)) {
            return [];
        }

        return $this->missingServerRequirements($type, $payload, $expected);
    }

    private function missingServerRequirements(string $type, array $payload, array $expected): array
    {
        if ($type === 'javascript_contains') {
            $source = $this->stripJavaScriptComments((string) ($payload['javascript_code'] ?? ''));
            return $this->missingJavaScriptRequirements($source, $expected);
        }

        $source = match ($type) {
            'html_contains' => $this->stripHtmlComments((string) ($payload['html_code'] ?? '')),
            'css_contains' => $this->stripBlockComments((string) ($payload['css_code'] ?? '')),
            'source_contains' => implode("\n", [
                $this->stripHtmlComments((string) ($payload['html_code'] ?? '')),
                $this->stripBlockComments((string) ($payload['css_code'] ?? '')),
                $this->stripJavaScriptComments((string) ($payload['javascript_code'] ?? '')),
            ]),
            default => '',
        };

        $normalizedSource = $this->normalizeSource($source);
        if ($normalizedSource === '') return $expected;

        return collect($expected)
            ->filter(fn (string $fragment) => ! str_contains($normalizedSource, $this->normalizeSource($fragment)))
            ->values()
            ->all();
    }

    /**
     * JavaScriptnél a mintamegoldás konkrét változónevei és értékei ne legyenek kötelezőek.
     * Példa: `let nev = "9";` azt jelenti, hogy legyen egy let deklaráció, nem azt,
     * hogy a tanulónak pontosan `nev` változót és `"9"` értéket kell használnia.
     */
    private function missingJavaScriptRequirements(string $source, array $expected): array
    {
        $normalizedSource = $this->normalizeSource($source);
        if ($normalizedSource === '') return $expected;

        $actualDeclarations = $this->javascriptDeclarations($source);
        $usedDeclarations = array_fill(0, count($actualDeclarations), false);
        $hasDeclarationRequirements = collect($expected)
            ->contains(fn (string $fragment) => $this->parseJavaScriptDeclaration(trim($fragment)) !== null);
        $usage = [];
        $missing = [];

        foreach ($expected as $fragment) {
            $trimmed = trim($fragment);

            $declaration = $this->parseJavaScriptDeclaration($trimmed);
            if ($declaration !== null) {
                $matched = false;
                foreach ($actualDeclarations as $index => $actual) {
                    if ($usedDeclarations[$index]) continue;
                    if ($actual['keyword'] !== $declaration['keyword']) continue;
                    if (! $this->containsAllOperators($actual['operators'], $declaration['operators'])) continue;

                    $usedDeclarations[$index] = true;
                    $matched = true;
                    break;
                }

                if (! $matched) $missing[] = $fragment;
                continue;
            }

            $semanticPattern = $this->semanticJavaScriptPattern($trimmed, $hasDeclarationRequirements);
            if ($semanticPattern !== null) {
                $key = $semanticPattern['key'];
                $requiredOccurrence = ($usage[$key] ?? 0) + 1;
                $usage[$key] = $requiredOccurrence;
                $actualCount = preg_match_all($semanticPattern['pattern'], $source) ?: 0;

                if ($actualCount < $requiredOccurrence) $missing[] = $fragment;
                continue;
            }

            if (! str_contains($normalizedSource, $this->normalizeSource($fragment))) {
                $missing[] = $fragment;
            }
        }

        return $missing;
    }

    private function javascriptDeclarations(string $source): array
    {
        preg_match_all(
            '/\b(let|const|var)\s+[A-Za-z_$][A-Za-z0-9_$]*\s*(?:=\s*([^;\r\n]+))?\s*;?/u',
            $source,
            $matches,
            PREG_SET_ORDER
        );

        return collect($matches)
            ->map(function (array $match): array {
                $rhs = (string) ($match[2] ?? '');
                return [
                    'keyword' => strtolower((string) $match[1]),
                    'operators' => $this->extractOperators($rhs),
                ];
            })
            ->all();
    }

    private function parseJavaScriptDeclaration(string $fragment): ?array
    {
        if (! preg_match('/^\s*(let|const|var)\s+[A-Za-z_$][A-Za-z0-9_$]*\s*(?:=\s*([^;\r\n]+))?\s*;?\s*$/u', $fragment, $match)) {
            return null;
        }

        return [
            'keyword' => strtolower((string) $match[1]),
            'operators' => $this->extractOperators((string) ($match[2] ?? '')),
        ];
    }

    private function extractOperators(string $source): array
    {
        preg_match_all('/(?:===|!==|==|!=|>=|<=|\+|-|\*|\/|%|>|<)/', $source, $matches);
        return array_values(array_unique($matches[0] ?? []));
    }

    private function containsAllOperators(array $actual, array $expected): bool
    {
        foreach ($expected as $operator) {
            if (! in_array($operator, $actual, true)) return false;
        }
        return true;
    }

    private function semanticJavaScriptPattern(string $fragment, bool $allowFlexibleConsole): ?array
    {
        $normalized = trim($fragment);

        if ($allowFlexibleConsole && preg_match('/^console\s*\.\s*log\s*\(/i', $normalized)) {
            return ['key' => 'console.log', 'pattern' => '/\bconsole\s*\.\s*log\s*\(/i'];
        }

        if (! $allowFlexibleConsole && preg_match('/^console\s*\.\s*log\s*\(/i', $normalized)) {
            return null;
        }

        foreach (['if', 'for', 'while', 'switch'] as $keyword) {
            if (preg_match('/^'.preg_quote($keyword, '/').'\s*\(/i', $normalized)) {
                return ['key' => $keyword, 'pattern' => '/\b'.preg_quote($keyword, '/').'\s*\(/i'];
            }
        }

        if (preg_match('/^function\s+[A-Za-z_$][A-Za-z0-9_$]*\s*\(/i', $normalized)) {
            return ['key' => 'function', 'pattern' => '/\bfunction\s+[A-Za-z_$][A-Za-z0-9_$]*\s*\(/i'];
        }

        if (preg_match('/^([A-Za-z_$][A-Za-z0-9_$]*(?:\s*\.\s*[A-Za-z_$][A-Za-z0-9_$]*)+)\s*\(/', $normalized, $match)) {
            $call = preg_replace('/\s+/', '', (string) $match[1]);
            $parts = array_map(fn ($part) => preg_quote($part, '/'), explode('.', $call));
            return [
                'key' => 'call:'.$call,
                'pattern' => '/\b'.implode('\s*\.\s*', $parts).'\s*\(/i',
            ];
        }

        return null;
    }

    private function passesClientConsoleValidation(string $type, array $actual, array $expected): bool
    {
        if ($type === 'console_contains') {
            foreach ($expected as $expectedLine) {
                if (! in_array($expectedLine, $actual, true)) return false;
            }
            return true;
        }

        if ($type === 'console_exact') return $actual === $expected;
        return false;
    }

    private function expectedFragments(string $expected): array
    {
        return collect(preg_split('/\R/u', $expected) ?: [])
            ->map(fn ($line) => trim((string) $line))
            ->filter(fn (string $line) => $line !== '')
            ->values()
            ->all();
    }

    private function normalizeConsole(array $lines): array
    {
        return collect($lines)
            ->map(fn ($line) => trim((string) $line))
            ->filter(fn (string $line) => $line !== '')
            ->values()
            ->all();
    }

    private function normalizeSource(string $source): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $source));
    }

    private function stripHtmlComments(string $source): string
    {
        return (string) preg_replace('/<!--.*?-->/s', '', $source);
    }

    private function stripBlockComments(string $source): string
    {
        return (string) preg_replace('~/\*.*?\*/~s', '', $source);
    }

    private function stripJavaScriptComments(string $source): string
    {
        $source = $this->stripBlockComments($source);
        return (string) preg_replace('~(^|\s)//[^\r\n]*~m', '$1', $source);
    }
}
