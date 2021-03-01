<?php
/*
 * MIT License
 *
 * Copyright (c) 2021 Petr Ploner <petr@ploner.cz>
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

namespace Fox\Helpers;


use Fox\Attribute\TypedArray;
use Fox\Http\UnknownBodyArgumentException;
use ReflectionClass;
use ReflectionMethod;

class RequestBody
{
    public static function getAllFilteredPostItems(int $filter = FILTER_UNSAFE_RAW, array $filterOptions = []): array
    {
        $retArr = [];
        foreach ($_POST as $key => $item) {
            $retArr[$key] = filter_var($item, $filter, $filterOptions);
        }

        return $retArr;
    }

    public static function getPostOrJsonBody(): ?array
    {
        if (!empty($_POST)) {
            return self::getAllFilteredPostItems();
        }

        $json = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $json;
        }

        return null;
    }

    public static function instanceDAOFromBody(string $DAOClass, ?array $requestBody): ?object
    {
        $reflection = new ReflectionClass($DAOClass);
        $requiredProperties = self::getRequiredProperties($reflection);
        $DAOInstance = self::createDAOInstance($reflection, $requiredProperties, $requestBody);
        $optionalArgs = array_diff(array_keys($requestBody ?? []), array_keys($requiredProperties ?? []));
        self::setOptionalValues($DAOInstance, $optionalArgs, $reflection, $requestBody);

        return $DAOInstance;
    }

    private static function setOptionalValues(object $DAOInstance,
                                              array $optionalArgs,
                                              ReflectionClass $reflectionClass,
                                              array $requestBody): void
    {
        $setters = [];
        foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
            if (str_starts_with($reflectionMethod->getName(), 'set')) {
                $lowerVar = strtolower(str_replace('set', '', $reflectionMethod->getName()));
                $type = $reflectionMethod->getParameters()[0]?->getType()?->getName() ?? null;
                $typedArray = $type === 'array' ? $reflectionMethod->getAttributes(TypedArray::class)[0]?->newInstance() : null;
                $setters[$lowerVar] = [$type, $reflectionMethod->getName(), $typedArray];
            }
        }

        $settersKeys = array_keys($setters);

        foreach ($optionalArgs as $optionalArg) {
            $lowerizedKey = strtolower($optionalArg);
            if (!in_array($lowerizedKey, $settersKeys)) {
                throw new UnknownBodyArgumentException($optionalArg);
            }

            list($type, $name, $typedArray) = $setters[$lowerizedKey];

            /** @var $typedArray TypedArray|null */
            if ($typedArray !== null) {
                $value = [];
                foreach ($requestBody[$optionalArg] as $item) {
                    $value[] = self::instanceDAOFromBody($typedArray->className, $item);
                }
            } else {
                $value = str_contains($type, 'DAO') ? self::instanceDAOFromBody($type, $requestBody[$optionalArg]) : $requestBody[$optionalArg];
            }
            call_user_func([$DAOInstance, $name], $value);
        }
    }

    private static function createDAOInstance(ReflectionClass $reflectionClass, array $requiredProperties, ?array $requestBody): object
    {
        foreach ($requiredProperties as $key => list($type, $value)) {
            if (str_contains($type, 'DAO') && isset($requestBody[$key]) && !empty($requestBody[$key])) {
                $requiredProperties[$key] = self::instanceDAOFromBody($type, $requestBody[$key]);
                continue;
            }

            $typedArrays = $reflectionClass->getConstructor()?->getAttributes(TypedArray::class);
            if ($type === 'array' && $typedArrays !== null) {
                $arrayType = null;
                foreach ($typedArrays as $typedArray) {
                    /** @var TypedArray $attrInst */
                    $attrInst = $typedArray->newInstance();
                    if ($attrInst->variable === $key) {
                        $arrayType = $attrInst->className;
                        break;
                    }
                }

                $newArray = [];
                foreach ($requestBody[$key] as $item) {
                    $newArray[] = self::instanceDAOFromBody($arrayType, $item);
                }

                $requiredProperties[$key] = $newArray;
                continue;
            }
            $requiredProperties[$key] = $requestBody[$key] ?? null;
        }

        return $reflectionClass->newInstanceArgs($requiredProperties);
    }

    private static function getRequiredProperties(ReflectionClass $reflectionClass): array
    {
        if (!method_exists($reflectionClass->getName(), '__construct')) {
            return [];
        }

        $parameters = [];
        $constructParameters = $reflectionClass->getMethod('__construct')->getParameters();
        foreach ($constructParameters as $constructParameter) {
            try {
                $default = $constructParameter->getDefaultValue();
            } catch (\ReflectionException) {
                $default = null;
            }
            $parameters[$constructParameter->getName()] = [$constructParameter->getType()->getName(), $default];
        }

        return $parameters;
    }
}