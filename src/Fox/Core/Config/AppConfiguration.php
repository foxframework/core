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

namespace Fox\Core\Config;


abstract class AppConfiguration
{
    private string $srcDir = 'src';
    private string $tmpDir = 'tmp';
    private string $publicDir = 'public';
    private string $controllersDir;
    private string $templatesDir;
    private bool $debug = false;
    private array $extensions = [];

    public function __construct(private string $baseDir, private array $parameters)
    {
        $this->srcDir = $baseDir . '/' . $this->srcDir;
        $this->controllersDir = "$this->srcDir/App/Controllers";
        $this->templatesDir = "$this->srcDir/App/Templates";
    }


    public function getSrcDir(): string
    {
        return $this->srcDir;
    }

    public function setSrcDir(string $srcDir): AppConfiguration
    {
        $this->srcDir = $srcDir;
        return $this;
    }

    public function getTmpDir(): string
    {
        return $this->tmpDir;
    }

    public function setTmpDir(string $tmpDir): AppConfiguration
    {
        $this->tmpDir = $tmpDir;
        return $this;
    }

    public function getPublicDir(): string
    {
        return $this->publicDir;
    }

    public function setPublicDir(string $publicDir): AppConfiguration
    {
        $this->publicDir = $publicDir;
        return $this;
    }


    public function getControllersDir(): string
    {
        return $this->controllersDir;
    }

    public function setControllersDir(string $controllersDir): AppConfiguration
    {
        $this->controllersDir = $controllersDir;
        return $this;
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    public function setDebug(bool $debug): AppConfiguration
    {
        $this->debug = $debug;
        return $this;
    }

    public function getBaseDir(): string
    {
        return $this->baseDir;
    }

    public function setBaseDir(string $baseDir): AppConfiguration
    {
        $this->baseDir = $baseDir;
        return $this;
    }

    public function getParameter(string $name): string|array|null
    {
        return $this->parameters[$name] ?? null;
    }

    public function getExtensions(): array
    {
        return $this->extensions;
    }

    public function setExtensions(array $extensions): void
    {
        $this->extensions = $extensions;
    }
}
