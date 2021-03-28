<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Sdk\Command;

use Heptacom\HeptaConnect\Sdk\Service\Composer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Heptacom\HeptaConnect\Sdk\Service\Composer
 */
class ComposerTest extends TestCase
{
    public function testAddingPathRepository(): void
    {
        \copy(__DIR__.'/fixture/composerAddingPathRepository/composer_init.json', __DIR__.'/fixture/composerAddingPathRepository/composer.json');
        $composer = new Composer(__DIR__.'/fixture/composerAddingPathRepository/composer.json');
        $composer->addPathRepository('../composerRequiringPackage');

        self::assertJsonFileEqualsJsonFile(
            __DIR__.'/fixture/composerAddingPathRepository/composer_result.json',
            __DIR__.'/fixture/composerAddingPathRepository/composer.json'
        );
    }

    public function testRequiringPackage(): void
    {
        \copy(__DIR__.'/fixture/composerRequiringPackage/composer_init.json', __DIR__.'/fixture/composerRequiringPackage/composer.json');
        $composer = new Composer(__DIR__.'/fixture/composerRequiringPackage/composer.json');
        $composer->requirePackage('heptaconnect/dataset-base', '>=0.0.1');

        self::assertJsonFileEqualsJsonFile(
            __DIR__.'/fixture/composerRequiringPackage/composer_result.json',
            __DIR__.'/fixture/composerRequiringPackage/composer.json'
        );
    }

    public function testSetVersion(): void
    {
        \copy(__DIR__.'/fixture/composerSetVersion/composer_init.json', __DIR__.'/fixture/composerSetVersion/composer.json');
        $composer = new Composer(__DIR__.'/fixture/composerSetVersion/composer.json');
        $composer->setVersion('0.0.1');

        self::assertJsonFileEqualsJsonFile(
            __DIR__.'/fixture/composerSetVersion/composer_result.json',
            __DIR__.'/fixture/composerSetVersion/composer.json'
        );
        self::assertSame('0.0.1', $composer->getVersion());
    }

    public function testSetName(): void
    {
        \copy(__DIR__.'/fixture/composerSetName/composer_init.json', __DIR__.'/fixture/composerSetName/composer.json');
        $composer = new Composer(__DIR__.'/fixture/composerSetName/composer.json');
        $composer->setName('foo/bar');

        self::assertJsonFileEqualsJsonFile(
            __DIR__.'/fixture/composerSetName/composer_result.json',
            __DIR__.'/fixture/composerSetName/composer.json'
        );
        self::assertSame('foo/bar', $composer->getName());
    }

    public function testSetKeywords(): void
    {
        \copy(__DIR__.'/fixture/composerSetKeywords/composer_init.json', __DIR__.'/fixture/composerSetKeywords/composer.json');
        $composer = new Composer(__DIR__.'/fixture/composerSetKeywords/composer.json');
        self::assertEquals(['fuu', 'baz'], $composer->getKeywords());
        $composer->setKeywords(['foo', 'bar']);

        self::assertJsonFileEqualsJsonFile(
            __DIR__.'/fixture/composerSetKeywords/composer_result.json',
            __DIR__.'/fixture/composerSetKeywords/composer.json'
        );
        self::assertEquals(['foo', 'bar'], $composer->getKeywords());
    }

    public function testAddAndRemoveKeywords(): void
    {
        \copy(__DIR__.'/fixture/composerAddAndRemoveKeywords/composer_init.json', __DIR__.'/fixture/composerAddAndRemoveKeywords/composer.json');
        $composer = new Composer(__DIR__.'/fixture/composerAddAndRemoveKeywords/composer.json');
        $composer->addKeyword('foodle');
        $composer->removeKeyword('doodle');

        self::assertJsonFileEqualsJsonFile(
            __DIR__.'/fixture/composerAddAndRemoveKeywords/composer_result.json',
            __DIR__.'/fixture/composerAddAndRemoveKeywords/composer.json'
        );
        self::assertEquals(['foo', 'bar', 'foodle'], $composer->getKeywords());
    }
}
