<?php

declare(strict_types=1);

namespace Imago;

final class ConfigLoader
{
    public static function load(string $configPath): array
    {
        if (!file_exists($configPath)) {
            throw new \RuntimeException("Configuration file not found: {$configPath}");
        }

        $config = require $configPath;

        if (!is_array($config)) {
            throw new \RuntimeException('Configuration must return an array');
        }

        $configDir = dirname($configPath);

        $config['root_dir'] = $configDir;
        $config['storage_dir'] = $configDir . '/public/storage';
        $config['cache_dir'] ??= $configDir . '/public/cache';
        $config['log_dir'] = $configDir . '/logs';

        if (isset($config['services']) && is_array($config['services'])) {
            foreach ($config['services'] as $name => &$serviceConfig) {
                if (isset($serviceConfig['config'])) {
                    $serviceConfigPath = $configDir . '/' . $serviceConfig['config'];
                    if (file_exists($serviceConfigPath)) {
                        $extraConfig = require $serviceConfigPath;
                        if (is_array($extraConfig)) {
                            $serviceConfig = array_replace_recursive($serviceConfig, $extraConfig);
                        }
                    }
                }

                $serviceConfig['name'] ??= $name;
                $serviceConfig['storage_path'] = $config['storage_dir'] . '/' . ($serviceConfig['storage'] ?? $name);
            }
        }

        return $config;
    }
}
