<?php

namespace Enalquiler\SymfonyMiddleware;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
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

    /**
     * SymfonyMiddleware constructor.
     *
     * @param HttpKernelInterface $app
     * @param Kernel              $symfonyApp
     */
    public function __construct(Kernel $symfonyApp)
    {
        $this->symfonyApp = $symfonyApp;
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
        $httpFoundationFactory = new HttpFoundationFactory();
        $psr7Factory = new DiactorosFactory();
        
        if (!$this->booted) {
            $this->booted = true;
            $this->symfonyApp->boot();
            $dispatcher = $this->symfonyApp->getContainer()->get('event_dispatcher');
            $dispatcher->addListener(
                'kernel.exception',
                function (GetResponseForExceptionEvent $event) use ($request, $delegate, $httpFoundationFactory) {
                    if ($event->getException() instanceof NotFoundHttpException) {
                        $psr7Response = $delegate->process($request);
                        $response = $httpFoundationFactory->createResponse($psr7Response);
                        $response->headers->set('X-Status-Code', $response->getStatusCode());
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