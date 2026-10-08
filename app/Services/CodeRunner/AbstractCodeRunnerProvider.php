<?php

namespace App\Services\CodeRunner;

abstract class AbstractCodeRunnerProvider implements CodeRunnerProvider
{
    protected function limitOutput(string $value): string
    {
        $value = trim($value);
        if (mb_strlen($value) <= 30000) return $value;
        return mb_substr($value, 0, 30000)."\n… [a kimenet rövidítve]";
    }
}
