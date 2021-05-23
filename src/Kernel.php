<?php

declare(strict_types=1);

namespace Heptacom\HeptaConnect\Sdk;

use Composer\Autoload\ClassLoader;
use Heptacom\HeptaConnect\Bridge\ShopwarePlatform\AbstractIntegration;
use Heptacom\HeptaConnect\Bridge\ShopwarePlatform\Bundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class Kernel extends \Shopware\Core\Kernel
{
    public function boot(): void
    {
        if ($this->booted === false) {
            \chmod(__DIR__.'/../config/jwt/private.pem', 0660);
            \chmod(__DIR__.'/../config/jwt/public.pem', 0660);
        }

        parent::boot();
    }

    public function registerBundles()
    {
        yield from parent::registerBundles();

        yield new Bundle();
        yield new AbstractIntegration(
            true,
            \dirname((new \ReflectionClass(AbstractIntegration::class))->getFileName())
        );
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        parent::configureContainer($container, $loader);

        $confDir = __DIR__ . '/../config';

        $loader->load($confDir . '/{packages}/*' . self::CONFIG_EXTS, 'glob');
        $loader->load($confDir . '/{packages}/' . $this->environment . '/**/*' . self::CONFIG_EXTS, 'glob');
        $loader->load($confDir . '/{services}' . self::CONFIG_EXTS, 'glob');
        $loader->load($confDir . '/{services}_' . $this->environment . self::CONFIG_EXTS, 'glob');
    }

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
        $kernelParameters['kernel.vendor_dir'] = \dirname((new \ReflectionClass(ClassLoader::class))->getFileName(), 2);

        return $kernelParameters;
    }
}
