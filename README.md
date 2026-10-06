# AI Software Engineer

An AI-powered code review and code fixing workflow built with **PHP, Symfony, Docker, Git, GitHub Actions, and Gemini**.

The project uses two AI agents:

* **AI-Code-Review-Agent** — analyzes changed PHP code and produces structured review findings.
* **AI-Code-Fix-Agent** — applies approved fixes based on the original AI review.

The main goal is to allow a developer to review changed PHP code with AI and, after explicit developer approval, safely apply AI-generated fixes.

The developer remains in control of the workflow at every stage.

## Development standards

See [docs/coding-standards.md](docs/coding-standards.md) for the project rules
covering PHP 8.4+, PSR standards, Symfony 8, testing, and secure changes.

## Getting started

The application requires Docker Compose and Git. From the workspace directory
that contains `docker-compose.yml`:

1. Copy `.env.example` to `.env` and set local database credentials.
   Generate unique values for `APP_SECRET`, `MYSQL_ROOT_PASSWORD`, and
   `MYSQL_PASSWORD`; do not reuse production credentials.
2. Start the services with `docker compose up --build -d`.
3. Configure `GEMINI_API_KEY` and `GEMINI_MODEL` in `app/.env.local`. Do not
   commit this file or expose the API key.
4. If dependencies are not already installed, run
   `docker exec agents_system composer install`.

The application is served at `http://localhost:8080`. MySQL is bound to
`127.0.0.1:3307` by default, and phpMyAdmin is not started unless explicitly
enabled with `docker compose --profile dev-tools up --build -d`; it is bound to
`127.0.0.1:8081`. Change these ports in the workspace `.env` file as needed.
The example config disables debug output; enable `APP_DEBUG=1` only for local
development. The Compose defaults use production mode with debug disabled.

The PHP container is named `agents_system`; application commands below are run
from `/var/www/app` inside that container.

---

# Architecture

```text
                         Git Changes
                              │
                              ▼
                 ┌─────────────────────────┐
                 │ AI-Code-Review-Agent     │
                 │ Code Review Agent        │
                 └────────────┬────────────┘
                              │
                              ▼
                       ReviewResult
                              │
                              ▼
                    Developer Approval
                              │
                              ▼
                 ┌─────────────────────────┐
                 │ AI-Code-Fix-Agent       │
                 │ Fix Agent               │
                 └────────────┬────────────┘
                              │
                              ▼
                    Generated PHP Source
                              │
                     ┌────────┴────────┐
                     │                 │
                     ▼                 ▼
              PHP Syntax Check   Fix Scope Validation
                     │                 │
                     └────────┬────────┘
                              │
                         Both PASS
                              │
                              ▼
                         Write File
                              │
                              ▼
                     PHPUnit Test Suite
                              │
                              ▼
                    Commit & Push Changes
```

The important safety principle is:

> **AI-generated code is never written directly to the source file.**

Generated source must pass validation before it can replace the original file.

---

# Two-Agent Model

## AI-Code-Review-Agent

The **AI-Code-Review-Agent** is responsible only for reviewing code.

It:

1. Detects changed PHP files from Git.
2. Plans changed PHP files into bounded review units and request batches.
3. Reads each unit's source and line range.
4. Sends the source and context to the LLM.
5. Produces structured review findings.
6. Reassembles unit results by file and publishes findings to the GitHub Pull Request.

The review is tied to the exact Git commit SHA being reviewed.

The review agent does **not** run again after AI-Code-Fix-Agent modifies the code.

---

## AI-Code-Fix-Agent

The **AI-Code-Fix-Agent** is responsible only for applying approved fixes.

It receives:

```text
File path
Original source
ReviewResult
```

It returns minimal JSON edits anchored to exact snippets from the original
source. The application applies those edits locally, preserving all unchanged
source verbatim instead of relying on the model to regenerate the entire file.

AI-Code-Fix-Agent does **not** perform a new code review after generating the fix.

The original review findings remain the source of truth for the fix operation.

---

# AI-Code-Review-Agent

Changed PHP files are divided into bounded units and grouped into requests to
stay within the configured prompt budget. Large files are split across units;
findings are mapped back to absolute file line numbers and combined by file.
Malformed batch responses are retried by reviewing the units individually.

A review finding contains:

```text
Line
Severity
Category
Message
Suggestion
```

Supported severities:

```text
critical
high
medium
low
```

Supported categories include:

```text
security
bug
performance
maintainability
validation
error_handling
code_smell
```

Example:

