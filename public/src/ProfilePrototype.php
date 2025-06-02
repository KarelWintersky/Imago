<?php

namespace App\Imago;

use App\Imago\ProfilePrototypeInterface;

class ProfilePrototype implements ProfilePrototypeInterface
{
    public array $config_data = [];

    public function __construct($configDir, $configFile = null)
    {
        if (!is_dir($configDir)) {
            throw new \RuntimeException("Config dir not exists");
        }

        if (!is_readable($configDir . '/' . $configFile)) {
            throw new \RuntimeException("Config file ". $configFile ." not exists");
        }

        $this->config_data = require_once $configDir . $configFile;

        if (empty($this->config_data)) {
            throw new \RuntimeException("Invalid or empty project");
        }
    }

    public function getConfig($key = '', $defaultValue = null)
    {
        if (empty($key)) {
            return $this->config_data;
        }

        $keys = explode('.', $key);
        $value = $this->config_data;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $defaultValue;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public function checkAllowed($sourceFile): bool
    {
        return true;
    }

    public function setFileContent($cacheFile):bool
    {
        return true;
    }
}