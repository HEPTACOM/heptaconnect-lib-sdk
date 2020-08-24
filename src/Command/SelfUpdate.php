<?php declare(strict_types=1);

namespace Heptacom\HeptaConnect\Sdk\Command;

use Heptacom\HeptaConnect\Sdk\Composer\Composer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SelfUpdate extends Command
{
    protected static $defaultName = 'sdk:self-update';

    private string $vendorDir;

    public function __construct(string $vendorDir)
    {
        parent::__construct();
        $this->vendorDir = $vendorDir;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        if (($projectDir = \realpath($this->vendorDir . '/../')) === false) {
            $io->error(\sprintf('Unable to find the SDK project directory (%s).', $this->vendorDir . '/../'));

            return 1;
        }

        Composer::update($output, $projectDir);

        return 0;
    }
}
