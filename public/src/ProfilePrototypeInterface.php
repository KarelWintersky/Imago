<?php

namespace App\Imago;

interface ProfilePrototypeInterface
{
    public function __construct($configDir, $configFile = null);
    public function getConfig($key = '', $defaultValue = null);

    public function checkAllowed($sourceFile):bool;
    public function setFileContent($cacheFile):bool;

}