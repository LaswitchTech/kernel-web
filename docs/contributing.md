# Contributing to Kernel-Web

## Branching Strategy

Kernel-Web uses a simplified branching model aligned with semantic versioning.

### Branches

| Branch  | Purpose                          | Status        |
|---------|----------------------------------|---------------|
| `stable` | Production-ready releases       | Protected     |
| `dev`    | Active development              | Protected     |

### Release Tags

Releases use semantic versioning: `vX.Y.Z`

- **Major** (`v2.0.0`): Breaking changes
- **Minor** (`v1.1.0`): New features, backward-compatible
- **Patch** (`v1.0.1`): Bug fixes, backward-compatible

### Feature Branches

- Create a branch from `dev` for all work
- Branch naming convention: `{type}/{short-description}`
  - `feat/add-auth-flow`
  - `fix/403-on-admin-page`
  - `docs/contributing-guide`
- Merge back to `dev` via pull request
- Never push directly to `stable` or `dev`

### Pull Requests

- **All pull requests target `dev` by default**
- PR title follows conventional commit format (optional but preferred)
- PR description should explain:
  - What changed and why
  - Any migration or config changes
  - Testing performed
- Squash merge preferred for non-technical changes (docs, config)
- Rebase merge preferred for feature branches to keep commit history

### Release Process

1. `dev` is the integration branch for all features
2. When `dev` is stable, create a release branch from `dev` (optional)
3. Tag the release on `stable`: `vX.Y.Z`
4. `stable` always reflects the latest tagged release
5. After tagging, merge `stable` back into `dev` if needed

### Branch Protection

- `stable`: requires PR + approval
- `dev`: requires PR + approval
- Releases are created via the [release workflow](../.github/workflows/release.yml)
