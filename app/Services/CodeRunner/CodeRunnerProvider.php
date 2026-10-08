<?php

namespace App\Services\CodeRunner;

interface CodeRunnerProvider
{
    public function name(): string;
    public function isConfigured(): bool;
    public function run(string $language, string $code, string $stdin): array;
}
