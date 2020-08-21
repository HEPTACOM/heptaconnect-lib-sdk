<?php declare(strict_types=1);

use Composer\Autoload\ClassLoader;
use Heptacom\HeptaConnect\Sdk\Kernel;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\DbalKernelPluginLoader;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\StaticKernelPluginLoader;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\ErrorHandler\Debug;

if (PHP_VERSION_ID < 70200) {
    echo 'Your cli is running PHP version ' . PHP_VERSION . ' but Shopware 6 requires at least PHP 7.2.0' . PHP_EOL;
    exit();
}

if (PHP_VERSION_ID < 70400) {
    echo 'Your cli is running PHP version ' . PHP_VERSION . ' but HEPTAconnect requires at least PHP 7.4.0' . PHP_EOL;
    exit();
}

set_time_limit(0);
umask(0000);

if (!class_exists(ClassLoader::class, false) || !isset($classLoader) || !($classLoader instanceof ClassLoader)) {
    $classLoader = require_once __DIR__ . '/../vendor/autoload.php';
}

$envFile = __DIR__ . '/../.env';

if (is_readable($envFile) && !is_dir($envFile)) {
    (new Dotenv(true))->load(__DIR__ . '/../.env');
}

if (!isset($_SERVER['PROJECT_ROOT'])) {
    $_SERVER['PROJECT_ROOT'] = dirname(__DIR__);
}

$input = new ArgvInput();
Debug::enable();
$pluginLoader = new StaticKernelPluginLoader($classLoader, null);

if ($input->getFirstArgument() === 'sdk:install') {
    $_SERVER['INSTALL'] = true;
}

if (!isset($_SERVER['INSTALL']) && (trim($_SERVER['DATABASE_URL'] ?? '') !== '')) {
    $pluginLoader = new DbalKernelPluginLoader($classLoader, null, \Shopware\Core\Kernel::getConnection());
}

$application = new class(new Kernel(
    'dev',
    true,
    $pluginLoader,
    'heptaconnect',
    '1.0.0-sdk',
    null,
    dirname(__DIR__)
)) extends Application
{
    const COMMAND_WHITELIST = [
        'about',
        'help',
        'list',
        'cache:clear',
        'heptaconnect:',
        'messenger:',
        'sdk:',
    ];

    public function add(Command $command)
    {
        $name = $command->getName();
        $command->setHidden(true);

        foreach (self::COMMAND_WHITELIST as $whitelisted) {
            if (mb_strpos($name, $whitelisted) === 0) {
                $command->setHidden(false);
                break;
            }
        }

        return parent::add($command);
    }
};

$application->run($input);
