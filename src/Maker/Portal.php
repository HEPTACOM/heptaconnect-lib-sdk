<?php declare(strict_types=1);

namespace Heptacom\HeptaConnect\Sdk\Maker;

class Portal
{
    private const TEMPLATE_EMITTER = <<<'PHP'
<?php
declare(strict_types=1);

namespace ___NAMESPACE___\Emitter;

use Heptacom\HeptaConnect\Dataset\Base\Contract\DatasetEntityContract;
use Heptacom\HeptaConnect\Portal\Base\Emission\Contract\EmitContextInterface;
use Heptacom\HeptaConnect\Portal\Base\Emission\Contract\EmitterContract;
use Heptacom\HeptaConnect\Portal\Base\Mapping\Contract\MappingInterface;
use ___NAMESPACE___\Packer\ExamplePacker;

class ExampleEmitter extends EmitterContract
{
    public function supports(): string
    {
        // TODO: Return FQCN of supported entity type.
    }

    protected function run(MappingInterface $mapping, EmitContextInterface $context): ?DatasetEntityContract
    {
        $primaryKey = $mapping->getExternalId();

        $container = $context->getContainer();
        /** @var ExamplePacker $examplePacker */
        $examplePacker = $container->get(ExamplePacker::class);

        // TODO: Read from your data source using the primary key.
        // some file / database / api magic ...
        $data = [];

        // TODO: Convert the data into an entity object and return it.
        return $examplePacker->pack($data);
    }
}

PHP;

    private const TEMPLATE_EXPLORER = <<<'PHP'
<?php
declare(strict_types=1);

namespace ___NAMESPACE___\Explorer;

use Heptacom\HeptaConnect\Portal\Base\Exploration\Contract\ExploreContextInterface;
use Heptacom\HeptaConnect\Portal\Base\Exploration\Contract\ExplorerContract;
use ___NAMESPACE___\Packer\ExamplePacker;

class ExampleExplorer extends ExplorerContract
{
    public function supports(): string
    {
        // TODO: Return FQCN of supported entity type.
    }

    protected function run(ExploreContextInterface $context): iterable
    {
        $container = $context->getContainer();
        /** @var ExamplePacker $examplePacker */
        $examplePacker = $container->get(ExamplePacker::class);

        // TODO: Either yield primary keys or full entity objects from your data source.
        // some file / database / api magic ...

        foreach ([] as $data) {
            yield $examplePacker->pack($data);
        }
    }
}

PHP;

    private const TEMPLATE_PACKER = <<<'PHP'
<?php
declare(strict_types=1);

namespace ___NAMESPACE___\Packer;

use Heptacom\HeptaConnect\Dataset\Base\Contract\DatasetEntityContract;

class ExamplePacker
{
    public function pack(array $source): DatasetEntityContract
    {
        // TODO: Convert raw data from your data source into an entity.
    }
}

PHP;

    private const TEMPLATE_RECEIVER = <<<'PHP'
<?php
declare(strict_types=1);

namespace ___NAMESPACE___\Receiver;

use Heptacom\HeptaConnect\Dataset\Base\Contract\DatasetEntityContract;
use Heptacom\HeptaConnect\Portal\Base\Mapping\Contract\MappingInterface;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiveContextInterface;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiverContract;
use ___NAMESPACE___\Unpacker\ExampleUnpacker;

class ExampleReceiver extends ReceiverContract
{
    public function supports(): string
    {
        // TODO: Return FQCN of supported entity type.
    }

    protected function run(
        MappingInterface $mapping,
        DatasetEntityContract $entity,
        ReceiveContextInterface $context
    ): void {
        $container = $context->getContainer($mapping);
        /** @var ExampleUnpacker $exampleUnpacker */
        $exampleUnpacker = $container->get(ExampleUnpacker::class);

        // TODO: Convert the entity object into raw data.
        $data = $exampleUnpacker->unpack($entity);

        // TODO: Wite data to your data target.
        // some file / database / api magic ...

        // TODO: Assign a primary key from your data target to the entity.
        // $entity->setPrimaryKey();
    }
}

PHP;

    private const TEMPLATE_UNPACKER = <<<'PHP'
<?php
declare(strict_types=1);

namespace ___NAMESPACE___\Unpacker;

use Heptacom\HeptaConnect\Dataset\Base\Contract\DatasetEntityContract;

class ExampleUnpacker
{
    public function unpack(DatasetEntityContract $source): array
    {
        // TODO: Convert an entity into raw data for your data target.
    }
}

PHP;

