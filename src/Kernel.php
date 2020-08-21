<?php

declare(strict_types=1);

namespace Heptacom\HeptaConnect\Sdk;

use Composer\Autoload\ClassLoader;

class Kernel extends \Shopware\Core\Kernel
{
    protected function initializeDatabaseConnectionVariables(): void
    {
        if ($_SERVER['INSTALL'] ?? false) {
            return;
        }

        parent::initializeDatabaseConnectionVariables();
    }

    protected function getKernelParameters(): array
    {
        $kernelParameters = parent::getKernelParameters();
        $kernelParameters['kernel.vendor_dir'] = dirname((new \ReflectionClass(ClassLoader::class))->getFileName(), 2);

        return $kernelParameters;
    }
}
