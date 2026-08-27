# Changelog

All notable changes to `meritum/http` are documented here.

---

## [2.0.0] — Unreleased

2.0 migrates to `georgeff/kernel` ^2.0 and is a major release with several breaking changes. This entry will be finalized when 2.0.0 actually ships; it currently reflects everything merged to the `2.x` branch so far.

### Added
- `Contract\EmitterInterface` (`emit(ResponseInterface $response): void`) — response emission now goes through the container instead of `run()` constructing `SapiEmitter` directly, so a custom emitter (non-SAPI runtimes like Swoole/RoadRunner, or a test double that captures the response instead of emitting it) can be swapped in via `define(EmitterInterface::class, ...)` before boot. `SapiEmitter` implements it and remains the default
- `HttpKernelInterface::get()`/`post()`/`put()`/`patch()`/`delete()`/`options()`/`head()` — single-method route registration shortcuts, each a thin `addRoute(['METHOD'], ...)` delegate; `addRoute()` remains the general form and the only way to bind more than one method to a route
- `HttpKernelInterface::addExceptionHandler(callable $factory): static` — registers the exception handler via a definition-compatible callable (`callable(ContainerInterface): ExceptionHandlerInterface`) instead of requiring callers to know the container-definition mechanism directly. Takes a factory rather than a bare instance specifically so exception handlers with their own dependencies can resolve them from the container, the same way any other service does
- `Exception\RoutingException` (extends `\RuntimeException`, implements `Georgeff\Kernel\Exception\KernelExceptionInterface`, uses `ThrowHelpers`) — thrown by `Router::handleFound()` when a route's string handler can't be resolved from the container, or when a resolved handler doesn't implement `RequestHandlerInterface`. Replaces the previous generic `\RuntimeException` in both cases; a container-resolution failure preserves the original exception via `getPrevious()`. Deliberately does **not** implement `HttpExceptionInterface` — it signals a route-configuration bug, not a client-facing HTTP error
- `RouteCollection`, `MiddlewareStack`, and `Route` (via `RouteInterface`, which now extends `Georgeff\Kernel\Contract\DebuggableInterface`) implement `DebuggableInterface`. `HttpKernel` registers the route collection and global middleware stack with the kernel's profiler at construction time, so `getDebugInfo()['components']['routes']`/`['middleware']` reflect whatever's been registered via `addRoute()`/the HTTP-verb methods/`addMiddleware()`, even before `boot()`
- `handle()`, `terminate()`, and `run()` each own an independent, self-contained debug profile (`getDebugInfo()['profiles']['handle']`/`['terminate']`/`['run']`), alongside the base `Kernel`'s own `boot`/`shutdown` profiles. Replaces the previous single shared `request` profile, whose ownership-transfer logic didn't extend cleanly to `terminate()` — `terminate()` now profiles correctly whether it's called via `run()` or standalone

### Changed
- **Breaking:** migrated to `georgeff/kernel` ^2.0 — `HttpKernel::__construct()` now takes `Georgeff\Kernel\Contract\EnvironmentInterface` (e.g. `new Georgeff\Kernel\Environment\Production()`) instead of the old `Environment` enum, and `Georgeff\Kernel\Contract\ContainerBuilderInterface` instead of the old `ServiceRegistrar`
- **Breaking:** `Exception\ExceptionHandlerInterface` moved to `Contract\ExceptionHandlerInterface`, matching the `Contract\` convention `georgeff/kernel` 2.0 uses for its own extension-point interfaces
- **Breaking:** `run()` now boots the kernel itself if it hasn't been booted yet (`Kernel::boot()` is idempotent), instead of throwing `KernelException` when called unbooted. `run()` is the single top-level entrypoint — it already owned `terminate()`/`shutdown()` at the tail, this makes it symmetric at the head too
- **Breaking:** `terminate()` now throws `KernelException` if called before the current request has finished `handle()` — tracked via an internal flag reset at the start of every `handle()` call, so this is scoped per request rather than a one-time "has `handle()` ever run" check
- `HttpKernel`'s private `throwIf()`/`throwIfShutdown()` helper methods removed in favor of calling `Georgeff\Kernel\Exception\KernelException::throwIf()`/`throwIfNot()` directly
- `HttpKernelInterface` no longer extends `KernelInterface` directly — `RunnableKernelInterface` already does in kernel 2.0, so the explicit extend was redundant
- `Router::handleFound()`'s "invalid route handler" failure now throws `RoutingException` instead of a generic `\RuntimeException` (see Added)

### Removed
- **Breaking:** the `'__route__'` string-key request attribute. `RouteInterface::class` is the only route attribute key now
