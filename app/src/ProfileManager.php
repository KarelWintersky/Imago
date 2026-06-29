<?php

declare(strict_types=1);

namespace Imago;

final class ProfileManager
{
    private readonly array $profiles;

    public function __construct(private readonly array $serviceConfig) {
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

        $profile = $this->profiles[$name];

        return [
            'width' => (int) ($profile['width'] ?? 0),
            'height' => (int) ($profile['height'] ?? 0),
            'mode' => $profile['mode'] ?? 'resize',
        ];
    }

    public function list(): array
    {
        return array_keys($this->profiles);
    }
}
