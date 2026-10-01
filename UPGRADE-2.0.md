# Upgrading from 1.x to 2.0

2.0 migrates `meritum/http` onto `georgeff/kernel` ^2.0 and is a major release with several breaking changes. This guide covers changes specific to `meritum/http` only — since `HttpKernel extends Kernel`, you're also upgrading the base kernel dependency, and most of what breaks here is a direct consequence of that. **Read [`georgeff/kernel`'s own `UPGRADE-2.0.md`](https://github.com/MikeGeorgeff/kernel/blob/main/UPGRADE-2.0.md) first** — Environment enum → interface, module namespace moves, `kernel.*` container IDs removed, `addDefinition()`/`tag()` removed, `define()` redefinition guard, PSR-14 removal, `kernel.config` → `ConfigInterface`, and the rest all apply here too and aren't repeated in this guide.

See `CHANGELOG.md` for the full list of additions that aren't covered here (most 2.0 additions are opt-in and don't require any changes to upgrade).

## Requirements

- [ ] **`georgeff/kernel` ^2.0.** `composer.json` now requires `"georgeff/kernel": "^2.0"`.

## 1. `HttpKernel` constructor: same signature change as the base kernel

`HttpKernel::__construct()` mirrors `Kernel::__construct()`'s parameter types exactly — see the base kernel guide's sections 1–2 for the full rationale. In `meritum/http` terms:

```php
// Before
use Georgeff\Kernel\Environment;
use Meritum\Http\HttpKernel;

$kernel = new HttpKernel(Environment::Production);

// After
use Georgeff\Kernel\Environment\Production;
use Meritum\Http\HttpKernel;

$kernel = new HttpKernel(new Production());
```

- [ ] Replace every `Environment::Production`/`::Staging`/`::Development`/`::Testing`/`::Local` argument to `HttpKernel`'s constructor with the matching `Environment\*` class instance.
- [ ] If you pass a custom registrar as the second constructor argument, update its type from `ServiceRegistrar` to `Contract\ContainerBuilderInterface`. If you only ever passed `null`, no change needed.

## 2. `ExceptionHandlerInterface` moved to `Contract\`

