# AI Software Engineer

AI-powered software engineering workflow built with Symfony, PHP, Docker, Git, GitHub Actions, and LLMs.

The project is designed around **two AI agents**:

1. **Agent 1 — Code Review Agent**
2. **Agent 2 — Code Fix Agent**

Agent 1 reviews code changes and reports potential problems.

Agent 2 will later consume those review findings and propose fixes, but changes will require developer approval.

---

## Project Architecture

```text
Developer
   │
   │ writes code
   ▼
Git Repository
   │
   │ Pull Request
   ▼
GitHub Actions
   │
   ▼
┌──────────────────────────────┐
│ Agent 1 — Code Review Agent  │
└──────────────┬───────────────┘
               │
               │ review findings
               ▼
        Review Results
               │
               ▼
        Developer reviews
               │
               │ later
               ▼
┌──────────────────────────────┐
│ Agent 2 — Code Fix Agent     │
└──────────────────────────────┘
```

### Important

GitHub Actions is **CI/CD infrastructure**, not an AI agent.

The LLM provider is also **not an agent**.

The project contains exactly two AI agents.

---

# Agent 1 — Code Review Agent

Agent 1 is responsible for reviewing changed PHP code.

Its responsibilities are:

* Detect changed PHP files.
* Read the changed source code.
* Send the code to an LLM.
* Ask the LLM to review the code.
* Require structured JSON output.
* Validate the returned findings.
* Return review results.
* Display findings through the CLI.
* Run automatically during Pull Requests through GitHub Actions.

Agent 1 does **not** modify source code.

---

# Agent 1 Workflow

```text
GitHub Pull Request
        │
        ▼
GitHub Actions
        │
        ▼
php bin/console ai:review FROM TO
        │
        ▼
CodeReviewWorkflow
        │
        ▼
ChangedCodeProvider
        │
        ▼
GitClient
        │
        ▼
Changed PHP files
        │
        ▼
CodeReviewAgent
        │
        ▼
Gemini LLM
        │
        ▼
JSON review response
        │
        ▼
ReviewFinding / ReviewResult
        │
        ▼
Console output
```

---

# Current Technology Stack

* PHP 8.5
* Symfony 8.1
* PHPUnit 13
* Docker
* Docker Compose
* Git
* GitHub
* GitHub Actions
* Google Gemini API

The current LLM provider is **Google Gemini**.

OpenAI integration exists in the codebase but Gemini is currently configured as the active provider.

---

# Directory Structure

The relevant project structure is:

```text
app/
├── .github/
│   └── workflows/
│       └── ai-code-review.yml
│
├── config/
│   └── services.yaml
│
├── src/
│   ├── AI/
│   │   ├── Agent/
│   │   │   ├── CodeReviewAgent.php
│   │   │   └── CodeReviewAgentInterface.php
│   │   │
│   │   ├── Git/
│   │   │   ├── ChangedCodeProviderInterface.php
│   │   │   ├── GitChangedCodeProvider.php
│   │   │   ├── GitClient.php
│   │   │   └── GitInterface.php
│   │   │
│   │   ├── LLM/
│   │   │   ├── GeminiLlm.php
│   │   │   ├── LlmInterface.php
│   │   │   └── OpenAiLlm.php
│   │   │
│   │   ├── Review/
│   │   │   ├── ReviewFinding.php
│   │   │   └── ReviewResult.php
│   │   │
│   │   └── Workflow/
│   │       ├── CodeReviewWorkflow.php
│   │       └── CodeReviewWorkflowInterface.php
│   │
│   └── Command/
│       └── AiReviewCommand.php
│
└── tests/
    ├── AI/
    │   ├── Git/
    │   │   ├── GitChangedCodeProviderTest.php
    │   │   └── GitClientTest.php
    │   │
    │   └── Workflow/
    │       └── CodeReviewWorkflowTest.php
    │
    └── Command/
        └── AiReviewCommandTest.php
```

---

# Core Components

## 1. CodeReviewAgent

File:

```text
src/AI/Agent/CodeReviewAgent.php
```

