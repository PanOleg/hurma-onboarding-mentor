<?php

namespace App\Chat\Llm;

use RuntimeException;

final class PromptLoader
{
    public function load(string $name): string
    {
        $file = config("rag.prompts.$name");
        $path = resource_path("prompts/$file.md");
        if (! is_file($path)) {
            throw new RuntimeException("prompt file not found: $path");
        }

        return rtrim((string) file_get_contents($path));
    }
}
