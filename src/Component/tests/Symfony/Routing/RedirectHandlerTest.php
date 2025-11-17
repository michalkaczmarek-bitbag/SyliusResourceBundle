<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\Resource\Tests\Symfony\Routing;

use PHPUnit\Framework\TestCase;
use Sylius\Bundle\GridBundle\Storage\FilterStorageInterface;
use Sylius\Resource\Exception\InvalidArgumentException;
use Sylius\Resource\Metadata\Create;
use Sylius\Resource\Metadata\Delete;
use Sylius\Resource\Metadata\ResourceMetadata;
use Sylius\Resource\Symfony\ExpressionLanguage\ArgumentParserInterface;
use Sylius\Resource\Symfony\Routing\Factory\RouteName\OperationRouteNameFactoryInterface;
use Sylius\Resource\Symfony\Routing\RedirectHandler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

final class RedirectHandlerTest extends TestCase
{
    private RouterInterface $router;

    private ArgumentParserInterface $argumentParser;

    private OperationRouteNameFactoryInterface $operationRouteNameFactory;

    private FilterStorageInterface $filterStorage;

    private RedirectHandler $redirectHandler;

    protected function setUp(): void
    {
        $this->router = $this->createMock(RouterInterface::class);
        $this->argumentParser = $this->createMock(ArgumentParserInterface::class);
        $this->operationRouteNameFactory = $this->createMock(OperationRouteNameFactoryInterface::class);
        $this->filterStorage = $this->createMock(FilterStorageInterface::class);

        $this->redirectHandler = new RedirectHandler(
            $this->router,
            $this->argumentParser,
            $this->operationRouteNameFactory,
            $this->filterStorage,
        );
    }

    public function testItIsInitializable(): void
    {
        $this->assertInstanceOf(RedirectHandler::class, $this->redirectHandler);
    }

    public function testItRedirectsToResourceWithIdArgumentByDefault(): void
    {
        $data = new \stdClass();
        $data->id = 'xyz';

        $operation = new Create(redirectToRoute: 'app_dummy_index');
        $resource = new ResourceMetadata(alias: 'app.book');
        $operation = $operation->withResource($resource);

        $request = $this->createMock(Request::class);

        $this->filterStorage->method('all')->willReturn([]);
        $this->router
            ->expects($this->once())
            ->method('generate')
            ->with('app_dummy_index', ['id' => 'xyz'])
            ->willReturn('/dummies');

        $this->redirectHandler->redirectToResource($data, $operation, $request);
    }

    public function testItRedirectsToResourceWithCustomIdentifierArgumentByDefault(): void
    {
        $data = new \stdClass();
        $data->code = 'xyz';

        $operation = new Create(redirectToRoute: 'app_dummy_index');
        $resource = new ResourceMetadata(alias: 'app.ok', identifier: 'code');
        $operation = $operation->withResource($resource);

        $request = $this->createMock(Request::class);

        $this->filterStorage->method('all')->willReturn([]);
        $this->router
            ->expects($this->once())
            ->method('generate')
            ->with('app_dummy_index', ['code' => 'xyz'])
            ->willReturn('/dummies');

        $this->redirectHandler->redirectToResource($data, $operation, $request);
    }

    public function testItRedirectsToResourceWithoutArgumentsAfterDeleteOperationByDefault(): void
    {
        $data = new \stdClass();
        $data->id = 'xyz';

        $operation = new Delete(redirectToRoute: 'app_dummy_index');
        $resource = new ResourceMetadata(alias: 'app.book');
        $operation = $operation->withResource($resource);

        $request = $this->createMock(Request::class);

        $this->filterStorage->method('all')->willReturn([]);
        $this->router
            ->expects($this->once())
            ->method('generate')
            ->with('app_dummy_index', [])
            ->willReturn('/dummies');

        $this->redirectHandler->redirectToResource($data, $operation, $request);
    }

    public function testItUsesFiltersFromGridStorageWhenRedirectingToAnIndexOperation(): void
    {
        $data = new \stdClass();
        $data->id = 'xyz';

        $operation = new Delete(redirectToRoute: 'app_dummy_index');
        $resource = new ResourceMetadata(alias: 'app.book');
        $operation = $operation->withResource($resource);

        $request = $this->createMock(Request::class);

        $this->filterStorage->method('all')->willReturn(['criteria' => ['enabled' => true]]);
        $this->router
            ->expects($this->once())
            ->method('generate')
            ->with('app_dummy_index', ['criteria' => ['enabled' => true]])
            ->willReturn('/dummies');

        $this->redirectHandler->redirectToResource($data, $operation, $request);
    }

    public function testItRedirectsToRoute(): void
    {
        $data = new \stdClass();

        $this->filterStorage->method('all')->willReturn([]);
        $this->router
            ->expects($this->once())
            ->method('generate')
            ->with('app_dummy_index', [])
            ->willReturn('/dummies');

        $this->redirectHandler->redirectToRoute($data, 'app_dummy_index');
    }

    public function testItThrowsAnExceptionWhenOperationHasNoResource(): void
    {
        $data = new \stdClass();
        $operation = new Create(redirectToRoute: 'app_dummy_index', name: 'app_dummy_create');
        $request = $this->createMock(Request::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Operation "app_dummy_create" has no resource, but it should.');

        $this->redirectHandler->redirectToResource($data, $operation, $request);
    }

    public function testItThrowsAnExceptionWhenOperationHasNoRouteRedirection(): void
    {
        $data = new \stdClass();
        $operation = new Create(name: 'app_dummy_create');
        $request = $this->createMock(Request::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Operation "app_dummy_create" has no redirection route, but it should.');

        $this->redirectHandler->redirectToResource($data, $operation, $request);
    }

    public function testItThrowsAnExceptionWhenTryingRedirectWithCustomArgumentsThatAreNotScalarOnes(): void
    {
        $data = new \stdClass();
        $data->code = 'xyz';

        $operation = new Create(
            redirectToRoute: 'app_dummy_index',
            redirectArguments: ['code' => 'resource.code', 'criteria' => ['foo' => 'resource.code', 'bar' => new \stdClass()]],
        );
        $resource = new ResourceMetadata(alias: 'app.book');
        $operation = $operation->withResource($resource);

        $request = $this->createMock(Request::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Parameter "bar" should be a scalar or an array.');

        $this->redirectHandler->redirectToResource($data, $operation, $request);
    }
}