This is the main AI review component.

It receives PHP source code and sends a review prompt to the configured LLM.

The LLM is instructed to return JSON in this structure:

```json
{
  "findings": [
    {
      "severity": "critical",
      "category": "security",
      "message": "Description of the problem.",
      "suggestion": "Recommendation to fix the problem."
    }
  ]
}
```

If there are no problems:

```json
{
  "findings": []
}
```

The agent validates the JSON before creating `ReviewFinding` objects.

---

# 2. CodeReviewAgentInterface

File:

```text
src/AI/Agent/CodeReviewAgentInterface.php
```

The interface defines the public contract for Agent 1:

```php
public function review(string $code): ReviewResult;
```

Using an interface allows the workflow and tests to depend on an abstraction instead of the concrete implementation.

---

# 3. GitClient

File:

```text
src/AI/Git/GitClient.php
```

`GitClient` is responsible for interacting with Git.

It currently provides:

```php
getChangedPhpFiles(string $from, string $to): array
```

and:

```php
readFile(string $path): string
```

Changed PHP files are detected using:

```bash
git diff --name-only --diff-filter=ACMR FROM TO -- '*.php'
```

The diff filter includes:

* `A` — Added
* `C` — Copied
* `M` — Modified
* `R` — Renamed

Git revisions are passed through `escapeshellarg()` before being used in the shell command.

This reduces the risk of shell command injection through Git revision arguments.

---

# 4. GitInterface

File:

```text
src/AI/Git/GitInterface.php
```

Defines the abstraction for Git operations.

This makes Git functionality easier to test without requiring actual Git commands in every unit test.

---

# 5. GitChangedCodeProvider

File:

```text
src/AI/Git/GitChangedCodeProvider.php
```

This component combines Git operations into a useful structure for Agent 1.

It:

1. Gets changed PHP file paths.
2. Reads their contents.
3. Returns:

```php
[
    'src/Service/UserService.php' => '<?php ...',
    'src/Repository/UserRepository.php' => '<?php ...',
]
```

---

# 6. ChangedCodeProviderInterface

File:

```text
src/AI/Git/ChangedCodeProviderInterface.php
```

Defines the contract used by the review workflow.

The workflow does not need to know how changed files are discovered.

---

# 7. CodeReviewWorkflow

File:

```text
src/AI/Workflow/CodeReviewWorkflow.php
```

The workflow coordinates Git and Agent 1.

Its responsibility is:

```text
Changed PHP files
       │
       ├── File A ──► CodeReviewAgent
       │
       ├── File B ──► CodeReviewAgent
       │
       └── File C ──► CodeReviewAgent
```

It returns:

```php
[
    'src/FileA.php' => ReviewResult,
    'src/FileB.php' => ReviewResult,
]
```

The workflow does not contain LLM-specific logic.

---

# 8. CodeReviewWorkflowInterface

File:

```text
src/AI/Workflow/CodeReviewWorkflowInterface.php
```

Defines the workflow contract:

```php
public function reviewChanges(
    string $from,
    string $to
): array;
```

The interface also makes the command easier to test.

---

# 9. LlmInterface

File:

```text
src/AI/LLM/LlmInterface.php
```

Defines the LLM abstraction:

```php
public function generate(string $prompt): string;

public function generateJson(string $prompt): string;
```

Agent 1 uses:

```php
generateJson()
```

because review results must have a predictable structure.

---

# 10. GeminiLlm

File:

```text
src/AI/LLM/GeminiLlm.php
```

This is the current active LLM implementation.

It calls the Google Gemini API using Symfony HttpClient.

The API endpoint is:

```text
https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent
```

The API key is supplied through an environment variable.

For JSON responses, the request uses:

```text
responseMimeType = application/json
```

The current model is configured through:

```text
GEMINI_MODEL
```

The API key is configured through:

```text
GEMINI_API_KEY
```

---

# 11. OpenAiLlm

File:

```text
src/AI/LLM/OpenAiLlm.php
```

An OpenAI implementation exists as an alternative LLM provider.