```text
[medium] error_handling

Check the $exitCode and handle the case where the Git command
fails with a non-zero exit code.
```

The review result is stored as structured JSON and associated with the reviewed commit SHA.
Each finding's reported source line is checked against the reviewed source
before it is accepted, so a model-reported line that cannot be located is
rejected rather than attached to the wrong code.

---

# AI-Code-Fix-Agent

AI-Code-Fix-Agent receives the original review findings and generates a modified source file.

The workflow is:

```text
ReviewResult
     │
     ▼
AI-Code-Fix-Agent
     │
     ▼
Exact JSON source edits
     │
     ▼
Apply edits to original source
     │
     ▼
PHP Syntax Validation
     │
     ▼
Fix Scope Validation
     │
     ▼
Write file
```

If there are no findings for a file, no unnecessary fix is generated.

The agent can retry a failed fix attempt up to **3 attempts**.

A failed attempt is not written to the source file.

---

# Developer Approval

The developer remains in control of applying AI-generated fixes.

For GitHub Pull Requests, the fix workflow runs only after the Pull Request
author comments this exact command on the Pull Request:

```text
@ai-fix approve
```

Simply reviewing or resolving a finding does not run the fix workflow. Before
approving, resolve any review comment that should no longer be fixed. Resolved
AI review threads are ignored by AI-Code-Fix-Agent, so only unresolved findings
are passed to the fixer.

The approval is processed only when it comes from the Pull Request author.

The approval is tied to the exact reviewed commit SHA.

This prevents an approval for an older review from being accidentally applied to a newer version of the Pull Request.

The workflow also consumes the approval so that the same approval cannot be executed repeatedly.

---

# GitHub Actions Workflow

The project integrates the two-agent workflow with GitHub Actions.

## Review Workflow

The review workflow runs when a Pull Request is:

```text
opened
synchronize
reopened
```

The workflow:

1. Checks out the repository with full Git history.
2. Determines the base and head commit SHAs.
3. Detects changed PHP files.
4. Runs AI-Code-Review-Agent.
5. Generates `review-result.json`.
6. Associates the result with the exact commit SHA.
7. Validates the review result.
8. Uploads the review result as a GitHub Actions artifact.
9. Publishes findings to the Pull Request.
10. Avoids creating duplicate findings.

Review findings are displayed as GitHub Pull Request comments, including inline findings where the changed line can be identified.

The review workflow resolves previous AI inline review threads before posting
the findings for a new commit. If GitHub reports that the workflow token is not
allowed to resolve threads, configure the optional `AI_REVIEW_TOKEN` repository
secret with a maintainer token that has pull-request write access. The review
itself can still complete when thread resolution is unavailable.

Both workflows prefer `AI_REVIEW_TOKEN` when it is set and otherwise use
`GITHUB_TOKEN`. A configured but revoked, expired, or otherwise invalid
`AI_REVIEW_TOKEN` will not fall back to `GITHUB_TOKEN`; update or remove that
secret if GitHub reports `Bad credentials` (HTTP 401). The fix workflow also
uses the configured token when resolving AI review threads after a successful
fix push.

The workflows are in `.github/workflows/ai-code-review.yml` and
`.github/workflows/ai-code-fix.yml`. They use `GEMINI_API_KEY` as a repository
secret and `GEMINI_MODEL` as a repository variable.

---

# Review SHA Protection

Every persisted review contains the commit SHA it was generated from.

Conceptually:

```text
ReviewResult
    │
    ├── commit_sha
    ├── base_sha
    └── reviews
```

Before AI-Code-Fix-Agent runs, the workflow verifies that:

```text
review commit SHA == current PR HEAD SHA
```

and:

```text
review base SHA == current PR base SHA
```

If the Pull Request has changed after the review, the old review cannot be used.

A new review must be generated first.

This prevents AI-Code-Fix-Agent from applying findings against source code that was not the source code originally reviewed.

---

# No-Findings Protection

If the review contains no findings, the workflow does not enter the approval/fix cycle.

The review can still be stored as an artifact for traceability, but AI-Code-Fix-Agent will not be executed for an empty review.

This prevents unnecessary AI-generated changes when the reviewer has nothing to report.

---

# Approval Replay Protection

The GitHub workflow protects against repeated execution of the same approval.

An approval is associated with:

```text
Approval comment ID
+
Reviewed commit SHA
```

Once consumed, the same approval cannot trigger another fix operation.

If another fix operation is required after the Pull Request changes, the developer must provide a new approval for the new reviewed commit.

---

# AI-Code-Fix-Agent Commit Protection

