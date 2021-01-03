<?php


namespace Fox\Http;


class UnknownBodyArgumentException extends BadRequestException
{

    public function __construct(string $argument)
    {
        parent::__construct("Unknown body argument $argument");
    }
}