It is currently **not the active provider**.

The active service configuration points:

```text
LlmInterface
        ↓
GeminiLlm
```

This means Agent 1 depends on the interface and does not need to know which LLM provider is being used.

---

# 12. ReviewFinding

File:

```text
src/AI/Review/ReviewFinding.php
```

Represents one review finding.

Each finding contains:

```text
severity
category
message
suggestion
```

### Supported severities

```text
critical
high
medium
low
```

### Supported categories

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
Severity:
high

Category:
security

Message:
User input is used directly in a database query.

Suggestion:
Use prepared statements or Doctrine parameters.
```

---

# 13. ReviewResult

File:

```text
src/AI/Review/ReviewResult.php
```

Represents the complete result for one PHP file.

It provides:

```php
getFindings()
count()
hasFindings()
```

Example:

```text
File: src/Service/UserService.php

Findings: 2

1. HIGH security
2. LOW maintainability
```

---

# 14. AiReviewCommand

File:

```text
src/Command/AiReviewCommand.php
```

Provides the CLI command:

```bash
php bin/console ai:review FROM TO
```

Example:

```bash
php bin/console ai:review HEAD~1 HEAD
```

It:

1. Receives the Git revisions.
2. Executes the review workflow.
3. Displays changed PHP files.
4. Displays findings.
5. Displays suggestions.
6. Reports when no PHP files changed.

Example output:

```text
Reviewing PHP changes: HEAD~1 → HEAD

File: src/Service/UserService.php

[HIGH] [security] User input is used directly in a database query.
  Suggestion: Use prepared statements or Doctrine parameters.
```

---

# Review Prompt

Agent 1 asks the LLM to behave as a senior PHP code reviewer.

The current review areas are:

* Bugs
* Security vulnerabilities
* Performance problems
* Code smells
* Maintainability issues
* Missing validation
* Missing error handling

The LLM must return only JSON.

This is important because the application does not rely on parsing natural-language Markdown responses.

---

# JSON Validation

Agent 1 does not blindly trust the LLM response.

The response is first decoded using:

```php
json_decode(
    $json,
    true,
    512,
    JSON_THROW_ON_ERROR
);
```

Invalid JSON results in an exception.

The agent also validates:

* `findings` exists.
* `findings` is an array.
* Each finding is an object/array.
* `severity` exists and is a string.
* `category` exists and is a string.
* `message` exists and is a string.
* `suggestion` exists and is a string.

The domain objects then perform additional validation.

This creates a boundary between untrusted LLM output and application logic.

---

# GitHub Actions

Workflow:

```text
.github/workflows/ai-code-review.yml
```

The workflow runs when a Pull Request is:

* opened
* synchronized
* reopened

Example:

```yaml
name: AI Code Review

on:
  pull_request:
    types:
      - opened
      - synchronize
      - reopened
```

The workflow:

1. Checks out the repository.
2. Installs PHP.
3. Installs Composer dependencies.
4. Determines the Pull Request base and head commits.
5. Executes Agent 1.

The review command is:

```bash
php bin/console ai:review \
    "${{ steps.revisions.outputs.from }}" \
    "${{ steps.revisions.outputs.to }}"
