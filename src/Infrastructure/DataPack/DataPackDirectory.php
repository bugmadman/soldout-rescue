<?php

declare(strict_types=1);

namespace App\Infrastructure\DataPack;

final readonly class DataPackDirectory
{
    private function __construct(
        private string $path,
    ) {
    }

    public static function fromPath(string $path): self
    {
        $path = rtrim(trim($path), '/');
        if ($path === '' || !is_dir($path)) {
            throw new \DomainException(\sprintf('Data pack directory "%s" does not exist.', $path));
        }

        return new self($path);
    }

    public function file(string $name): string
    {
        return $this->path.'/'.$name;
    }

    public function has(string $name): bool
    {
        return is_file($this->file($name));
    }
}