When AI-Code-Fix-Agent successfully modifies files, it creates a dedicated commit:

```text
Apply AI code fixes
```

The review workflow recognizes AI-Code-Fix-Agent commits and does not start another AI review for that commit.

This prevents the following loop:

```text
AI Review
    ↓
AI Fix
    ↓
Commit
    ↓
AI Review
    ↓
AI Fix
    ↓
...
```

Instead, the workflow stops after the approved fix has been applied and tested.

---

# Pull Request Integration

After AI-Code-Fix-Agent successfully commits and pushes changes, the existing Pull Request is updated.

The Pull Request title is generated using the original commit subject:

```text
PR-<original commit message>
```

This makes the resulting Pull Request clearly associated with the original developer change.

---

# Validation and Safety

The project uses multiple safety layers before AI-generated source can modify the repository.

## 1. PHP Syntax Validation

The generated PHP source is first checked using:

```bash
php -l
```

The source is validated using a temporary file.

If the generated source is invalid PHP:

```text
AI-Code-Fix-Agent
       │
       ▼
Invalid PHP
       │
       ▼
Syntax validation fails
       │
       ▼
File is NOT modified
```

The original source remains untouched.

---

## 2. Fix Scope Validation

After PHP syntax validation succeeds, a second validation step checks whether the generated changes remain within the scope of the original review findings.

The validator checks for changes such as:

* Unrelated refactoring
* Unnecessary formatting changes
* Unrelated comment changes
* Unrelated behavior changes
* Changes outside the original review scope

The validator returns structured JSON:

```json
{
    "approved": true,
    "reason": "The changes address the review findings without unrelated modifications."
}
```

If the scope validator rejects the generated source, the original file is not modified.

---

## Validation Order

The order is intentional:

```text
Generated source
       │
       ▼
PHP Syntax Validation
       │
       ├── FAIL → Retry / reject
       │
       ▼
Fix Scope Validation
       │
       ├── FAIL → Retry / reject
       │
       ▼
Write file
```

PHP syntax validation happens first so that an invalid generated source does not consume an unnecessary LLM request for scope validation.

---

# Retry Handling

AI-Code-Fix-Agent supports up to:

```text
3 attempts
```

A failed fix can be caused by:

* Invalid PHP syntax
* Fix scope rejection
* Other fix-generation failures

The previous failure is passed into the next fix attempt so the agent can attempt to correct the problem.

If all attempts fail, the source file remains unchanged.

---

# Gemini Integration

The project uses Google's Gemini API through a Symfony HTTP client abstraction.

The LLM layer provides:

```text
generate()
generateJson()
```

`generateJson()` requests JSON responses from Gemini using:

```text
responseMimeType: application/json
```

The Gemini client supports retry handling for transient API failures.

Currently retryable HTTP statuses are:

```text
429
503
```

The maximum number of retries is:

```text
3
```

Retry delays use exponential backoff.

Non-retryable HTTP errors, such as:

```text
400
```

fail immediately.

---

# Gemini JSON Handling

The application expects structured JSON from Gemini.

Gemini can occasionally produce an invalid JSON escape such as:

```text
\$exitCode
```

The sequence `\$` is not a valid JSON escape sequence.

The Gemini JSON response handling normalizes this specific malformed escape:

```text
\$exitCode
```

to:

```text
$exitCode
```

The behavior is covered by automated tests.

The project intentionally keeps this normalization narrow rather than attempting broad or potentially unsafe JSON rewriting.

---

# Commands

## Code Review

Review PHP changes between two Git references:

```bash
docker exec agents_system php bin/console ai:review HEAD~1 HEAD
```

Example:

```bash
docker exec agents_system php bin/console ai:review main HEAD
```

The workflow identifies changed PHP files, plans bounded source units and
batches, and combines the validated findings per file.

---

## Code Fix

Run the fix workflow for a specific Git range:

```bash
docker exec agents_system php bin/console ai:fix HEAD~1 HEAD
```

The workflow can also be executed with explicit approval:

```bash
docker exec agents_system php bin/console ai:fix HEAD~1 HEAD --approved
```

When using an existing persisted review:

```bash
docker exec agents_system php bin/console ai:fix HEAD~1 HEAD --approved --review-file=ai-review/review-result.json
```

Other review-artifact commands used by the GitHub workflow are available for
local inspection and automation:

