

# Kernel-Web - Claude Prompt Template

## Purpose

This template is used to guide Claude (or other coding agents such as Qwen via Ollama) when working on Kernel-Web.

It ensures consistency in:
- architecture alignment
- workflow discipline
- documentation updates
- commit behavior
- output structure

---

## Prompt Template

Use the following template when starting a new task:

```text
You are working on the Kernel-Web repository.

Before doing anything:
1. Read `CLAUDE.md` (workflow, discipline, rules)
2. Read `DESIGN.md` (architecture, structure, system design)

You MUST follow both documents.

---

## Task

[Describe the task clearly here]

---

## Requirements

- Follow all rules from `CLAUDE.md`
- Respect architecture defined in `DESIGN.md`
- Do NOT introduce heavy frameworks
- Keep code modular, explicit, and readable
- Do NOT break existing structure
- Do NOT modify unrelated files
- Prefer small, safe changes

---

## Documentation

- Update `/docs` if behavior, setup, API, or usage changes
- Update `DESIGN.md` if architecture or structure changes
- Do NOT duplicate design content in `CLAUDE.md`

---

## Git

At the end of the task:
- Review all changes
- Ensure documentation is updated
- Commit the changes with a clear message

Do NOT commit secrets or sensitive data.

---

## Output Format

### Plan
Explain what you will do before coding.

### Implementation
Provide code changes.

### Summary
- What changed
- Files modified

### Validation
- What was tested or why not

### Commit
- Commit message (or reason if not committed)

### Next Step
Propose the next logical step for the project
```

---

## Usage Example

```text
## Task

Implement the initial kernel bootstrap with:
- `/public/index.php`
- basic config loader
- minimal router

```

---

## Rules

- Always include a clear task description
- Always require a plan before coding
- Always require documentation updates
- Always require a commit
- Always require a summary
- Always require a next step

---

## Goal

This template ensures:
- predictable outputs from Claude
- consistent architecture adherence
- no forgotten documentation
- no forgotten commits
- incremental and safe progress
