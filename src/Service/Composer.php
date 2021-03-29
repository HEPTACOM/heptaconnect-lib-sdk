<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Sdk\Service;

class Composer
{
    private string $file;

    public function __construct(string $file)
    {
        $this->file = $file;
    }

    public function setVersion(string $version): void
    {
        $projectComposerJson = $this->read();
        $projectComposerJson['version'] = $version;

        $this->write($projectComposerJson);
    }

    public function getVersion(): ?string
    {
        return $this->read()['version'] ?? null;
    }

    public function setName(string $name): void
    {
        $projectComposerJson = $this->read();
        $projectComposerJson['name'] = $name;

        $this->write($projectComposerJson);
    }

    public function getName(): ?string
    {
        return $this->read()['name'] ?? null;
    }

    public function setKeywords(array $keywords): void
    {
        $projectComposerJson = $this->read();
        $projectComposerJson['keywords'] = \array_values($keywords);

        $this->write($projectComposerJson);
    }

    public function addKeyword(string $keyword): void
    {
        $keywords = $this->getKeywords() ?? [];
        $keywords[] = $keyword;

        $this->setKeywords($keywords);
    }

    public function removeKeyword(string $keyword): void
    {
        $keywords = $this->getKeywords() ?? [];
        $keywords = \array_diff($keywords, [$keyword]);

        $this->setKeywords($keywords);
    }

    public function getKeywords(): ?array
    {
        return $this->read()['keywords'] ?? null;
    }

    public function setExtra(array $extra): void
    {
        $projectComposerJson = $this->read();
        $projectComposerJson['extra'] = $extra;

        $this->write($projectComposerJson);
    }

    public function setExtraValue(string $key, $value): void
    {
        $extra = $this->getExtra() ?? [];
        $extra[$key] = $value;

        $this->setExtra($extra);
    }

    public function removeExtraValue(string $key): void
    {
        $extra = $this->getExtra() ?? [];
        unset($extra[$key]);

        $this->setExtra($extra);
    }

    public function getExtra(): ?array
    {
        return $this->read()['extra'] ?? null;
    }

    public function requirePackage(string $package, string $constraint): void
    {
        $projectComposerJson = $this->read();
        $projectComposerJson['require'][$package] = $constraint;

        $this->write($projectComposerJson);
    }

    public function addPathRepository(string $directory, bool $symlink = true): void
    {
        $projectComposerJson = $this->read();
        $shouldAddRepository = true;

        if (isset($projectComposerJson['repositories'])) {
            foreach ($projectComposerJson['repositories'] as $repository) {
                if (!isset($repository['type'])) {
                    continue;
                }

                if ($repository['type'] !== 'path') {
                    continue;
                }

                if (!isset($repository['url'])) {
                    continue;
                }

                if ($repository['url'] !== $directory) {
                    continue;
                }

                $shouldAddRepository = false;
                break;
            }
        }

        if ($shouldAddRepository) {
            $projectComposerJson['repositories'][] = [
                'type' => 'path',
                'url' => $directory,
                'options' => [
                    'symlink' => $symlink,
                ]
            ];
        }

        $this->write($projectComposerJson);
    }

    private function read(): array
    {
        return \json_decode(\file_get_contents($this->file), true);
    }

    public function write(array $content): void
    {
        \file_put_contents($this->file, \json_encode($content, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES).\PHP_EOL);
    }
}