```bash
# Bind an artifact to the reviewed head and base commits.
docker exec agents_system php bin/console ai:review:bind-result review-result.json \
  --commit-sha=<head-sha> \
  --base-sha=<base-sha>

# Validate the artifact and optionally check its expected commit SHAs.
docker exec agents_system php bin/console ai:review:validate-result review-result.json \
  --commit-sha=<head-sha> \
  --base-sha=<base-sha>

# Prepare inline and general GitHub review-comment payloads from an artifact and diff.
docker exec agents_system php bin/console ai:review:prepare-comments review-result.json changed.php.diff

# Check Gemini connectivity and credentials.
docker exec agents_system php bin/console ai:test-gemini
```

The fix workflow:

1. Loads the review findings.
2. Checks developer approval.
3. Skips files without findings.
4. Generates fixes with AI-Code-Fix-Agent.
5. Validates PHP syntax.
6. Validates fix scope.
7. Writes only validated source.
8. Retries failed fixes up to 3 attempts.

---

# Example Local Workflow

Suppose a developer changes:

```text
fixtures/test1.php
```

The developer can first run:

```bash
docker exec agents_system php bin/console ai:review HEAD~1 HEAD
```

The AI-Code-Review-Agent may produce:

```text
[low] code_smell

The function outputs directly via echo while declaring a string
return type and returning an empty string.
```

After reviewing the findings, the developer can explicitly approve the fix:

```bash
docker exec agents_system php bin/console ai:fix HEAD~1 HEAD --approved
```

AI-Code-Fix-Agent returns minimal JSON edits. The application applies them to
the original source, then validates the complete resulting file.

The generated source then passes:

```text
PHP Syntax Validation
        ↓
       PASS
        ↓
Fix Scope Validation
        ↓
       PASS
        ↓
Write File
```

Only after all required validations succeed is the original file modified.

---

# GitHub Workflow

The intended GitHub Pull Request flow is:

```text
Developer pushes code
        │
        ▼
GitHub Pull Request
        │
        ▼
AI-Code-Review-Agent
        │
        ▼
Review findings
        │
        ▼
Developer reviews findings
        │
        ▼
@ai-fix approve
        │
        ▼
Review SHA validation
        │
        ▼
AI-Code-Fix-Agent
        │
        ▼
PHP Syntax Validation
        │
        ▼
Fix Scope Validation
        │
        ▼
PHPUnit
        │
        ▼
Commit: Apply AI code fixes
        │
        ▼
Push to PR branch
```

If any required validation fails, the source is not committed.

---

# Project Structure

The application separates agents, Git integration, LLM providers, file
handling, review models, workflows, and Symfony commands. Prompt templates are
plain text and loaded by `PromptTemplateLoader`.

- `src/AI/Agent/`: code review and fix agents, plus prompt loading
- `src/AI/File/`: source-file access and PHP validation
- `src/AI/Git/`: Git change detection and command execution
- `src/AI/LLM/`: the LLM interface and Gemini implementation
- `src/AI/Review/`: review findings, results, and serialization
- `src/AI/Workflow/`: review and fix orchestration
- `src/Command/`: Symfony console commands
- `prompts/`: `code_review.txt`, `code_review_batch.txt`, `fix_agent.txt`, and
  `fix_scope_validator.txt`
- `tests/`: automated unit and workflow tests
---

# Testing

The project contains automated tests covering:

* LLM communication
* Gemini response handling
* Gemini JSON handling
* Retry behavior
* Review agents
* Fix agents
* Git integration
* File providers
* PHP source validation
* Fix scope validation
* Review workflows
* Fix workflows
* Symfony service configuration
* GitHub-oriented workflow behavior

Run the complete test suite in the PHP container:

```bash
docker exec agents_system php ./bin/phpunit tests/ --display-all-issues
```

The application requires PHP 8.4 or newer. PHPUnit also requires the `mbstring`
extension; the GitHub Actions workflows provision PHP 8.5 with `mbstring` and
`intl`.

Check Symfony service wiring with:

```bash
docker exec agents_system php bin/console lint:container
```

---

# Docker

The development environment is containerized.

The application runs inside the Docker service:

```text
agents_system
```

Examples:

```bash
docker exec agents_system php bin/console ai:review HEAD~1 HEAD
```

```bash
docker exec agents_system php ./bin/phpunit tests/ --display-all-issues
```

This keeps the PHP/Symfony development tooling inside the container rather than requiring the complete development toolchain on the host machine.

---

# Configuration

The Gemini integration requires:

```text
GEMINI_API_KEY
GEMINI_MODEL
```

