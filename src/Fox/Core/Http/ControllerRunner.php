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

namespace Fox\Core\Http;


use ArgumentCountError;
use Fox\Core\Config\AppConfiguration;
use Fox\Core\Helpers\RequestBody;
use Fox\Core\Helpers\Server;
use FoxContainerHelper;
use Psr\Container\ContainerInterface;
use ReflectionMethod;
use ReflectionParameter;
use TypeError;

class ControllerRunner
{
    private AppConfiguration $config;

    public function __construct(private ContainerInterface $container)
    {
        $this->config = $this->container->get(AppConfiguration::class);
    }

    public function handle(string $path): void
    {
        try {
            try {
                list($controller, $args) = $this->resolveController($path);
                list($method, $bodyArg) = $this->checkMethod($controller, array_keys($args));
                if ($bodyArg !== null) {
                    $args[$bodyArg] = $this->resolveBody($controller, $method, $bodyArg);
                }

                foreach ($this->config->getGlobalBeforeActions() as $beforeActionClass) {
                    /** @var BeforeAction $beforeAction */
                    $beforeAction = $this->container->get($beforeActionClass);
                    $beforeAction->handleBeforeAction($controller, $method, $args[$bodyArg] ?? null);
                }

                $result = call_user_func_array([$controller, $method], $args);

                header('Content-Type: text/plain');
                if ($result instanceof Response) {
                    http_response_code($result->getStatus());

                    if (is_object($result->getBody()) || is_array($result->getBody())) {
                        header('Content-Type: application/json');
                        echo json_encode((array)$result->getBody());
                        exit;
                    }
                    echo $result->getBody();
                    exit;
                }

                echo $result;
                exit;
            } catch (TypeError $t) {
                if ($t instanceof ArgumentCountError) {
                    throw $t;
                }
                preg_match('~\$\w+~', $t->getMessage(), $var);
                preg_match('~must be of type (.*),~', $t->getMessage(), $expected);
                preg_match('~, (.*) given~', $t->getMessage(), $given);
                $typeVar = trim($var[0], '$');
                throw new BadRequestException("The request parameter '$typeVar' expected to be '$expected[1]', '$given[1]' given.");
            }
        } catch (HttpException $e) {
            $this->handleException($e);
        }
    }

    private function resolveController(string $path): array
    {
        $path = explode('?', $path)[0];
        $availableRoutes = $this->getPossibleRoutes($path);
        foreach ($availableRoutes as $availableRoute) {
            list($checkRouteArguments, $args) = $this->checkRouteArguments($availableRoute, $path);
            if ($checkRouteArguments) {
                return [$this->container->get(FoxContainerHelper::$controllerNames[$availableRoute]), $args];
            }
        }
        throw new NotFoundException();
    }

    private function getPossibleRoutes(string $path): array
    {
        $explodedPath = explode('/', $path);
        $pathCount = count($explodedPath);
        $availableRoutes = array_filter(array_keys(FoxContainerHelper::$controllerNames), function (string $value) use ($pathCount) {
            $expectedCount = count(explode('/', $value));
            return $expectedCount === $pathCount;
        });

        if (count($availableRoutes) === 0) {
            throw new NotFoundException();
        }

        return $availableRoutes;
    }

    private function handleException(HttpException $exception)
    {
        $message = $exception->getMessage() ? "<br />Message: <strong>{$exception->getMessage()}</strong>" : '';
        echo "<h1>Error {$exception->getCode()}</h1> $message";
        http_response_code($exception->getCode());
        exit;
    }

    private function checkRouteArguments(string $route, string $path): array
    {
        $explodedRoute = explode('/', $route);
        $explodedPath = explode('/', $path);
        $args = [];

        foreach ($explodedRoute as $index => $routePart) {
            if (str_starts_with($routePart, '{')) {
                $args[trim($routePart, '{}')] = urldecode($explodedPath[$index]);
                continue;
            }

            if (strtolower($explodedPath[$index]) !== strtolower($routePart)) {
                return [false, null];
            }
        }

        return [true, $args];
    }

    private function checkMethod(object $controller, array $args): array
    {
        $method = strtolower(Server::get('REQUEST_METHOD'));
        $body = null;
        if (!method_exists($controller, $method)) {
            throw new MethodNotAllowedException();
        }

        if ($method !== 'get') {
            $r = new ReflectionMethod($controller, $method);
            foreach ($r->getParameters() as $parameter) {
                if (!in_array($parameter->getName(), $args)) {
                    $body = $parameter->getName();
                    break;
                }
            }
        }

        return [$method, $body];
    }

    private function resolveBody(object $controller, string $method, string $bodyArg): mixed
    {
        $r = new ReflectionParameter([$controller, $method], $bodyArg);
        $requestBody = RequestBody::getPostOrJsonBody();
        if ($r->getType() === null || $r->getType()->getName() === 'array') {
            return $requestBody;
        }

        $class = $r->getType()->getName();
        return RequestBody::instanceDAOFromBody($class, $requestBody);
    }
}