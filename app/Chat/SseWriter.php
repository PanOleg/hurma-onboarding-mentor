<?php

namespace App\Chat;

final class SseWriter
{
    /** @var callable(string): void */
    private $emit;

    /** @param  callable(string): void  $emit */
    public function __construct(callable $emit)
    {
        $this->emit = $emit;
    }

    /** @param  array<mixed>  $data */
    public function event(string $name, array $data): void
    {
        ($this->emit)("event: $name\ndata: ".json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n");
    }
}
