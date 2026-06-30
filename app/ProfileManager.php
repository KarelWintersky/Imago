<?php

declare(strict_types=1);

namespace Imago;

final readonly class ProfileManager
{
    private array $profiles;

    public function __construct(private array $serviceConfig)
    {
        $this->profiles = $serviceConfig['profiles'] ?? [];
    }

    public function has(string $name): bool
    {
        return isset($this->profiles[$name]);
    }

    public function resolve(string $name): array
    {
        if (!$this->has($name)) {
            throw new \RuntimeException("Unknown profile: {$name}");
        }

        return $this->profiles[$name];
    }

    public function resolveDimensions(string $name): array
    {
        $rules = $this->resolve($name);
        $firstKey = array_key_first($rules);
        $config = $rules[$firstKey];

        return [
            'width' => (int) ($config['width'] ?? 0),
            'height' => (int) ($config['height'] ?? 0),
            'mode' => $firstKey,
        ];
    }

    public function list(): array
    {
        return array_keys($this->profiles);
    }
}
