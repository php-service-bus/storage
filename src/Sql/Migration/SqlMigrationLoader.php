<?php

/**
 * SQL databases adapters implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\Storage\Sql\Migration;

use Amp\Promise;
use function Amp\call;
use function Amp\File\listFiles;
use function ServiceBus\Common\createWithoutConstructor;

final class SqlMigrationLoader
{
    /**
     * @psalm-var non-empty-string
     *
     * @var string
     */
    private $directory;

    /**
     * @psalm-param non-empty-string $directory
     */
    public function __construct(string $directory)
    {
        $this->directory = $directory;
    }

    /**
     * Creating migration objects and sorting versions
     *
     * @psalm-return Promise<array<string, \ServiceBus\Storage\Sql\Migration\Migration>>
     */
    public function load(): Promise
    {
        return call(
            function (): \Generator
            {
                $migrations = [];

                /** @var \SplFileInfo[] $files */
                $files = yield $this->loadFiles();

                foreach ($files as $file)
                {
                    /**
                     * @psalm-suppress UnresolvableInclude
                     */
                    include_once (string) $file;

                    $name    = $file->getBasename('.' . $file->getExtension());
                    $version = \substr($name, 7);

                    /** @psalm-var class-string<Migration>|class-string $class */
                    $class = \sprintf('\%s', $name);

                    $migration = createWithoutConstructor($class);

                    if ($migration instanceof Migration)
                    {
                        $migrations[$version] = $migration;
                    }
                }

                \ksort($migrations);

                return $migrations;
            }
        );
    }

    /**
     * Getting list of migration files
     *
     * @psalm-return Promise<array<array-key, \SplFileInfo>>
     */
    private function loadFiles(): Promise
    {
        return call(
            function (): \Generator
            {
                /** @var string[] $files */
                $files = yield listFiles($this->directory);

                return \array_filter(
                    \array_map(
                        function (string $fileName): ?\SplFileInfo
                        {
                            return \str_contains($fileName, 'Version')
                                ? new \SplFileInfo($this->directory . '/' . $fileName)
                                : null;
                        },
                        $files
                    )
                );
            }
        );
    }
}
