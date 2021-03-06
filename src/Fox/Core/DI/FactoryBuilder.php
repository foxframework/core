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

namespace Fox\Core\DI;


use App\Services\Security\TestingIdentityProvider;
use Fox\Core\Attribute\Autowire;
use Fox\Core\Attribute\Command;
use Fox\Core\Attribute\Controller;
use Fox\Core\Attribute\Route;
use Fox\Core\Attribute\Service;
use Fox\Core\Config\ContainerConfiguration;
use Fox\Core\NotImplementedException;
use Nette\PhpGenerator\Method;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionException;
use SplFileInfo;

class FactoryBuilder
{

    const LICENSE_TEXT = '
MIT License
           
Copyright (c) 2020 Petr Ploner <petr@ploner.cz>
    
Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
';

    const SERVICE = 0;
    const CONTROLLER = 1;
    const COMMAND = 2;

    public static function getReflection(string $file): ?ReflectionClass
    {
        $classes = get_declared_classes();
        include_once $file;
        $diff = array_diff(get_declared_classes(), $classes);
        $class = reset($diff);

        if (empty($class)) {
            return null;
        }

        try {
            return new ReflectionClass($class);
        } catch (ReflectionException $e) {
            throw new ContainerGeneratorException("Fox Container generator can not process $file");
        }
    }

    public static function getServices(string $srcDir): array
    {
        $services = [];
        $srcDir = new RecursiveDirectoryIterator($srcDir);
        /**
         * @var $file SplFileInfo
         */
        foreach (new RecursiveIteratorIterator($srcDir) as $serviceFile => $file) {
            if ($file->getExtension() === 'php'
                && !str_contains($file->getFilename(), 'Entity')
                && !str_contains($file->getFilename(), 'DAO')) {
                $reflection = self::getReflection($serviceFile);
                if ($reflection !== null && $reflection->isInstantiable()) {
                    foreach ($reflection->getAttributes() as $attribute) {
                        if ($attribute->newInstance() instanceof Service) {
                            $services[] = $reflection;
                        }
                    }
                }
            }
        }

        return $services;
    }

    public static function addInterfaces(ReflectionClass $class, array $mappedInterfaces): array
    {
        foreach ($class->getInterfaceNames() as $interface) {
            $mappedInterfaces[$interface][] = $class->getName();
        }
        return $mappedInterfaces;
    }

    public static function createFactory(ReflectionClass $class, ContainerConfiguration $containerConfiguration, array $mappedInterfaces): array
    {

        $method = new Method(self::getFactoryMethodName($class->getName()));
        $method->setReturnType($class->getName());
        $methodHelper = new Method(self::getFactoryHelperName($class->getName()));
        $methodHelper->setReturnType('array');

        $autowire = false;
        foreach ($class->getAttributes() as $attribute) {
            if ($attribute->newInstance() instanceof Autowire) {
                $autowire = true;
                break;
            }
        }

        $parameters = [];
        if ($autowire) {
            $parameters = self::autowireService($class, $mappedInterfaces, $method);
        } else {
            throw new NotImplementedException();
        }

        $method->setBody(sprintf('return new %s(%s);', $class->getName(), join(',', $parameters['names'])));
        $methodHelper->setBody(sprintf('return [%s];', join(',', $parameters['types'])));

        return [$method, $methodHelper];
    }

    public static function getFactoryMethodName(string $class): string
    {
        return 'build' . ucfirst(str_replace('\\', '', $class));
    }

    public static function getFactoryHelperName(string $class): string
    {
        return 'help' . ucfirst(str_replace('\\', '', $class));
    }

    public static function getParameterName(string $class): string
    {
        return lcfirst(str_replace('\\', '', $class));
    }

    public static function getTypeOfService(ReflectionClass $class): array
    {
        foreach ($class->getAttributes() as $attribute) {
            $attributeInstance = $attribute->newInstance();
            if ($attributeInstance instanceof Service) {
                if ($attributeInstance instanceof Controller) {
                    $route = null;
                    foreach ($class->getAttributes() as $attr) {
                        $attrInstance = $attr->newInstance();
                        if ($attrInstance instanceof Route) {
                            $route = $attrInstance->route;
                        }
                    }
                    if ($route === null) {
                        throw new ContainerGeneratorException("Missing Route settings for %s", $class->getName());
                    }
                    return [self::CONTROLLER, $route];
                }
                if ($attributeInstance instanceof Command) {
                    return [self::COMMAND, sprintf('%s:%s', $attributeInstance->namespace, $attributeInstance->name)];
                }
                return [self::SERVICE, null];
            }
        }

        throw new ContainerGeneratorException("Class $class->name has not defined Service attribute!");
    }

    private static function autowireService(ReflectionClass $class, array $mappedInterfaces, Method $method): array
    {
        $parameters = ['names' => [], 'types' => []];
        foreach ($class->getConstructor()?->getParameters() ?? [] as $parameter) {
            if (!$parameter->hasType()) {
                throw new ContainerGeneratorException("Can not resolve constructor parameters of  $class->name > \$$parameter->name");
            }

            $parameterType = $parameter->getType()->getName();

            if (in_array($parameterType, ['string', 'array'])) {
                $parameters['names'][] = sprintf('$this->appConfig->getParameter(\'%s\')', $parameter->getName());
            } else {
                if (array_key_exists($parameterType, $mappedInterfaces)) {
                    if (count($mappedInterfaces[$parameterType]) > 1) {
                        throw new ContainerGeneratorException("Multiple definitions of $parameterType found!");
                    }
                    $parameterType = $mappedInterfaces[$parameterType][0];
                }
                $method->addParameter(self::getParameterName($parameterType))
                    ->setType($parameterType);
                $parameters['names'][] = '$' . self::getParameterName($parameterType);
                $parameters['types'][] = '\'' . $parameterType . '\'';
            }

        }
        return $parameters;
    }
}