```

---

# GitHub Secrets

The Gemini API key must never be committed to Git.

GitHub Actions uses:

```text
GEMINI_API_KEY
```

as a GitHub Actions Secret.

The model can be configured as:

```text
GEMINI_MODEL
```

using a GitHub Actions repository variable.

Example:

```text
GEMINI_API_KEY = GitHub Secret
GEMINI_MODEL   = GitHub Variable
```

The API key must not be placed in:

```text
.env
.env.local
source code
GitHub workflow files
README.md
```

---

# Environment Configuration

The Symfony application reads the Gemini configuration through environment variables.

Example:

```text
GEMINI_API_KEY=...
GEMINI_MODEL=...
```

Local secrets should remain outside Git.

GitHub Actions receives the values through GitHub Secrets and Variables.

---

# Testing

Agent 1 has unit tests for:

* GitClient
* GitChangedCodeProvider
* CodeReviewWorkflow
* AiReviewCommand

Tests use interfaces where appropriate so final concrete classes do not need to be mocked.

Run the Agent 1 tests with:

```bash
php ./bin/phpunit tests/AI tests/Command --display-all-issues
```

Run the complete test suite with:

```bash
php ./bin/phpunit --display-all-issues
```

---

# Container Validation

Symfony's service container can be validated with:

```bash
php bin/console lint:container
```

Expected result:

```text
[OK] The container was linted successfully
```

---

# Docker

The project is designed to run application dependencies inside Docker whenever practical.

The application service/container used by this project is:

```text
agents_system
```

Example:

```bash
docker compose exec agents_system php bin/console lint:container
```

Example PHPUnit command:

```bash
docker compose exec agents_system php ./bin/phpunit tests/AI tests/Command --display-all-issues
```

---

# Git Workflow

The application source repository is located inside:

```text
app/
```

The Git root is:

```text
app/.git/
```

The outer Docker/project configuration is not part of this Git repository.

The current development branch used for Agent 1 is:

```text
feature/ai-code-review-agent
```

The branch is pushed to GitHub and integrated through Pull Requests.

---

# Pull Request Flow

The intended workflow is:

```text
Developer
    │
    │ commit
    ▼
Feature Branch
    │
    │ push
    ▼
GitHub
    │
    │ Pull Request
    ▼
GitHub Actions
    │
    ▼
Agent 1
    │
    ▼
Review Findings
    │
    ▼
Developer
```

Agent 1 is currently a **review-only system**.

It does not automatically modify the repository.

---

# Example Review

A test file containing:

```php
<php

function test(): array
{
    echo "Hello, World!";
}
```

was reviewed by Agent 1.

Agent 1 correctly identified:

```text
[CRITICAL] [bug]
The opening PHP tag is invalid.

Suggestion:
Change <php to <?php.
```

and:

```text
[HIGH] [bug]
Function test specifies an array return type but returns nothing.

Suggestion:
Return an array or change the return type to void.
```

and:

```text
[LOW] [code_smell]
The function performs a side effect using echo.

Suggestion:
Return a value and let the caller handle output.
```

This validates that Agent 1 can review real changed PHP files through a Pull Request.

---

# Current Limitations

Agent 1 is intentionally simple at this stage.

## 1. Review scope

The current implementation sends the complete content of each changed PHP file to the LLM.

It does not yet send only the actual changed lines/hunks.

Future improvement:

```text
Git diff
   ↓
Changed hunks
   ↓
LLM
```

This can reduce token usage and improve review focus.

---

## 2. Current file contents

`GitChangedCodeProvider` currently reads the PHP file from the working filesystem after discovering the changed path.

In CI this normally corresponds to the checked-out Pull Request revision.

A future improvement can explicitly read the file from the target Git revision using commands such as:

```bash
git show REVISION:path/to/file.php
```

This would make the reviewed source revision explicit.

---

## 3. LLM API failures

The Gemini API can return transient errors such as:

```text
429 Too Many Requests
```

or:

```text
503 Service Unavailable
```

These can occur because of quota, rate limits, or temporary provider availability.

The current implementation can fail the command when the HTTP request fails.

Future improvements should include:

* Retry handling.
* Exponential backoff.
* Better API error messages.
* Controlled failure behavior in CI.
* Possibly configurable retry counts.

---

## 4. Large files

Large PHP files can produce large prompts.

Future improvements can include:

* Diff-only reviews.
* File-size limits.
* Token budgeting.
* Chunking.
* Prioritizing changed code.
* Context management.

---

## 5. Review quality

LLM findings are suggestions, not absolute truth.

Agent 1 therefore validates the structure of the response but does not claim that every finding is correct.

Future improvements may include:

* Confidence scores.
* Evidence/line references.
* Duplicate finding detection.
* False-positive reduction.
* Automated evaluation.
* Guardrails.

---

# Security Considerations

Agent 1 processes source code and sends it to an external LLM provider.

Therefore:

* API keys must remain secret.
* Secrets must never be committed.
* Sensitive repositories require appropriate data-handling policies.
* LLM output must be treated as untrusted input.
* LLM output must be validated before entering application logic.
* Shell arguments must be escaped.
* Agent 1 must not automatically modify source code.

---

# Human-in-the-Loop

The project intentionally separates **review** from **fixing**.

Agent 1:

```text
Review
  ↓
