<?php
/*
 * MIT License
 *
 * Copyright (c) 2020 Petr Ploner <petr@ploner.cz>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace Fox\Core\CLI;


use Fox\Core\Helpers\Server;
use FoxContainerHelper;
use Psr\Container\ContainerInterface;

class CommandRunner
{

    public function __construct(private ContainerInterface $container)
    {
    }

    public function runCommand(string $commandName): void
    {
        if (!array_key_exists($commandName, FoxContainerHelper::$commandNames)) {
            self::writeError("Unknown command '$commandName'", $commandName);
            exit;
        }

        /** @var FoxCommand $command */
        $command = $this->container->get(FoxContainerHelper::$commandNames[$commandName]);
        $args = Server::get('argv');
        unset($args[0]);
        call_user_func_array([$command, 'run'], array_values($args));
    }

    public static function writeError(string $message, string $commandName): void
    {
        fwrite(STDERR, "\033[31m[Fox CLI $commandName ERROR] " . $message . "\033[0m \n");
    }

    public static function writeOutput(string $message, string $commandName): void
    {
        fwrite(STDERR, "[Fox CLI $commandName] " . $message . "\n");
    }
}