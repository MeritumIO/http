<?php

namespace Meritum\Http\Test\Middleware;

use PHPUnit\Framework\TestCase;
use Meritum\Http\Middleware\MiddlewareStack;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class MiddlewareStackTest extends TestCase
{
    private MiddlewareInterface $middleware;

    protected function setUp(): void
    {
        $this->middleware = new class implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request);
            }
        };
    }

    public function test_implements_iterator_aggregate(): void
    {
        $this->assertInstanceOf(\IteratorAggregate::class, new MiddlewareStack());
    }

    public function test_is_empty_by_default(): void
    {
        $this->assertTrue((new MiddlewareStack())->isEmpty());
    }

    public function test_is_not_empty_after_add(): void
    {
        $stack = new MiddlewareStack();
        $stack->add($this->middleware);

        $this->assertFalse($stack->isEmpty());
    }

    public function test_add_returns_same_instance(): void
    {
        $stack = new MiddlewareStack();

        $this->assertSame($stack, $stack->add($this->middleware));
    }

    public function test_add_accepts_string(): void
    {
        $stack = new MiddlewareStack();
        $stack->add('SomeMiddleware');

        $this->assertFalse($stack->isEmpty());
    }

    public function test_iterator_yields_added_middleware(): void
    {
        $stack = new MiddlewareStack();
        $stack->add($this->middleware);
        $stack->add('SomeMiddleware');

        $this->assertSame([$this->middleware, 'SomeMiddleware'], iterator_to_array($stack));
    }

    public function test_iterator_yields_middleware_in_insertion_order(): void
    {
        $first  = clone $this->middleware;
        $second = clone $this->middleware;

        $stack = new MiddlewareStack();
        $stack->add($first);
        $stack->add($second);

        $items = iterator_to_array($stack);

        $this->assertSame($first, $items[0]);
        $this->assertSame($second, $items[1]);
    }

    public function test_get_iterator_returns_fresh_iterator_each_time(): void
    {
        $stack = new MiddlewareStack();
        $stack->add($this->middleware);

        $iteratorA = $stack->getIterator();
        $iteratorB = $stack->getIterator();

        $this->assertNotSame($iteratorA, $iteratorB);
    }

    public function test_stack_is_spreadable(): void
    {
        $stack = new MiddlewareStack();
        $stack->add($this->middleware);
        $stack->add('SomeMiddleware');

        $array = [...$stack];

        $this->assertSame([$this->middleware, 'SomeMiddleware'], $array);
    }
}