    private const TEMPLATE_PORTAL = <<<'PHP'
<?php
declare(strict_types=1);

namespace ___NAMESPACE___;

use Heptacom\HeptaConnect\Portal\Base\Emission\EmitterCollection;
use Heptacom\HeptaConnect\Portal\Base\Exploration\ExplorerCollection;
use Heptacom\HeptaConnect\Portal\Base\Portal\Contract\PortalContract;
use Heptacom\HeptaConnect\Portal\Base\Reception\ReceiverCollection;
use Psr\Container\ContainerInterface as C;
use ___NAMESPACE___\Receiver\ExampleReceiver;
use ___NAMESPACE___\Unpacker\ExampleUnpacker;
use ___NAMESPACE___\Emitter\ExampleEmitter;
use ___NAMESPACE___\Explorer\ExampleExplorer;
use ___NAMESPACE___\Packer\ExamplePacker;

class Portal extends PortalContract
{
    public function getExplorers(): ExplorerCollection
    {
        return new ExplorerCollection([
            new ExampleExplorer(),
        ]);
    }

    public function getEmitters(): EmitterCollection
    {
        return new EmitterCollection([
            new ExampleEmitter(),
        ]);
    }

    public function getReceivers(): ReceiverCollection
    {
        return new ReceiverCollection([
            new ExampleReceiver(),
        ]);
    }

    public function getServices(): array
    {
        $services = parent::getServices();

        $services[ExamplePacker::class] = static fn (C $c): ExamplePacker => new ExamplePacker();
        $services[ExampleUnpacker::class] = static fn (C $c): ExampleUnpacker => new ExampleUnpacker();

        return $services;
    }
}

PHP;

    public static function make(string $sourceDir, string $namespace): void
    {
        self::makeEmitter($sourceDir.'/Emitter', $namespace);
        self::makeExplorer($sourceDir.'/Explorer', $namespace);
        self::makePacker($sourceDir.'/Packer', $namespace);
        self::makeReceiver($sourceDir.'/Receiver', $namespace);
        self::makeUnpacker($sourceDir.'/Unpacker', $namespace);
        self::makePortal($sourceDir, $namespace);
    }

    protected static function makeEmitter(string $sourceDir, string $namespace): void
    {
        self::generate(
            $sourceDir,
            'ExampleEmitter.php',
            self::TEMPLATE_EMITTER,
            ['___NAMESPACE___'],
            [$namespace]
        );
    }

    protected static function makeExplorer(string $sourceDir, string $namespace): void
    {
        self::generate(
            $sourceDir,
            'ExampleExplorer.php',
            self::TEMPLATE_EXPLORER,
            ['___NAMESPACE___'],
            [$namespace]
        );
    }

    protected static function makePacker(string $sourceDir, string $namespace): void
    {
        self::generate(
            $sourceDir,
            'ExamplePacker.php',
            self::TEMPLATE_PACKER,
            ['___NAMESPACE___'],
            [$namespace]
        );
    }

    protected static function makeReceiver(string $sourceDir, string $namespace): void
    {
        self::generate(
            $sourceDir,
            'ExampleReceiver.php',
            self::TEMPLATE_RECEIVER,
            ['___NAMESPACE___'],
            [$namespace]
        );
    }

    protected static function makeUnpacker(string $sourceDir, string $namespace): void
    {
        self::generate(
            $sourceDir,
            'ExampleUnpacker.php',
            self::TEMPLATE_UNPACKER,
            ['___NAMESPACE___'],
            [$namespace]
        );
    }

    protected static function makePortal(string $sourceDir, string $namespace): void
    {
        self::generate(
            $sourceDir,
            'Portal.php',
            self::TEMPLATE_PORTAL,
            ['___NAMESPACE___'],
            [$namespace]
        );
    }

    private static function generate(
        string $directory,
        string $fileName,
        string $template,
        array $placeholders = [],
        array $replacements = []
    ): void {
        if (!\is_dir($directory) && !\mkdir($directory, 0775, true)) {
            throw new \Exception(\sprintf('A portal could not be created, because the source directory is not writable: %s', $directory));
        }

        $fileContent = \str_replace($placeholders, $replacements, $template);

        \file_put_contents($directory.'/'.$fileName, $fileContent);
    }
}