For local development, set these in `app/.env.local`. In GitHub Actions, set
`GEMINI_API_KEY` as a repository secret and `GEMINI_MODEL` as a repository
variable. The optional `GEMINI_RETRY_DELAY` value sets the initial delay (in
seconds) used by Gemini's retry backoff; it defaults to `1` in the Symfony
service configuration.

GitHub thread resolution uses `AI_REVIEW_TOKEN` when configured, or falls back
to `GITHUB_TOKEN` when the secret is absent. The workflow grants
`pull-requests: write` to `GITHUB_TOKEN`; if using a separate token, ensure it
is valid and has repository access and pull-request write access. See the
[GitHub Actions Workflow](#github-actions-workflow) section for token behavior.

The Gemini model can be configured independently from the application code.

---

# Design Principles

## Human in the Loop

The AI proposes changes.

The developer decides whether those changes should be applied.

No AI-generated fix is applied merely because a review finding exists.

---

## Original Review as the Source of Truth

AI-Code-Fix-Agent works from the findings generated by the original AI-Code-Review-Agent run.

It does not start a new review after applying fixes.

This keeps the fix operation tied to a known review and avoids continuously generating new review proposals after every AI-generated change.

---

## Small Scope

AI-Code-Review-Agent reviews bounded units grouped into requests and combines
the results per file. It verifies model-reported source locations against the
submitted code.

AI-Code-Fix-Agent receives the findings relevant to the file it is fixing.

This reduces unnecessary context and helps keep changes focused.

---

## Validate Before Writing

Generated source is treated as untrusted output.

The system validates it before replacing the original source.

```text
Generated code
      │
      ├── PHP syntax validation
      │
      └── Fix scope validation
              │
              ▼
        Write only if valid
```

---

## Provider Abstraction

The agents depend on an LLM abstraction rather than directly depending on a specific AI provider.

This allows the LLM implementation to be replaced or extended without changing the agents themselves.

---

## Test Before Commit

AI-generated changes must pass the complete PHPUnit test suite before AI-Code-Fix-Agent commits and pushes them through the GitHub workflow.

```text
AI-generated changes
        │
        ▼
PHP validation
        │
        ▼
Scope validation
        │
        ▼
PHPUnit
        │
        ▼
Commit & Push
```

---

# Current Scope

The current MVP focuses on:

* PHP
* Symfony
* Git-based change detection
* AI code review
* Developer approval
* AI-generated fixes
* PHP syntax validation
* AI fix-scope validation
* Gemini integration
* Retry handling
* GitHub Pull Request integration
* GitHub Actions
* Review artifacts
* Review SHA validation
* Approval replay protection
* AI-Code-Fix-Agent commit loop protection
* Bounded review batching and source-location validation
* Docker-based development
* Automated tests

---

# Future Improvements

Possible future extensions include:

* GitLab support
* Additional programming languages:

  * Python
  * TypeScript
  * Go
* Additional LLM providers
* More advanced static analysis
* Evaluation datasets for review quality
* Review/fix quality metrics
* Human feedback collection
* More advanced GitHub Pull Request automation
* Additional production monitoring and observability

---

# MVP Definition of Done

The current MVP provides:

```text
✓ Git changes can be detected

✓ Changed PHP files can be reviewed

✓ AI-Code-Review-Agent produces structured findings

✓ Review findings are associated with a commit SHA

✓ Developer explicitly approves fixes

✓ AI-Code-Fix-Agent generates fixes

✓ Generated PHP is syntax-validated

✓ Generated changes are scope-validated

✓ Files are modified only after validation

✓ Fix attempts are limited to 3 retries

✓ Gemini transient errors are retried

✓ Invalid Gemini JSON escape handling is tested

✓ Review artifacts are persisted

✓ Stale review SHAs are rejected

✓ Empty reviews do not enter the fix cycle

✓ Approval replay is prevented

✓ AI-Code-Fix-Agent commits are protected from review loops

✓ Full PHPUnit suite passes

✓ GitHub Actions review workflow works

✓ GitHub Actions fix workflow works

✓ AI-generated changes are tested before commit

✓ Docker environment works

✓ End-to-end review/fix flow works

✓ Project documentation is available
```

---

# Project Goal

The project is designed as a foundation for a larger AI-assisted software engineering platform while keeping the first version intentionally small, testable, and understandable.

The current architecture establishes a clear separation between:

```text
Review
   │
   ▼
Human Approval
   │
   ▼
Fix
   │
   ▼
Validation
   │
   ▼
Tests
   │
   ▼
Commit
```

This provides a controlled foundation for extending the system to additional programming languages, repositories, LLM providers, and software-engineering workflows.
