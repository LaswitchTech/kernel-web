# Kernel-Web - Claude Instructions

## Purpose

This file defines **how Claude must work** in this repository.

It is intentionally focused on:
- behavior
- workflow
- discipline
- documentation habits
- safety rules
- git habits
- coding expectations

Architecture, structure, system design, plugin design, theme design, layout design, OAuth design, and licensing design belong in:

```text
DESIGN.md
```

Claude must treat `DESIGN.md` as the source of truth for architectural direction.

---

## Project Context

Kernel-Web is a reusable PHP application kernel designed to support multiple applications through a modular system of core services, plugins, themes, layouts, and future licensing/authentication extensions.

Repository:
- Remote: `https://github.com/LaswitchTech/kernel-web/tree/dev`
- Local development URL: `https://kernel-web.local/`

Stack summary:
- PHP 8.1+ backend (CI: PHP 8.2), no heavy framework
- JavaScript frontend
- Bootstrap 5 and Bootstrap Icons
- LESS for styling and theming
- SQLite by default, future MySQL/MariaDB support

For detailed design rules, directory structure, plugin architecture, theme architecture, layout architecture, authentication direction, OAuth goals, licensing goals, and security model, read `DESIGN.md` first.

---

## Golden Rule

Before making architectural, structural, or system-level changes:

1. Read `DESIGN.md`
2. Follow its direction
3. Update it if the design changes
4. Update `/docs` when implementation details change
5. Check `ROADMAP.md` to ensure the task aligns with current priorities

Do not duplicate large design explanations in this file.

---

## Work Planning Rules

Before coding, always explain the plan.

The plan must include:
- what will be changed
- why it is being changed
- which files are expected to be touched
- any risks or assumptions
- Ensure the task aligns with the current priority level in `ROADMAP.md`

Keep plans concise, but specific.

Do not start coding without first understanding the existing structure.

---

## Coding Discipline

When coding:

- Make small, safe changes
- Never modify unrelated files
- Prefer readable code over clever code
- Prefer explicit logic over magic
- Respect existing architecture before introducing new patterns
- Keep controllers thin
- Keep services focused
- Keep repositories focused on persistence
- Keep reusable code generic
- Avoid NetMon-specific assumptions in Kernel-Web core
- Avoid hardcoding paths that should be configurable
- Avoid hidden side effects

---

## Design Separation Rule

Do not turn `CLAUDE.md` into an architecture document.

Use this split:

```text
CLAUDE.md → HOW to work
DESIGN.md → WHAT is being built
ROADMAP.md → WHAT is planned next (priorities and sequencing)
/docs     → IMPLEMENTED behavior and reference documentation
```

Examples:

- New plugin lifecycle decision → update `DESIGN.md`
- Implemented plugin loader behavior → update `/docs`
- Rule that Claude must commit after each run → keep in `CLAUDE.md`
- Rule that plugins live in `/lib/plugins/{Name}` → keep in `DESIGN.md`
- New feature prioritization or sequencing → update `ROADMAP.md`

---

## Documentation Rules

Always update documentation when changes affect:
- architecture
- behavior
- setup
- deployment
- security
- database schema
- migrations
- routes
- APIs
- plugins
- themes
- layouts
- authentication
- authorization
- licensing

Documentation responsibilities:

- `DESIGN.md` documents design intent and architecture decisions
- `ROADMAP.md` documents priorities, sequencing, and upcoming work
- `/docs` documents implemented behavior, setup, usage, APIs, and reference material
- `CLAUDE.md` documents workflow and contribution behavior

Never leave documentation knowingly stale.

---

## Git Workflow Rules

At the end of each completed run/task:

1. Review changed files
2. Run available checks/tests where practical
3. Ensure docs are updated
4. Commit the changes
5. Summarize the commit

Commit rules:
- Use clear, descriptive commit messages
- Keep commits focused
- Do not mix unrelated changes
- Do not commit secrets
- Do not commit local-only generated files unless intentionally required

If a task cannot be safely completed or committed, clearly explain why.

---

## Safety and Secret Handling

Never commit:
- `.env` files containing secrets
- private keys
- API tokens
- passwords
- OAuth client secrets
- license signing keys
- production database dumps
- user-uploaded private data

Use safe examples instead:
- `.env.example`
- sample config files
- placeholder credentials
- documented setup instructions

When handling files, uploads, paths, routing, auth, or deployment behavior, assume the app may be exposed to the public internet.

---

## Refactoring Rules

When refactoring code from NetMon or any other project into Kernel-Web:

- Extract only reusable infrastructure into the kernel
- Keep domain-specific behavior out of core
- Convert reusable features into plugins where appropriate
- Rename classes, namespaces, routes, and docs to generic Kernel-Web concepts
- Avoid copying dead code
- Avoid copying assumptions that only apply to NetMon
- Keep changes incremental and reviewable

If unsure whether something belongs in core or a plugin, prefer plugin until the core need is clear.

---

## Testing and Validation Rules

After changes, run whatever validation is available and appropriate, such as:
- PHP syntax checks
- unit tests if present
- migration dry-runs if applicable
- route smoke tests if practical
- manual browser checks when relevant

If no automated checks exist yet, state that clearly in the summary and suggest the next useful validation to add.

Never claim tests passed unless they were actually run.

---

## Error Handling Expectations

When introducing or modifying code:
- Fail safely
- Log useful errors where appropriate
- Do not expose sensitive details to users
- Avoid silent failures
- Keep user-facing errors simple
- Keep developer-facing logs actionable

## Global View Context Rule

**Never hide missing globals by silently returning from partials.**

If a partial encounters a missing global variable (e.g. `$Config`, `$Auth`, `$currentUserDisplayName`), that is a **context pipeline bug** — not a partial bug. The fix belongs at the layout entry point, not in defensive `isset()` checks that silently pass.

See `DESIGN.md` § "Global View Context Design" for the intended architecture. The goal is a single guaranteed context layer at the top of every layout that provides all globals to all views/partials. Never patch around a missing global with fallbacks or silent returns.

---

## Dependency Rules

Kernel-Web should avoid heavy dependencies.

Before adding a dependency:
- Explain why it is needed
- Confirm the problem cannot reasonably be solved with existing code
- Prefer small, well-maintained packages
- Document the dependency and its purpose

Do not introduce a framework unless explicitly requested.

---

## Style Expectations

Code should be:
- readable
- explicit
- modular
- documented where needed
- consistent with existing naming and structure

Documentation should be:
- clear
- practical
- updated alongside code
- written for future maintainers

---

## Run Summary Format

At the end of each run, summarize:

```text
Summary
- What changed

Files changed
- path/to/file — short explanation

Validation
- What was run, or why validation was not run

Commit
- Commit hash/message, or why no commit was made

Risks / Notes
- Any risks, assumptions, or follow-up items
```

---

## Current Development Context

The repository has been pulled locally and Apache has been configured for:

```text
https://kernel-web.local/
```

The project is currently in the early architecture/skeleton phase.

Before copying existing NetMon code, stabilize the kernel skeleton, documentation, and conventions so future refactoring is intentional instead of a direct copy.
