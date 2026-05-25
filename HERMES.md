# Kernel-Web — Project Rules for Hermes Agent

This file defines the rules, workflows, and architectural discipline for working in the Kernel-Web repository.

## 1. Required Startup Workflow

Before making any changes, follow this order:

1. Read `DESIGN.md` — source of truth for architecture and design decisions
2. Read `ROADMAP.md` — current priorities and task sequencing
3. Inspect the relevant `docs/` directory for implementation details
4. Inspect existing code that will be touched to understand current behavior

If a task is unclear, ask the user before proceeding. Never assume missing information.

## 2. Implementation Workflow

### Pre-Change Plan

Before coding, present a concise plan covering:

- What will be changed and why
- Which files will be touched (specific paths)
- Any risks or assumptions
- Whether the task aligns with current `ROADMAP.md` priorities

Keep plans brief but specific. Do not begin coding without presenting the plan first.

### Implementation Rules

- Make small, incremental changes
- Never modify unrelated files
- Write readable, explicit code over clever or magical code
- Prefer simpler code that can be expressed as inline logic over extracted helpers
- Extract a helper only when the same pattern repeats three times
- Preserve the existing architecture before introducing new patterns

### Post-Change

- Run relevant tests or validation (see Section 7)
- Update documentation if the change affects behavior
- Present a summary of changes and any remaining work
- Do not commit unless explicitly instructed

## 3. Kernel-Web Architectural Rules

### Design Principles

1. **Kernel first** — Core must remain app-agnostic. No domain-specific logic (e.g., NetMon) in core. Everything reusable lives in kernel or plugins.
2. **Plugin-first** — Prefer plugins over core expansion. If unsure whether something belongs in core or a plugin, put it in a plugin.
3. **Explicit over magic** — No hidden behaviors. No heavy frameworks. Readable, predictable flows.
4. **Separation of concerns** — Controllers = orchestration only. Services = business logic. Repositories = persistence. Kernel = infrastructure. Plugins = features.
5. **Secure by design** — Assume public deployment. Deny access by default. Validate everything server-side.

### Code Structure Expectations

- Keep controllers thin; they orchestrate, not compute
- Keep services focused on a single responsibility
- Keep repositories focused solely on data access
- Keep reusable code generic — no hardcoded, application-specific assumptions
- Avoid hardcoding paths that should be configuration
- Avoid hidden side effects

### Dependency Rules

- Prefer small, well-maintained packages
- Do not introduce a framework unless explicitly requested
- Before adding any dependency, explain why it is needed and confirm the problem cannot be solved with existing code

### PHP Conventions

- Minimum PHP 8.1. Use modern PHP 8.x features: `readonly` value objects, union types, `mixed`, `match` expressions, constructor property promotion, native `str_contains`/`str_starts_with`.
- No PHP 7.x compatibility. Code that reverts modern PHP syntax for backward compatibility is incorrect.

## 4. Git and Safety Rules

- Never commit unless explicitly instructed by the user
- Never modify unrelated files
- Never rewrite large sections unnecessarily
- Keep commits focused — do not mix unrelated changes
- When possible, preserve backward compatibility over breaking changes (unless the roadmap indicates active refactoring is the priority)

### Secrets Handling

Never commit under any circumstances:

- `.env` files containing secrets
- Private keys, API tokens, passwords
- OAuth client secrets, license signing keys
- Production database dumps
- User-uploaded or user-owned private data

Use safe examples instead: `.env.example`, sample configs, placeholder credentials, documented setup instructions.

Assume the app may be exposed to the public internet when handling files, uploads, paths, routing, or auth behavior.

### Refactoring from Other Projects

- Extract only reusable infrastructure into the kernel
- Keep domain-specific behavior out of core
- Convert reusable features into plugins where appropriate
- Rename classes, namespaces, routes, and docs to generic Kernel-Web concepts
- Avoid copying dead code or domain-specific assumptions
- Keep changes incremental and reviewable

## 5. Documentation Rules

Always update documentation when changes affect:

- Architecture or system design
- Behavior, setup, or deployment
- Security, database schema, or migrations
- Routes, APIs, plugins, themes, layouts
- Authentication, authorization, or licensing

### Documentation Responsibility Split

```
HERMES.md     → Project rules and workflows (this file)
DESIGN.md     → Architecture and design decisions (source of truth)
ROADMAP.md    → Priorities, sequencing, and progress
/docs         → Implemented behavior, setup guides, and reference material
```

Keep documentation aligned with code. Stale documentation is worse than none.

When documenting new services, plugins, or routes:

- Explain its purpose and responsibilities
- List key public methods or endpoints
- Note any dependencies on other system components
- Document any configuration keys, permissions, or hooks involved

### Architectural Changes

Whenever a structural or architectural change is made:

1. Update `DESIGN.md` with the design decision
2. Update relevant `/docs` files with implementation details
3. Update `ROADMAP.md` if the change affects priorities or task sequencing
4. Maintain consistency across all three documents

## 6. Testing Rules

### Validation Expectations

After making changes, run whatever validation is available and appropriate:

- PHP syntax checks (always)
- Unit tests if present (targeted first, broader after)
- Migration dry-runs if applicable
- Route smoke tests if practical
- Manual browser checks when relevant

### Test Reporting

- Run targeted tests before broader ones
- Explain what was tested and how
- Report failures honestly — never claim tests passed if they were not actually run
- If no automated tests exist for the changed area, state that clearly and suggest the next useful test to add

## 7. Error Handling Expectations

When introducing or modifying code:

- Fail safely — the system should degrade gracefully
- Log useful, actionable errors for developers
- Never expose sensitive details to users
- Avoid silent failures — surface errors at the appropriate layer
- Keep user-facing errors simple and non-technical
- Keep developer-facing logs specific enough to diagnose the issue

## 8. Global View Context Rule

Never hide missing global variables by silently returning from partials. If a partial encounters a missing global (`$Config`, `$Auth`, `$currentUserDisplayName`, etc.), that is a context pipeline bug — not a partial bug. The fix must be at the layout entry point where `ViewGlobals::contextFromScope()` guarantees all globals are available.

The goal is a single guaranteed context layer at the top of every layout. Never patch around a missing global with fallbacks or silent returns in partial files.

## 9. Response Format

When working on tasks, structure your response as follows:

### Plan

Concise description of what will be done and why. List affected files. Note risks.

### Implementation

The actual work — code changes, configurations, structure modifications.

### Tests

What was tested and the results. Be honest about what passed, failed, or was not run.

### Documentation

What documentation was updated and what changed.

### Summary

Overview of changes made and any remaining work.

### Next Steps

Recommended follow-up actions, prioritized by impact.

---

## Current Project State

Kernel-Web is a reusable PHP application kernel supporting multiple applications through a modular system of core services, plugins, themes, and layouts.

**Repository**
- Remote: `https://github.com/LaswitchTech/kernel-web/tree/dev`
- Local dev: `https://kernel-web.local/`

**Phase Status**
- Phase 1 (stabilization): Complete (15/15 tasks)
- Phase 2 (kernel foundations): Substantially complete
- Phase 2b (global context and UX): Mostly complete
- Phase 3 (mid-term): Not yet started
- Phase 4 (long-term): Deferred

**Next recommended tasks (per ROADMAP.md)**
1. P2: Multi-tenant data scoping (organization-level filtering middleware)
2. P2: Kernel update system (version check, download, apply workflow)
3. P2: Migrate remaining DB-backed config to ConfigOverrideService
4. P3: Make 2FA methods extensible
5. P3: Add Variables.md documentation

---

*End of project rules.*
