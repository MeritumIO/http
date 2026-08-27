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

## Not required, but worth adopting

These are new in 2.0 and don't require any change to upgrade:

- **HTTP-verb shortcuts** — `get()`/`post()`/`put()`/`patch()`/`delete()`/`options()`/`head()` on `HttpKernelInterface`, thin wrappers over `addRoute()` for the common single-method case.
- **`addExceptionHandler(callable $factory)`** — registers the exception handler via a factory that receives the container, instead of calling `define(ExceptionHandlerInterface::class, ...)` directly. Existing `define()`-based registration still works unchanged; this is purely a more ergonomic entrypoint, and the only way an exception handler with its own dependencies can resolve them.
- **`EmitterInterface`** — response emission now goes through the container (`SapiEmitter` remains the default). Swap in a custom implementation via `define(EmitterInterface::class, ...)` before boot for non-SAPI runtimes or tests that want to capture the response instead of emitting it.

## Verifying the upgrade

- [ ] `composer test` — full suite passes
- [ ] `composer analyze` — PHPStan clean at `level: max`
- [ ] Grep your own codebase for `Environment::`, `Meritum\Http\Exception\ExceptionHandlerInterface`, `__route__`, and `requestProfile` — anything still matching needs one of the sections above.
- [ ] Also run through [`georgeff/kernel`'s own verification checklist](https://github.com/MikeGeorgeff/kernel/blob/main/UPGRADE-2.0.md#verifying-the-upgrade) for base-kernel-level changes.
