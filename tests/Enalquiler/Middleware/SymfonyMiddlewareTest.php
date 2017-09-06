<?php

declare(strict_types=1);

namespace Enalquiler\Middleware;

use function GuzzleHttp\Psr7\copy_to_string;
use GuzzleHttp\Psr7\Response;
use function GuzzleHttp\Psr7\rewind_body;
use GuzzleHttp\Psr7\ServerRequest;
use Interop\Http\ServerMiddleware\DelegateInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\RouteCollectionBuilder;
use function GuzzleHttp\Psr7\stream_for;

final class SymfonyMiddlewareTest extends TestCase
{
    /** @test */
    public function givenANonExistingRouteOnSymfonyMiddleware_ItCanCatchAndDispatchIt(): void
    {
        $response = $this->executeSymfonyMiddlewareFor('/bar');
        
        assertSame('Response from delegate', $response);
    }
    
    /** @test */
    public function givenAnExistingRouteOnSymfonyMiddleware_ItCanCatchAndDispatchIt(): void
    {
        $response = $this->executeSymfonyMiddlewareFor('/foo');
        
        assertSame('Response from Symfony!', $response);
    }
    
    /** @after */
    protected function cleanCacheAndLogsFolder()
    {
        $filesystem = new Filesystem();
        $filesystem->remove([
            __DIR__ . '/cache',
            __DIR__ . '/logs'
        ]);
    }

    private function executeSymfonyMiddlewareFor($path): string
    {
        $kernel = new class('test', true) extends Kernel {
            use MicroKernelTrait;
            public function registerBundles() { return [new FrameworkBundle()]; }
            protected function configureRoutes(RouteCollectionBuilder $routes) { $routes->add('/foo', 'kernel:fooAction'); }
            protected function configureContainer(ContainerBuilder $c, LoaderInterface $loader) { $c->loadFromExtension('framework', ['secret' => uniqid('test_app_', true)]); }
            public function fooAction() { return new SymfonyResponse('Response from Symfony!'); }
        };

        $delegate = new class implements DelegateInterface {
            public function process(ServerRequestInterface $request) {
                return (new Response())->withBody(stream_for('Response from delegate'));
            }
        };

        $response = (new SymfonyMiddleware($kernel))->process(
            new ServerRequest('GET', $path),
            $delegate
        );

        rewind_body($response);

        return copy_to_string($response->getBody());
    }
}
