<?php

declare(strict_types=1);

namespace App\Core;

class Stream
{
    private string $contents = '';

    public function write(string $string): int
    {
        $this->contents .= $string;

        return strlen($string);
    }

    public function __toString(): string
    {
        return $this->contents;
    }
}
