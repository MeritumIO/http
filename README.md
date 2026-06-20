# meritum/http

Module-first PSR-15 HTTP kernel for the Meritum ecosystem.

## Requirements

- PHP 8.4+
- [`georgeff/kernel`](https://github.com/MikeGeorgeff/kernel) ^1.6

## Installation

```bash
composer require meritum/http
```

## Basic usage

```php
use Georgeff\Kernel\Environment;
use Meritum\Http\HttpKernel;

$kernel = new HttpKernel(Environment::Production);

$kernel->addRoute('GET', '/', HomeHandler::class);

$kernel->boot();
$kernel->run();
```

`run()` resolves the incoming request from globals, passes it through the middleware pipeline, emits the response, runs terminating callbacks via `terminate()`, then shuts the kernel down.

## Routing

### Registering routes

Routes must be registered before `boot()`. The handler can be a `RequestHandlerInterface` instance or a container service ID string.

```php
$kernel->addRoute('GET', '/users', ListUsersHandler::class);
$kernel->addRoute(['GET', 'HEAD'], '/users/{id}', ShowUserHandler::class);
$kernel->addRoute('POST', '/users', CreateUserHandler::class);
```

`addRoute()` returns a `RouteInterface` for further configuration.

### Route arguments

FastRoute path parameters are available on the request as a route attribute:

```php
use Meritum\Http\Routing\RouteInterface;

public function handle(ServerRequestInterface $request): ResponseInterface
{
    /** @var RouteInterface $route */
    $route = $request->getAttribute(RouteInterface::class);

    $id = $route->getArgument('id');
}
```

The route is also available under the `'__route__'` string key as a convenience alias for code that does not import `RouteInterface`.

### Route middleware

Middleware can be attached to individual routes and will run after the global stack, before the handler:

```php
$kernel->addRoute('GET', '/admin', AdminHandler::class)
       ->addMiddleware(AuthMiddleware::class)
       ->addMiddleware(RateLimitMiddleware::class);
```

## Middleware

### Global middleware

Global middleware runs on every request, before route middleware:

```php
$kernel->addMiddleware(LoggingMiddleware::class);
$kernel->addMiddleware(new CorsMiddleware());
```

Middleware can be a `MiddlewareInterface` instance or a container service ID string. Global middleware must be registered before `boot()`.

### Execution order

```
global middleware → route middleware → handler
```

## Exception handling

By default, exceptions thrown by the middleware pipeline propagate out of `handle()`. To catch them and return a response, register an `ExceptionHandlerInterface` implementation in the container before boot:

```php
use Meritum\Http\Exception\ExceptionHandlerInterface;

$kernel->define(ExceptionHandlerInterface::class, fn() => new MyExceptionHandler())->share();
$kernel->boot();
```

```php
use Meritum\Http\Exception\ExceptionHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class MyExceptionHandler implements ExceptionHandlerInterface
{
    public function handle(\Throwable $e, ServerRequestInterface $request): ResponseInterface
    {
        // build and return an error response
    }
}
```

The exception handler can check `$e instanceof HttpExceptionInterface` to distinguish HTTP errors from unexpected exceptions and access the status code and title:

```php
use Meritum\Http\Exception\HttpExceptionInterface;

if ($e instanceof HttpExceptionInterface) {
    $status = $e->getStatusCode(); // e.g. 404
    $title  = $e->getTitle();      // e.g. 'Not Found'
}
```

## HTTP exceptions

The package provides a base exception class and two concrete exceptions thrown by the router:

| Class | Status |
|---|---|
| `HttpException` | 500 |
| `NotFoundHttpException` | 404 |
| `MethodNotAllowedHttpException` | 405 |

All implement `HttpExceptionInterface` which exposes `getStatusCode()`, `getTitle()`, and `getRequest()`.

`MethodNotAllowedHttpException` exposes the allowed methods via the `$allowedMethods` property:

```php
use Meritum\Http\Exception\MethodNotAllowedHttpException;

if ($e instanceof MethodNotAllowedHttpException) {
    $allowed = $e->allowedMethods; // ['GET', 'HEAD']
}
```

Custom HTTP exceptions can extend `HttpException` and override the `$status` and `$title` properties:

```php
use Meritum\Http\Exception\HttpException;

final class UnprocessableEntityException extends HttpException
{
    protected string $title = 'Unprocessable Entity';
    protected int $status = 422;
}
```

## Terminating callbacks

Callbacks registered with `onTerminating()` run after the response has been emitted. They receive the request, response, and kernel:

```php
$kernel->onTerminating(function (
    ServerRequestInterface $request,
    ResponseInterface $response,
    KernelInterface $kernel
): void {
    // flush logs, close connections, etc.
});
```

Callbacks must be registered before `boot()`. `terminate()` does not shut the kernel down — shutdown happens automatically at the end of `run()`, after all terminating callbacks have completed.

## Handling requests directly

`handle()` can be called directly instead of going through `run()`, which is useful for testing or custom request/response lifecycles:

```php
$kernel->boot();

$response = $kernel->handle($request);

// emit, terminate, then shut down manually
$kernel->terminate($request, $response);
$kernel->shutdown();
```

When calling `handle()` directly, shutdown is your responsibility — it is not called automatically.

## Using modules

Routes, middleware, service definitions, and terminating callbacks can be registered inside a `ModuleInterface` implementation:

```php
use Georgeff\Kernel\Module\ModuleInterface;
use Meritum\Http\HttpKernelInterface;

final class ApiModule implements ModuleInterface
{
    public function register(KernelInterface $kernel): void
    {
        assert($kernel instanceof HttpKernelInterface);

        $kernel->addRoute('GET', '/api/users', ListUsersHandler::class);
        $kernel->addMiddleware(ApiAuthMiddleware::class);

        $kernel->define(ListUsersHandler::class, fn() => new ListUsersHandler())->share();
    }
}
```

```php
$kernel->addModule(new ApiModule());
$kernel->boot();
$kernel->run();
```

## License

MIT
