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

        $rules = $this->profiles[$name];
        $rule = array_key_first($rules);
        $config = $rules[$rule];

        return [
            'width' => (int) ($config['width'] ?? 0),
            'height' => (int) ($config['height'] ?? 0),
            'mode' => $rule,
        ];
    }

    public function list(): array
    {
        return array_keys($this->profiles);
    }
}
