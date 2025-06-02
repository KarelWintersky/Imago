<?php

namespace App\Imago\Profiles;

use App\Imago\ProfilePrototype;

class ZSDCostume extends ProfilePrototype
{
    const CONFIG_FILE = 'zsd_costume.php';

    public function __construct($configDir, $configFile = null)
    {
        parent::__construct($configDir, self::CONFIG_FILE);
    }

    public function checkAllowed($sourceFile): bool
    {
        // return str_contains('445336100', $sourceFile);
        return true;
    }

    public function setFileContent($cacheFile):bool
    {
        return copy($this->getConfig('BAD_IMAGE'), $cacheFile);
    }

}