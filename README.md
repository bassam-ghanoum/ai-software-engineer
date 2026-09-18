# 2 Agents AI Software Engineer

An AI-powered code review and code fixing workflow built with **PHP, Symfony, Docker, Git, and Gemini**.

The project uses two AI agents:

* **Agent 1 — Code Review Agent**
* **Agent 2 — Fix Agent**

The main goal is to allow a developer to review changed PHP code with AI and, after explicit developer approval, safely apply AI-generated fixes.

---

## Architecture

```text
                         Git Changes
                              │
                              ▼
                    ┌───────────────────┐
                    │      Agent 1      │
                    │   Code Reviewer   │
                    └─────────┬─────────┘
                              │
                              ▼
                       ReviewResult
                              │
                              ▼
                     Developer Approval
                              │
                              ▼
                    ┌───────────────────┐
                    │      Agent 2      │
                    │     Fix Agent     │
                    └─────────┬─────────┘
                              │
                              ▼
                    Generated PHP Source
                              │
                    ┌─────────┴─────────┐
                    │                   │
                    ▼                   ▼
             PHP Syntax Check     Scope Validation
                    │                   │
                    └─────────┬─────────┘
                              │
                         Both PASS
                              │
                              ▼
                         Write File
```

The important safety principle is:

> **AI-generated code is never written directly to the source file.**

The generated source must pass validation before it can replace the original file.

---

# Features

## Agent 1 — Code Review

Agent 1 analyzes changed PHP files independently.

It:

1. Reads the Git diff.
2. Identifies changed PHP files.
3. Reads the current source.
4. Sends the source and file path to the LLM.
5. Produces structured review findings.

Each finding contains information such as:

* Severity
* Category
* Message
* Suggested fix

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

---

# Agent 2 — Code Fix

Agent 2 receives:

```text
File path
Original source
ReviewResult
```

It generates a corrected version of the source code.

Agent 2 does **not** write the generated code directly to the file.

Instead:

```text
ReviewResult
     │
     ▼
   FixAgent
     │
     ▼
Generated source
     │
     ▼
Validation
     │
     ▼
Write file
```

If there are no review findings, no unnecessary fix is generated.

---

# Developer Approval

The developer remains in control of the workflow.

The fixes are only applied after explicit approval.

Example:

```text
Apply these fixes? [y/N]
```

If the developer rejects the fixes, no files are modified.

For non-interactive execution:

```bash
php bin/console ai:fix HEAD~1 HEAD --no-interaction
```

The workflow reports:

```text
Fixes were not approved. No files were modified.
```

---

# Validation and Safety

The project uses two validation layers before modifying a source file.

## 1. PHP Syntax Validation

The generated source is written to a temporary file and checked with:

```bash
php -l
```

If the generated source is invalid PHP:

```text
Agent 2
   ↓
Invalid PHP
   ↓
Syntax validation fails
   ↓
File is NOT modified
```

The original source remains untouched.

---

## 2. Fix Scope Validation

A second LLM validation step checks whether the generated changes remain within the scope of the original review findings.

The validator is instructed to reject:

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

The Gemini client also implements retry handling for transient API failures.

Currently retryable HTTP statuses are:

```text
429
503
```

The client supports configurable retry delay and exponential backoff.

The maximum number of retries is:

```text
3
```

Non-retryable errors, such as HTTP 400, fail immediately.

---

# Commands

## Code Review

Review PHP changes between two Git references:

```bash
php bin/console ai:review HEAD~1 HEAD
```

Example:

```bash
php bin/console ai:review main HEAD
```

The workflow identifies changed PHP files and reviews each file independently.

---

## Code Fix

Run the complete review-and-fix workflow:

```bash
php bin/console ai:fix HEAD~1 HEAD
```

The command:

1. Reviews the changed files.
2. Displays the findings.
3. Requests developer approval.
4. Generates fixes using Agent 2.
5. Validates the generated PHP.
6. Validates the fix scope.
7. Writes the file only when validation succeeds.

---

# Example Workflow

Suppose a developer changes:

