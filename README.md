# meritum/http

Module-first PSR-15 HTTP kernel for the Meritum ecosystem.

## Requirements

- PHP 8.4+
- [`georgeff/kernel`](https://github.com/MikeGeorgeff/kernel) ^2.0

## Installation

```bash
composer require meritum/http
```

## Basic usage

```php
use Georgeff\Kernel\Environment\Production;
use Meritum\Http\HttpKernel;

$kernel = new HttpKernel(new Production());

$kernel->addRoute('GET', '/', HomeHandler::class);

$kernel->run();
```

`run()` boots the kernel if it has not been booted yet, resolves the incoming request from globals, passes it through the middleware pipeline, emits the response, runs terminating callbacks via `terminate()`, then shuts the kernel down.

## Routing

### Registering routes

Routes must be registered before `boot()`. The handler can be a `RequestHandlerInterface` instance or a container service ID string.

HTTP-verb methods are the shortest way to register a single-method route:

```php
$kernel->get('/users', ListUsersHandler::class);
$kernel->post('/users', CreateUserHandler::class);
$kernel->put('/users/{id}', ReplaceUserHandler::class);
$kernel->patch('/users/{id}', UpdateUserHandler::class);
$kernel->delete('/users/{id}', DeleteUserHandler::class);
$kernel->options('/users', UsersOptionsHandler::class);
$kernel->head('/users/{id}', ShowUserHandler::class);
```

`addRoute()` is the general form, and is the only way to bind more than one method to the same route:

```php
$kernel->addRoute(['GET', 'HEAD'], '/users/{id}', ShowUserHandler::class);
```

Both return a `RouteInterface` for further configuration.

### Route arguments

FastRoute path parameters are available on the request via the `RouteInterface::class` attribute:

```php
use Meritum\Http\Routing\RouteInterface;

public function handle(ServerRequestInterface $request): ResponseInterface
{
    /** @var RouteInterface $route */
    $route = $request->getAttribute(RouteInterface::class);

    $id = $route->getArgument('id');
}
```

### Route middleware

Middleware can be attached to individual routes and will run after the global stack, before the handler:

```php
$kernel->get('/admin', AdminHandler::class)
       ->addMiddleware(AuthMiddleware::class)
       ->addMiddleware(RateLimitMiddleware::class);
```

### Route groups

`group()` registers routes under a shared path prefix. The callback receives a route-registration object with the same `addRoute()`/HTTP-verb methods as the kernel itself:

```php
$kernel->group('/api', function ($api) {
    $api->get('/users', ListUsersHandler::class);
    $api->post('/users', CreateUserHandler::class);
});
```

`group()` returns a `RouteGroupInterface`, which is where group-level middleware is attached — via `addMiddleware()` on the return value, after the callback has already registered its routes:

```php
$kernel->group('/api', function ($api) {
    $api->get('/users', ListUsersHandler::class);
})->addMiddleware(ApiAuthMiddleware::class);
```

This works because group middleware is resolved per request, not at registration time — so it applies to every route the group produced regardless of when `addMiddleware()` was called relative to them.

Groups can be nested; each level's prefix and middleware compose with its parent's:

```php
$kernel->group('/api', function ($api) {
    $api->group('/v1', function ($v1) {
        $v1->get('/users', ListUsersHandler::class); // GET /api/v1/users
    })->addMiddleware(V1DeprecationMiddleware::class);
})->addMiddleware(ApiAuthMiddleware::class);
```

Like `addRoute()`, `group()` must be called before `boot()`.

### Route identifiers

Every route has a unique `getId()` string, derived from its methods and path (method order and casing don't matter). Registering two routes with the same methods and path throws `RoutingException`:

```php
$kernel->get('/users', ListUsersHandler::class);
$kernel->get('/users', OtherHandler::class); // throws RoutingException: Duplicate route GET /users
```

### Inspecting registered routes

`getRoutes()` returns every registered route — including ones registered inside `group()` callbacks — keyed by route ID:

```php
foreach ($kernel->getRoutes() as $id => $route) {
    // $route->getPath(), $route->getMethods(), ...
}
```

Unlike `addRoute()`/`group()`, it can be called both before and after `boot()`.

## Route caching

For deployments with a large or slow-to-build route table, `enableRouteCache()` caches the compiled route-dispatch data to a file, skipping route re-registration on every request once the cache file exists:

```php
$kernel->enableRouteCache(__DIR__ . '/../var/cache/routes.php');
$kernel->run();
```

Like `addRoute()`/`group()`, it must be called before `boot()`. The cache file is written the first time it's needed and reused as-is on every request after that — routes added or removed later aren't reflected until the file is deleted (or a different path is used) and regenerated, so clearing it is part of your deploy process whenever the route table changes.

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
global middleware → group middleware (outermost to innermost) → route middleware → handler
```

## Exception handling

By default, exceptions thrown by the middleware pipeline propagate out of `handle()`. To catch them and return a response, register an exception handler factory before boot:

```php
use Meritum\Http\Contract\ExceptionHandlerInterface;

$kernel->addExceptionHandler(fn() => new MyExceptionHandler());
$kernel->run();
```

The factory receives the container, so handlers with dependencies can resolve them the same way any other service does:

```php
use Psr\Container\ContainerInterface;

$kernel->addExceptionHandler(function (ContainerInterface $container) {
    return new MyExceptionHandler($container->get(LoggerInterface::class));
});
```

```php
use Meritum\Http\Contract\ExceptionHandlerInterface;
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

All implement `HttpExceptionInterface`, which exposes `getStatusCode()`, `getTitle()`, and `getRequest()`.

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

### Routing exceptions

`RoutingException` is thrown when a matched route can't actually be dispatched — a string handler that isn't resolvable from the container, or a resolved handler that doesn't implement `RequestHandlerInterface`. Unlike the exceptions above, it does **not** implement `HttpExceptionInterface`: it signals a bug in your route configuration rather than something the client did, so there's no meaningful status code or title to expose. It still reaches your registered exception handler like any other `Throwable`, and preserves the original failure (e.g. a container "not found" error) via `getPrevious()`.

### Middleware exceptions

`MiddlewareStackException` is thrown when a middleware entry registered by container service ID can't actually be resolved — either the container can't find it, or it resolves to something that doesn't implement `MiddlewareInterface`. Like `RoutingException`, it does **not** implement `HttpExceptionInterface` — it signals a misconfigured middleware entry rather than something the client did. It still reaches your registered exception handler like any other `Throwable`, and preserves a container resolution failure via `getPrevious()`.

### Route cache exceptions

`RouteCacheException` is thrown when [route caching](#route-caching) is enabled and the cache file can't actually be used — most commonly a corrupt or invalid cache file. Like `RoutingException` and `MiddlewareStackException`, it does **not** implement `HttpExceptionInterface` and preserves the original failure via `getPrevious()`.

## Response emission

Responses are emitted through `EmitterInterface`, resolved from the container in `run()`. The default implementation, `SapiEmitter`, writes headers and body via `header()`/`echo`. Swap it for a custom implementation before boot — useful for non-SAPI runtimes (Swoole, RoadRunner workers) or tests that want to capture the response instead of emitting it:

```php
use Meritum\Http\Contract\EmitterInterface;

$kernel->define(EmitterInterface::class, fn() => new MyEmitter())->share();
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

Callbacks must be registered before `boot()`. `terminate()` can only be called after a request has been handled — calling it before `handle()` has completed throws. `terminate()` does not shut the kernel down — shutdown happens automatically at the end of `run()`, after all terminating callbacks have completed.

## Handling requests directly

`handle()` can be called directly instead of going through `run()`, which is useful for testing or custom request/response lifecycles. Unlike `run()`, this path does not boot the kernel for you:

```php
$kernel->boot();

$response = $kernel->handle($request);

// emit, terminate, then shut down manually
$kernel->terminate($request, $response);
$kernel->shutdown();
```

When calling `handle()` directly, boot and shutdown are your responsibility — neither happens automatically.

## Debugging

Pass `debug: true` to the constructor to enable profiling and populate `getDebugInfo()`:

```php
$kernel = new HttpKernel(new Production(), debug: true);

$kernel->addRoute('GET', '/', HomeHandler::class);
$kernel->run();

$info = $kernel->getDebugInfo();
```

`boot`, `run`, `handle`, `terminate`, and `shutdown` are each tracked as independent profiles under `$info['profiles']` — every one is self-contained and appears whenever that stage actually runs, so `terminate()` still profiles correctly even when called outside `run()`. `$info['components']` includes `routes` and `middleware`, reflecting whatever was registered via `addRoute()`/the HTTP-verb methods and `addMiddleware()`.

Each route's debug info includes a `groups` key — the chain of groups it was registered under, outermost first, each with its own `prefix` and `middleware` — alongside a `middleware` key that's already merged with every enclosing group's, in the order it will actually run.

## Using modules

Routes, middleware, service definitions, and terminating callbacks can be registered inside a `ModuleInterface` implementation:

```php
use Georgeff\Kernel\KernelInterface;
use Georgeff\Kernel\Contract\ModuleInterface;
use Meritum\Http\HttpKernelInterface;

final class ApiModule implements ModuleInterface
{
    public function register(KernelInterface $kernel): void
    {
        assert($kernel instanceof HttpKernelInterface);

        $kernel->get('/api/users', ListUsersHandler::class);
        $kernel->addMiddleware(ApiAuthMiddleware::class);

        $kernel->define(ListUsersHandler::class, fn() => new ListUsersHandler())->share();
    }
}
```

```php
$kernel->addModule(new ApiModule());
$kernel->run();
```

## License

MIT
