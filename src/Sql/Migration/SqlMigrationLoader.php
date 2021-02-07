<?php

/**
 * SQL databases adapters implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 0);

namespace ServiceBus\Storage\Sql\Migration;

use Amp\Promise;
use function Amp\call;
use function Amp\File\scandir;

/**
 *
 */
final class SqlMigrationLoader
{
    /**
     * @var string
     */
    private $directory;

    /**
     * SqlMigrationLoader constructor.
     *
     * @param string $directory
     */
    public function __construct(string $directory)
    {
        $this->directory = $directory;
    }

    /**
     * Creating migration objects and sorting versions
     *
     * @return Promise<array<string, \ServiceBus\Storage\Sql\Migration\Migration>>
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
                     * @noinspection   PhpIncludeInspection
                     */
                    include_once (string) $file;

                    $name    = $file->getBasename('.' . $file->getExtension());
                    $version = \substr($name, 7);

                    /** @psalm-var class-string<Migration> $class */
                    $class = \sprintf('\%s', $name);

                    $migration = new $class;

                    /** @psalm-suppress RedundantConditionGivenDocblockType */
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
     * @return Promise<\SplFileInfo[]>
     */
    private function loadFiles(): Promise
    {
        return call(
            function (): \Generator
            {
                /** @var string[] $files */
                $files = yield scandir($this->directory);

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