```text
fixtures/test1.php
```

and introduces a problem.

The developer runs:

```bash
php bin/console ai:fix HEAD~1 HEAD
```

Agent 1 produces a finding such as:

```text
[LOW] code_smell

The function outputs directly via echo while declaring a string
return type and returning an empty string, mixing side effects
with return values.
```

The developer approves:

```text
Apply these fixes? [y/N] y
```

Agent 2 generates the corrected source.

The generated source then passes:

```text
PHP Syntax Validation
        ↓
        PASS

Fix Scope Validation
        ↓
        PASS
```

Only then is the file written.

---

# Project Structure

The project follows a separation between Git integration, AI agents, LLM providers, file handling, validation, and workflows.

Conceptually:

```text
src/
├── AI/
│   ├── Agent/
│   │   ├── CodeReviewAgent/
│   │   └── FixAgent/
│   │
│   ├── File/
│   │   ├── SourceFileProvider
│   │   └── PhpSourceValidator
│   │
│   ├── LLM/
│   │   ├── LlmInterface
│   │   ├── GeminiLlm
│   │   └── OpenAiLlm
│   │
│   ├── Review/
│   │   └── ReviewResult / ReviewFinding
│   │
│   └── Workflow/
│       ├── CodeReviewWorkflow
│       └── FixWorkflow
│
└── Command/
    ├── AiReviewCommand
    └── AiFixCommand
```

The exact project structure may evolve as additional providers and languages are introduced.

---

# Testing

The project contains unit and integration tests covering:

* LLM communication
* Gemini response handling
* Retry behavior
* Review agents
* Fix agents
* File providers
* Source validation
* Review workflows
* Fix workflows

Run the complete test suite:

```bash
php ./bin/phpunit tests/ --display-all-issues
```

Current project result:

```text
62 tests
244 assertions
0 failures
0 errors
```

---

# Docker

The development environment is containerized.

The application runs inside the Docker service:

```text
agents_system
```

Example:

```bash
docker exec agents_system php ./bin/phpunit tests/ --display-all-issues
```

This keeps the PHP/Symfony development tooling inside the container rather than requiring the complete toolchain on the host machine.

---

# Design Principles

## Human in the loop

The AI proposes changes.

The developer decides whether those changes should be applied.

---

## Small scope

Agent 1 reviews changed files independently.

Agent 2 receives the findings relevant to the file it is fixing.

This reduces unnecessary context and helps keep changes focused.

---

## Validate before writing

The generated source is treated as untrusted output.

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

## Provider abstraction

The agents depend on an LLM abstraction rather than directly depending on one AI provider.

This allows the LLM implementation to be replaced or extended without changing the agents themselves.

---

# Current Scope

The current MVP focuses on:

* PHP
* Git-based change detection
* AI code review
* Developer approval
* AI-generated fixes
* PHP syntax validation
* AI fix-scope validation
* Gemini integration
* Docker-based development
* Automated tests

---

# Future Improvements

The current MVP intentionally keeps the architecture focused.

Possible future versions could add:

* GitHub Actions integration
* Automatic pull-request comments
* Automatic commit creation
* GitHub PR integration
* GitLab support
* Additional programming languages:

  * Python
  * TypeScript
  * Go
* Additional LLM providers
* More advanced static analysis
* Evaluation datasets for review quality
* Metrics for review and fix accuracy
* Human feedback collection

These are future extensions and are not required for the current MVP.

---

# MVP Definition of Done

The current MVP is considered complete when:

```text
✓ Git changes can be detected
✓ Changed PHP files can be reviewed
✓ Agent 1 produces structured findings
✓ Developer explicitly approves fixes
✓ Agent 2 generates fixes
✓ Generated PHP is syntax-validated
✓ Generated changes are scope-validated
✓ Files are modified only after validation
✓ Gemini transient errors are retried
✓ Unit and integration tests pass
✓ End-to-end review/fix flow works
✓ Docker environment works
✓ Project documentation is available
```

The project is designed as a foundation for a larger AI-assisted software engineering platform while keeping the first version intentionally small and understandable.
