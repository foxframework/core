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
 *  SOFTWARE.
 *
 */

namespace Fox\Core\Helpers;


class Encryptor
{

    private const METHOD = 'AES-256-CBC';

    public static function encrypt(string $key, string $content): string
    {
        $length = openssl_cipher_iv_length(self::METHOD);
        $iv = openssl_random_pseudo_bytes($length);
        $encrypted = openssl_encrypt($content, self::METHOD, $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($encrypted) . '|' . base64_encode($iv);
    }

    public static function decrypt(string $key, string $content): string
    {
        list($data, $iv) = explode('|', $content);
        $iv = base64_decode($iv);
        $decrypted = openssl_decrypt($data, self::METHOD, $key, 0, $iv);
        if ($decrypted) {
            return $decrypted;
        }

        throw new CanNotDecryptException();
    }

}