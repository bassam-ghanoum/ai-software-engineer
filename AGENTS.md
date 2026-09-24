# 2 Agents AI Software Engineer

This file is the canonical project context and working agreement for OpenAI
Codex and other coding agents working in this repository.

## Project purpose

This is a PHP/Symfony system that uses two deliberately separate AI agents:

1. **Agent 1 - Code Review Agent** analyzes changed PHP files and produces a
   structured `ReviewResult`.
2. **Agent 2 - Fix Agent** generates corrections only from the original,
   developer-approved review findings.

The core flow is:

```text
Review -> explicit developer approval -> fix -> validate -> write -> commit/push
```

The developer is the final authorization point. Do not collapse the two-agent
model into a self-reviewing agent or introduce an automatic review/fix loop.

## Current technology and environment

- PHP 8.5.10
- Symfony 8.1.6
- PHPUnit 13.3.3
- Docker, Docker Compose, Git, GitHub, and GitHub Actions
- Gemini is the current LLM provider
- The application must depend on `LlmInterface`, not directly on Gemini

The application repository is this directory. The local Docker service/container
is `agents_system`, and the application path inside it is `/var/www/app`.
Typical commands are run from the host with:

```bash
docker exec agents_system php ...
```

The repository and current branch are the source of truth. This document
contains architectural rules and historical context; do not assume that
historical class names, test counts, configuration, or branches are current.
Inspect the code before changing it.

## Architecture boundaries

Keep these responsibilities separate:

```text
GitClient
  -> identifies changed files
CodeReviewAgent / CodeReviewWorkflow
  -> analyzes code and produces ReviewResult
Developer
  -> explicitly approves the exact reviewed revision
FixAgent / FixWorkflow
  -> generates and orchestrates an approved fix
SourceValidator
  -> validates generated source
FixScopeValidator
  -> checks that changes stay within approved scope
SourceFileProvider
  -> writes files only after all checks pass
GitHub Actions
  -> coordinates the external workflow
```

Important abstractions include:

- `LlmInterface` and `GeminiLlm`
- `CodeReviewAgent`, `CodeReviewAgentInterface`, and `CodeReviewWorkflow`
- `ReviewFinding` and `ReviewResult`
- `FixAgent`, `FixAgentInterface`, `FixResult`, and `FixWorkflow`
- `SourceFileProviderInterface` and `LocalSourceFileProvider`
- `SourceValidatorInterface` and `PhpSourceValidator`
- `FixScopeValidatorInterface`
- `GitClient`
- `ai:review` and `ai:fix` Symfony commands

Use dependency injection and interfaces wherever practical. Agents generate
content; workflows decide whether it may be written. Agents must not write
files directly.

## Safety invariants

Every change must preserve these rules:

1. No explicit developer approval means no fix.
2. Approval must be tied to the exact SHA reviewed by Agent 1.
3. A changed PR invalidates approval for an older reviewed SHA.
4. No actionable findings means Agent 2 must not run.
5. Agent 2 must consume the original structured `ReviewResult`.
6. Generated PHP must pass syntax validation before scope validation.
7. Failed scope validation must prevent writing.
8. LLM, API, network, malformed-JSON, and configuration failures must fail
   loudly; never convert them into an empty review or successful fix.
9. Do not automatically run a fresh Agent 1 review after Agent 2.
10. Treat LLM output, source comments, GitHub events, comments, branches, and
    SHAs as untrusted input.
11. Never expose or commit API keys, generated secrets, prompts, or sensitive
    source in logs.
12. Avoid broad unrelated refactors.

The current approval protocol is exactly:

```text
@ai-fix approve
```

Do not treat `LGTM`, `approved`, or similar text as approval unless the
workflow explicitly implements that protocol. Approval handling must also
prevent duplicate execution and use appropriately narrow GitHub permissions.

## Validation and commands

The standard commands are:

```bash
docker exec agents_system php bin/console ai:review
docker exec agents_system php bin/console ai:review:bind-result review-result.json --commit-sha=HEAD_SHA --base-sha=BASE_SHA
docker exec agents_system php bin/console ai:review:validate-result review-result.json --commit-sha=HEAD_SHA --base-sha=BASE_SHA
docker exec agents_system php bin/console ai:review:prepare-comments review-result.json changed-lines.diff
docker exec agents_system php bin/console ai:fix HEAD~1 HEAD
docker exec agents_system php ./bin/phpunit tests/ --display-all-issues
docker exec agents_system php bin/console lint:container
docker exec agents_system php bin/console ai:fix --help
```

Run targeted tests first, then the full relevant suite. Do not rely on
historical test counts. If a workflow file changes, inspect the YAML and
validate the GitHub Actions behavior where possible.

The generated review artifact is conceptually:

```text
ai-review/review-result.json
```

Keep it isolated from normal repository history unless the implementation
deliberately chooses another storage strategy.

## GitHub workflows

The primary workflows are:

- `.github/workflows/ai-code-review.yml`: reviews changed PHP files on PR
  `opened`, `synchronize`, and `reopened` events.
- `.github/workflows/ai-code-fix.yml`: handles authorized PR comments and the
  `@ai-fix approve` protocol.

Keep GitHub-specific orchestration separate from domain logic. Preserve
idempotency, SHA binding, no-findings protection, duplicate-PR prevention,
auditability, and least-privilege permissions.

## Coding workflow for agents

Before editing:

1. Read this file and the nearest applicable instructions.
2. Inspect `README.md`, `composer.json`, `src/`, `tests/`, `config/`, and
   `.github/workflows/` as relevant.
3. Run `git status`, inspect the current branch, and review recent history.
4. Compare the actual implementation with this architecture; do not recreate
   historical code from this document.

While editing:

- Make the smallest complete change that satisfies the request.
- Add or update tests for behavior changes.
- Preserve explicit approval and validation gates.
- Prefer existing Symfony components, helpers, and project patterns.
- Ask before making a significant architectural change.

Before finishing, run the relevant tests and validation commands, inspect the
final diff, and report changed files, commands run, results, and remaining
concerns.

## Symfony conventions

The detailed PHP and Symfony rules are maintained in
[docs/coding-standards.md](docs/coding-standards.md). Apply those rules to all
application and test code.

Follow Symfony best practices and inspect `composer.json` before assuming
packages are installed. Do not assume Doctrine, Twig, API Platform, Messenger,
or Lock are available.

- Add capabilities with `composer require` and let Symfony Flex configure them.
- Prefer PHP attributes, autowiring, autoconfiguration, constructor property
  promotion, and `readonly` DTOs/value objects.
- Keep controllers and commands thin; delegate to services/workflows.
- Use Symfony components for validation, serialization, security, locks, queues,
  and HTTP rather than hand-rolled infrastructure.
- Use `symfony/lock` for mutual exclusion when locking is needed.
- Keep secrets in `.env.local`, GitHub Actions secrets, or the Symfony vault.
- Run `bin/console lint:container` and applicable lint commands after changes.

## Testing

Install `symfony/test-pack` only if required and absent. Service tests should
use `KernelTestCase`; HTTP tests should use `WebTestCase`. A feature is not
complete without a test exercising its observable behavior.

## Future direction

The design may later support Python, TypeScript, Go, GitLab, and additional LLM
providers such as OpenAI, Anthropic, or local models. Keep provider and
language-specific behavior behind interfaces, but do not introduce a
multi-language abstraction without a concrete requirement.