- [ ] Update the import: `Meritum\Http\Exception\ExceptionHandlerInterface` → `Meritum\Http\Contract\ExceptionHandlerInterface`. This matches the `Contract\` convention `georgeff/kernel` 2.0 uses for its own extension-point interfaces.

## 3. `run()` no longer throws when called unbooted

- [ ] If you always call `$kernel->boot()` before `$kernel->run()` (the documented 1.x pattern), no change needed — `boot()` is idempotent, so the explicit call is now optional but harmless.
- [ ] If any of your own tests assert that `run()` throws `KernelException` when the kernel hasn't been booted, that assertion is no longer true — `run()` now boots the kernel itself instead. Update or remove those tests.

## 4. `terminate()` now requires a handled request

- [ ] If you call `terminate()` directly (bypassing `run()`) without having called `handle()` first for that request, this now throws `KernelException` ("Cannot terminate an unhandled request") instead of silently running your terminating callbacks. Call `handle()` before `terminate()`:

  ```php
  $kernel->boot();

  $response = $kernel->handle($request);

  $kernel->terminate($request, $response); // requires handle() to have run first now
  $kernel->shutdown();
  ```

## 5. `'__route__'` request attribute removed

- [ ] Replace any `$request->getAttribute('__route__')` call with `$request->getAttribute(RouteInterface::class)`:

  ```php
  // Before
  $route = $request->getAttribute('__route__');

  // After
  use Meritum\Http\Routing\RouteInterface;

  $route = $request->getAttribute(RouteInterface::class);
  ```

## 6. Router failures: generic `\RuntimeException` → `RoutingException`

- [ ] If you catch `\RuntimeException` specifically around route dispatch (rather than a broader `\Throwable`/exception-handler catch), note that `RoutingException` is still an instance of `\RuntimeException`, so a broad catch continues to work unchanged.
- [ ] If you catch a more specific exception type around a string route handler that fails to resolve from the container (e.g. your DI container's own "not found" exception), that failure is now caught internally and re-thrown as `RoutingException` instead of propagating unwrapped. Catch `Meritum\Http\Exception\RoutingException` instead, and use `getPrevious()` if you need the original container exception.

## 7. `getDebugInfo()` shape change: `requestProfile` → separate `handle`/`terminate`/`run` profiles

If you parse `getDebugInfo()` output directly (dashboards, logging, tests) rather than just displaying it as-is:

- [ ] The old `requestProfile` top-level key is gone (it was a `HttpKernel`-specific `getDebugInfo()` override merging a single shared request profile). `handle()`, `terminate()`, and `run()` each now report through the base kernel's own `profiles` map instead: `getDebugInfo()['profiles']['handle']`, `['terminate']`, `['run']`, alongside `['boot']`/`['shutdown']`.
- [ ] `['terminate']` only appears once `terminate()` has actually run for the current request — but unlike 1.x, it appears whether `terminate()` was called via `run()` or standalone, so this is more consistently available than before, not less.
- [ ] New keys to be aware of, not migrations: `getDebugInfo()['components']['routes']` and `['middleware']` — present as soon as debug mode is enabled, reflecting whatever's registered via `addRoute()`/the HTTP-verb methods/`addMiddleware()`, even before `boot()`.

## 8. Middleware failures: `\InvalidArgumentException` → `MiddlewareStackException`

- [ ] If you catch `\InvalidArgumentException` around a middleware entry that resolves to something that doesn't implement `MiddlewareInterface`, catch `Meritum\Http\Exception\MiddlewareStackException` instead. It extends `\RuntimeException`, not `\InvalidArgumentException`, so the old catch no longer matches.
- [ ] If you catch your DI container's own "not found" exception around a middleware service ID that can't be resolved, that failure is now wrapped in `MiddlewareStackException` too. Use `getPrevious()` if you need the original container exception.

## 9. Duplicate routes throw at registration

- [ ] Registering two routes with the same methods and path now throws `RoutingException` (`Duplicate route METHODS PATH`) as soon as the second one is registered. In 1.x the duplicate was accepted and failed later, when the dispatcher was built, with FastRoute's `BadRouteException`. Remove the duplicate registration; if you caught `FastRoute\BadRouteException` for this, catch `RoutingException` instead. Method order and casing don't matter: `['GET', 'POST']` and `['post', 'get']` on the same path are the same route.

## 10. `Router`, `RouterFactory`, and `MiddlewareResolver` are now internal

Only relevant if you construct or extend these classes yourself rather than letting `HttpKernel` build them.

- [ ] All three are now marked `@internal` and can change in any release without notice. Their constructors have already changed in 2.0. Stop depending on them directly: register routes and middleware through `HttpKernel` (`addRoute()`, `group()`, `addMiddleware()`), and if you need to replace the whole pipeline, use `$kernel->override(RequestHandlerInterface::class, ...)`.

## Not required, but worth adopting

These are new in 2.0 and don't require any change to upgrade:

- **HTTP-verb shortcuts** — `get()`/`post()`/`put()`/`patch()`/`delete()`/`options()`/`head()` on `HttpKernelInterface`, thin wrappers over `addRoute()` for the common single-method case.
- **Route groups** — `group(string $prefix, callable $callback)` registers routes under a shared path prefix, with group-level middleware attached via `addMiddleware()` on the returned `RouteGroupInterface`. Groups nest.
- **Route caching** — `enableRouteCache(string $file)` caches FastRoute's compiled dispatch data to a file. Clearing the file when the route table changes is a deploy-time step.
- **Route introspection** — `getRoutes()` returns every registered route keyed by its `getId()`, before or after `boot()`.
- **`addExceptionHandler(callable $factory)`** — registers the exception handler via a factory that receives the container, as a shorter alternative to `define(ExceptionHandlerInterface::class, ...)->share()`. It's a thin wrapper over `define()`, so calling it twice, or alongside your own `define(ExceptionHandlerInterface::class, ...)`, throws `DefinitionException`.
- **`EmitterInterface`** — response emission now goes through the container (`SapiEmitter` remains the default). Swap in a custom implementation with a plain `define(EmitterInterface::class, ...)`, from the bootstrap or any module, for non-SAPI runtimes or tests that want to capture the response instead of emitting it. `ServerRequestInterface` can be replaced the same way.

## Verifying the upgrade

- [ ] `composer test` — full suite passes
- [ ] `composer analyze` — PHPStan clean at `level: max`
- [ ] Grep your own codebase for `Environment::`, `Meritum\Http\Exception\ExceptionHandlerInterface`, `__route__`, `requestProfile`, `BadRouteException`, `Routing\Router`, `RouterFactory`, and `MiddlewareResolver` — anything still matching needs one of the sections above. Also check any `catch (\InvalidArgumentException` near middleware registration (section 8).
- [ ] Also run through [`georgeff/kernel`'s own verification checklist](https://github.com/MikeGeorgeff/kernel/blob/main/UPGRADE-2.0.md#verifying-the-upgrade) for base-kernel-level changes.
