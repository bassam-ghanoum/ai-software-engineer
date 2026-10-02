# PHP and Symfony Coding Standards

This project targets PHP `>=8.4` and Symfony `8.1.*`. These standards apply to
application code, commands, controllers, services, tests, and configuration
changes.

## General PHP Standards

- Follow PSR-1 for basic coding standards and PSR-4 for autoloaded namespaces.
- Follow PSR-12 for formatting. Use four spaces for indentation, one class per
  file, braces on the next line, and no trailing whitespace.
- Use `declare(strict_types=1);` in every PHP source file.
- Use explicit parameter, property, and return types. Use `mixed` only when the
  value is genuinely mixed; prefer a domain type or value object.
- Use `final` for classes that are not designed for extension.
- Prefer constructor property promotion and `readonly` for immutable services,
  DTOs, and value objects.
- Return DTOs for semantic application results instead of positional or
  associative arrays. DTOs should be `final readonly`, expose named typed
  properties or intent-revealing accessors, and validate their invariants when
  constructed.
- For public or protected application and service methods, use a DTO return
  type whenever the returned data has a meaningful, stable shape. Prefer a
  dedicated collection DTO when the result is a list or keyed collection.
  Reserve `: array` for local algorithms, private parsing details, and
  framework, transport, or serialization boundaries.
- Represent domain collections with small typed collection DTOs that implement
  `IteratorAggregate` and `Countable`; validate element types at construction
  and preserve meaningful keys such as file paths. Do not introduce a generic
  collection framework for unrelated result types.
- Keep arrays at explicit framework, transport, and serialization boundaries
  (for example, Symfony hooks and JSON payloads). Convert to or from those
  arrays at the boundary rather than passing decoded or encoded shapes through
  application services. A `toArray()` conversion belongs at that boundary,
  not in ordinary internal result handling.
- Use enums for closed sets of values instead of string or integer constants.
- Use `DateTimeImmutable` instead of mutable date objects.
- Keep methods small and focused. Name methods and variables for intent rather
  than implementation details.
- Do not use `@` error suppression, global state, hidden service locators, or
  `die()`/`exit()` in application code.
- Throw specific exceptions and preserve the original exception as the
  previous exception when translating errors.
- Do not put secrets, tokens, credentials, or sensitive source code in logs,
  fixtures, documentation, or committed configuration.

## Symfony 8 Standards

- Use dependency injection with autowiring and autoconfiguration. Depend on
  interfaces at architectural boundaries.
- Keep controllers and Console commands thin. Validate input and delegate
  business behavior to application services or workflows.
- Prefer PHP attributes for routes, commands, validation, and other Symfony
  metadata when the project supports the attribute.
- Use Symfony components for HTTP clients, validation, serialization, caching,
  locking, filesystem operations, and console output instead of custom copies.
- Inject `ParameterBagInterface` or a typed configuration object when runtime
  configuration is needed. Never read `$_ENV` or `$_SERVER` directly in domain
  code.
- Keep domain logic independent of Symfony and infrastructure where practical.
- Use `symfony/lock` for mutual exclusion. Do not implement lock files or
  process coordination with ad hoc filesystem checks.
- Use structured, contextual logging through `LoggerInterface`; never log
  credentials, API keys, prompts containing secrets, or full sensitive payloads.
- Keep service configuration explicit when it clarifies a boundary or prevents
  accidental wiring. Do not introduce a service locator to avoid defining
  dependencies.
- Add packages with Composer and let Symfony Flex configure them. Do not assume
  a package is installed; verify `composer.json` first.

## Project Architecture

- Preserve the two-agent flow: review, explicit approval, fix, validation, and
  write.
- Agents may generate content, but workflows decide whether content may be
  written. Agents must not write files directly.
- Depend on `LlmInterface`, not on the Gemini implementation, outside the LLM
  integration boundary.
- Keep GitHub-specific orchestration outside domain services.
- Treat LLM output, GitHub events, comments, branches, commit SHAs, and source
  comments as untrusted input.
- Avoid broad refactors in feature or bug-fix changes. Keep changes within the
  requested behavior and its tests.

## Testing Standards

- Add or update a test for every observable behavior change.
- Use `KernelTestCase` for service integration tests and `WebTestCase` for HTTP
  behavior. Use focused unit tests for pure domain logic.
- Test failure paths, malformed input, authorization, and boundary conditions,
  not only the successful path.
- Keep tests deterministic. Do not depend on real network services, wall-clock
  timing, or developer-specific filesystem paths.
- Run the focused test first, then the relevant full suite:

  ```bash
  docker exec agents_system php ./bin/phpunit tests/ --display-all-issues
  docker exec agents_system php bin/console lint:container
  ```

## Review Checklist

Before opening a change, verify:

- PHP files use strict types, PSR-4 namespaces, explicit types, and PSR-12
  formatting.
- Services receive dependencies through constructors and do not access a
  container directly.
- Controllers and commands contain orchestration only.
- Exceptions, logs, and user-facing errors do not leak sensitive data.
- New behavior has focused tests, including relevant failure paths.
- Generated PHP passes syntax validation before scope validation and writing.
- `lint:container` and the applicable PHPUnit tests pass.
