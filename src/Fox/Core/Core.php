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

namespace Fox\Core;


use Fox\Core\CLI\CLIException;
use Fox\Core\CLI\CommandRunner;
use Fox\Core\Config\AppConfiguration;
use Fox\Core\Config\ContainerConfiguration;
use Fox\Core\DI\ContainerService;
use Fox\Core\Helpers\Globals;
use Fox\Core\Helpers\Server;
use Fox\Core\Http\ControllerRunner;
use Psr\Container\ContainerInterface;
use Symfony\Component\Finder\Glob;
use Tracy\Debugger;

class Core
{
    private AppConfiguration $appConfiguration;
    private ContainerConfiguration $containerConfiguration;
    private ContainerService $containerService;
    private ContainerInterface $container;

    public function __construct(AppConfiguration $appConfiguration,
                                ContainerConfiguration $containerConfiguration,
    )
    {
        $this->appConfiguration = $appConfiguration;
        $this->containerConfiguration = $containerConfiguration;

        if ($appConfiguration->isDebug()) {
            Debugger::enable(Debugger::DEVELOPMENT);
        }

        $this->containerService = new ContainerService($this->appConfiguration, $this->containerConfiguration);

    }

    public function handle(): void
    {
        $this->boot();

        if (php_sapi_name() === 'cli') {
            $this->handleCLI();
        } else {
            $this->handleHTTP();
        }
    }

    private function boot(): void
    {
        $this->container = $this->containerService->initContainer();
        Globals::set('foxContainer', $this->container);
    }

    private function handleCLI(): void
    {
        if (Server::get('argc') < 2) {
            throw new CLIException('Not enough parameters, missing command name!');
        }

        $commandRunner = new CommandRunner($this->container);
        $commandRunner->runCommand(Server::get('argv')[1]);
    }

    private function handleHTTP(): void
    {
        $controllerRunner = new ControllerRunner($this->container);
        $controllerRunner->handle(Server::get('REQUEST_URI'));
    }
}