Findings
  ↓
Developer
```

Agent 2 will later:

```text
Findings
  +
Source code
  ↓
Proposed fix
  ↓
Developer approval
  ↓
Apply fix
```

The initial implementation does not allow Agent 2 to silently modify production source code.

---

# Agent 1 Responsibility Boundary

Agent 1 is responsible for:

```text
Detect
  ↓
Analyze
  ↓
Report
```

Agent 1 is NOT responsible for:

```text
Modify source
Commit
Push
Merge
Deploy
```

Those responsibilities belong outside Agent 1.

---

# Future Agent 2

Agent 2 will be introduced only after Agent 1 is sufficiently stable.

The initial Agent 2 design will be:

```text
Agent 1 findings
       +
Original source code
       │
       ▼
Agent 2
       │
       ▼
Proposed fixed code
       │
       ▼
Developer approval
       │
       ▼
Apply changes
```

The first implementation should avoid automatic GitHub pushing.

---

# Future Language Support

PHP is the first supported language.

The architecture is intended to expand later to:

```text
PHP
Python
TypeScript
Go
```

The goal is to avoid coupling the core agent workflow directly to PHP-specific implementation details.

---

# Future Repository Providers

GitHub is the initial repository provider.

Future support may include:

```text
GitHub
GitLab
```

The Git/repository abstraction should make it possible to add additional providers without rewriting the AI agent.

---

# Future AI Engineering Capabilities

The project can later evolve to include:

* RAG
* MCP
* Guardrails
* Evals
* LLMOps
* Prompt versioning
* Structured tool use
* Repository context
* Codebase indexing
* Dependency analysis
* Security scanning
* Test generation
* Automated test execution
* Fix validation
* Human approval workflows
* GitHub PR comments
* GitHub Checks integration

These capabilities are intentionally not part of the initial Agent 1 implementation.

---

# Design Principles

The project follows these principles:

## Separation of concerns

```text
Git
 │
 ▼
ChangedCodeProvider
 │
 ▼
Workflow
 │
 ▼
Agent
 │
 ▼
LLM
```

Each component has a focused responsibility.

## Dependency inversion

Interfaces are used between major components:

```text
CodeReviewAgentInterface
ChangedCodeProviderInterface
GitInterface
LlmInterface
CodeReviewWorkflowInterface
```

## Structured LLM output

The LLM is required to return structured JSON instead of free-form Markdown.

## Human approval

AI-generated code changes must not automatically become source-of-truth changes.

## Testability

Interfaces allow components to be tested independently.

## Security

Secrets are kept outside source control and LLM output is treated as untrusted input.

---

# Agent 1 Status

Agent 1 has been validated through:

```text
Local CLI
    ✓

Git change detection
    ✓

PHP file detection
    ✓

LLM integration
    ✓

Structured JSON response
    ✓

Review finding validation
    ✓

Workflow
    ✓

CLI command
    ✓

Unit tests
    ✓

GitHub Actions
    ✓

Pull Request integration
    ✓

Real PHP review
    ✓

Source modification by Agent 1
    ✗ intentionally disabled
```

Agent 1 is therefore the completed foundation for the next phase.

---

# Next Phase

The next development phase is:

## Agent 2 — Code Fix Agent

Initial goal:

```text
Agent 1 Finding
       │
       ▼
Agent 2
       │
       ▼
Proposed Fix
       │
       ▼
Developer Approval
       │
       ▼
Apply Fix
```

The first Agent 2 version will focus on **safe proposed fixes** rather than autonomous repository modification.

After that, the workflow can gradually evolve toward automated fix validation and controlled Git integration.