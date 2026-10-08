<?php

namespace App\Http\Controllers;

use App\Services\CodeExecutionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class CodeExecutionController extends Controller
{
    public function __construct(private readonly CodeExecutionService $codeExecution) {}

    public function capabilities(): JsonResponse
    {
        return response()->json($this->codeExecution->capabilities());
    }

    public function run(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'language' => ['required', Rule::in(['python', 'csharp', 'sql'])],
            'code' => ['required', 'string', 'max:30000'],
            'stdin' => ['nullable', 'string', 'max:10000'],
        ]);

        try {
            return response()->json($this->codeExecution->execute(
                $validated['language'],
                $validated['code'],
                $validated['stdin'] ?? ''
            ));
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 503);
        }
    }
}
