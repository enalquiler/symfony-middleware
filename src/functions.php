<?php

namespace Enalquiler\MiddleWare;

use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\ServerRequestFactory;

function lazy(callable $factory)
{
    return new LazyMiddleware($factory);
}