<?php

namespace App\Imago\Profiles;

use App\Imago\ProfilePrototype;

class FSNews extends ProfilePrototype
{
    const CONFIG_FILE = '47news.php';

    public function __construct($configDir, $configFile = null)
    {
        parent::__construct($configDir, self::CONFIG_FILE);
    }

    public function checkAllowed($sourceFile): bool
    {
        return true;
    }

}