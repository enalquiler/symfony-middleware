<?php

namespace Enalquiler\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Kernel;

final class SymfonyMiddleware implements MiddlewareInterface
{
    /**
     * @var Kernel
     */
    private $symfonyApp;

    /**
     * @var bool
     */
    private $booted = false;

    public function __construct(Kernel $app)
    {
        $this->symfonyApp = $app;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $httpFoundationFactory = new HttpFoundationFactory();
        $psr7Factory = new DiactorosFactory();
        
        if (!$this->booted) {
            $this->booted = true;
            $this->symfonyApp->boot();
            $dispatcher = $this->symfonyApp->getContainer()->get('event_dispatcher');
            $dispatcher->addListener(
                'kernel.exception',
                function (GetResponseForExceptionEvent $event) use ($request, $handler, $httpFoundationFactory) {
                    if ($event->getException() instanceof NotFoundHttpException) {
                        $psr7Response = $handler->handle($request);
                        $response = $httpFoundationFactory->createResponse($psr7Response);
                        $event->allowCustomResponseCode();
                        $event->setResponse($response);
                    }
                }
            );
        }

        $httpFoundationRequest = $httpFoundationFactory->createRequest($request);
        $httpFoundationResponse = $this->symfonyApp->handle($httpFoundationRequest);
        $this->symfonyApp->terminate($httpFoundationRequest, $httpFoundationResponse);

        return $psr7Factory->createResponse($httpFoundationResponse);
    }
}