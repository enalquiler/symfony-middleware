<?php

namespace Enalquiler\Middleware;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class LazyMiddleware implements MiddlewareInterface
{
    private $factory;
    private $app;

    public function __construct(callable $factory)
    {
        $this->factory = $factory;
    }

    /**
     * Process an incoming client or server request and return a response,
     * optionally delegating to the next middleware component to create the response.
     *
     * @param RequestInterface $request
     * @param DelegateInterface $delegate
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        return $this->createApp()->process($request, $delegate);
    }

    /**
     * @return MiddlewareInterface
     */
    private function createApp()
    {
        $this->app = $this->app ?: call_user_func($this->factory);

        return $this->app;
    }
}