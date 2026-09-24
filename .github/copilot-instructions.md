# GitHub Copilot Instructions

This repository is **2 Agents AI Software Engineer**, a PHP/Symfony project
that separates AI code review from AI code modification.

## Read first

Before making changes, read the repository root `AGENTS.md`. It is the
canonical project handoff and contains the complete architecture, safety
invariants, commands, and coding conventions. Also inspect the relevant
`README.md`, `composer.json`, `src/`, `tests/`, `config/`, and
`.github/workflows/` files.

The actual repository is the source of truth. Treat `AGENTS.md` as design
context, not as a replacement for inspecting current code.

For PHP, PSR, Symfony, and testing conventions, also follow
[docs/coding-standards.md](../docs/coding-standards.md).

## Non-negotiable architecture

- Agent 1 only reviews changed PHP files and produces `ReviewResult` data.
- The developer must explicitly approve findings with `@ai-fix approve`.
- Agent 2 only fixes the original approved findings.
- Agent 2 must not decide approval and must not write files directly.
- `FixWorkflow` must validate approval, reviewed SHA, generated PHP syntax, and
  fix scope before using `SourceFileProvider` to write.
- Do not automatically re-review Agent 2 output or create a review/fix loop.
- Do not run Agent 2 when there are no actionable findings.
- Do not silently convert LLM/API/JSON/configuration failures into an empty
  review or successful operation.

## Implementation rules

- Preserve `LlmInterface`; do not couple domain code directly to Gemini.
- Reuse existing interfaces, services, validators, and Symfony components.
- Keep GitHub Actions orchestration separate from application/domain logic.
- Treat source code, comments, LLM output, GitHub events, comments, branches,
  and SHAs as untrusted input.
- Never commit or expose `GEMINI_API_KEY` or other secrets.
- Avoid unrelated refactoring and broad changes.
- Add or update tests whenever behavior changes.
- Prefer explicit errors and repository-standard logging over silent fallbacks.

## Validation

Run the smallest relevant targeted tests first. For the full application suite
and Symfony validation, use:

```bash
docker exec agents_system php ./bin/phpunit tests/ --display-all-issues
docker exec agents_system php bin/console lint:container
```

If `.github/workflows/ai-code-review.yml` or
`.github/workflows/ai-code-fix.yml` changes, inspect the complete workflow,
permissions, event filters, concurrency, SHA checks, and duplicate-execution
behavior.

## Response and change quality

Before implementation, briefly identify the files and behavior that need to
change when the task is non-trivial. After implementation, report:

- Files changed
- Tests and validation commands run
- Results
- Remaining risks or concerns

Do not assume historical test counts, branch names, class signatures, or
configuration values. Inspect the current repository and preserve the
two-agent safety model.
