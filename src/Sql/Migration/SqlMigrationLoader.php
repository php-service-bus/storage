<?php

/**
 * SQL databases adapters implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Storage\Sql\Migration;

use Amp\Promise;
use function Amp\call;
use function Amp\File\scandir;

/**
 *
 */
final class SqlMigrationLoader
{
    /** @var string */
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

                /** @var \SplFileInfo[] $supportedFiles */
                $supportedFiles = yield from $this->loadFiles();

                foreach ($supportedFiles as $file)
                {
                    /**
                     * @psalm-suppress UnresolvableInclude
                     * @noinspection   PhpIncludeInspection
                     */
                    include_once (string) $file;

                    /** @var string $name */
                    $name    = $file->getBasename('.' . $file->getExtension());
                    $version = (string) \substr($name, 7);

                    /** @psalm-var class-string<\ServiceBus\Storage\Sql\Migration\Migration> $class */
                    $class = \sprintf('\%s', $name);

                    $migration = new $class;

                    if (($migration instanceof Migration) === false)
                    {
                        throw new \RuntimeException(
                            \sprintf('Migration must extend `%s` class', Migration::class)
                        );
                    }

                    $migrations[$version] = new $class;
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
                            return \strpos($fileName, 'Version') !== false
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
