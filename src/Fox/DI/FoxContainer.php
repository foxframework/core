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

namespace Fox\DI;


use Fox\Config\AppConfiguration;
use Fox\Config\ContainerConfiguration;
use FoxContainerHelper;
use Psr\Container\ContainerInterface;

class FoxContainer implements ContainerInterface
{
    private const INTERNALLY_INITIALIZED_SERVICES = [AppConfiguration::class, ContainerInterface::class];
    private array $initializedServices = [];

    public function __construct(private FoxContainerHelper $containerHelper,
                                private ContainerConfiguration $containerConfiguration,
                                private AppConfiguration $appConfiguration)
    {
        $this->initializedServices[AppConfiguration::class] = $this->appConfiguration;
        $this->initializedServices[ContainerInterface::class] = $this;
    }

    public function get($id): object
    {
        if ($this->containerConfiguration->isSingleton() || in_array($id, self::INTERNALLY_INITIALIZED_SERVICES)) {
            if (array_key_exists($id, $this->initializedServices)) {
                return $this->initializedServices[$id];
            }
        }

        $service = null;
        $factoryMethod = FactoryBuilder::getFactoryMethodName($id);
        if (method_exists($this->containerHelper, $factoryMethod)) {
            $args = [];
            foreach (call_user_func([$this->containerHelper, FactoryBuilder::getFactoryHelperName($id)]) as $dependency) {
                $args[] = $this->get($dependency);
            }
            $service = call_user_func_array([$this->containerHelper, $factoryMethod], $args);
        }

        if ($service !== null) {
            if ($this->containerConfiguration->isSingleton()) {
                $this->initializedServices[$id] = $service;
            }
            return $service;
        }

        throw new ServiceNotFoundException("Service with given id $id was not found!");
    }

    public function has($id): bool
    {
        if (array_key_exists($id, $this->initializedServices)) {
            return true;
        }
        $factoryMethod = FactoryBuilder::getFactoryMethodName($id);
        return method_exists($this->containerHelper, $factoryMethod);
    }
}