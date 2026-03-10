# SemVer Agent Rules

This file is the source of truth for how an agent should recommend or decide the next semantic version from commit history.

## Baseline

- Current baseline version: `v4.0.0`
- Default baseline source: latest release tag (`git describe --tags --abbrev=0`)
- Fallback if no tag exists: `v0.1.0`

## Commit Message Contract

Use Conventional Commit style:

`<type>(<scope>)!: <summary>`

Examples:

- `feat(gallery): add ZIP link expiration controls`
- `fix(auth): deny client access to photographer routes`
- `refactor(api)!: remove legacy v1 gallery endpoint`

Breaking changes must be explicit:

- `!` in subject, or
- `BREAKING CHANGE:` in body

## Commit Cheat Sheet

Use these templates:

- New feature: `feat(<scope>): <what was added>`
- Bug fix: `fix(<scope>): <what was fixed>`
- Refactor (no behavior change): `refactor(<scope>): <what changed internally>`
- Docs only: `docs(<scope>): <what was documented>`
- Breaking API or behavior: `feat(<scope>)!: <summary>` plus body line `BREAKING CHANGE: <impact>`

Good examples:

- `feat(galleries): add bulk zip pre-generation option`
- `fix(auth): block client from photographer gallery browser route`
- `docs(semver): add agent decision rules`
- `feat(api)!: rename gallery delivery endpoint`

Avoid:

- `update stuff`
- `misc fixes`
- `wip`

## Bump Mapping

- `major`: any breaking change (`!` or `BREAKING CHANGE:`)
- `minor`: `feat`
- `patch`: `fix`, `perf`, `refactor`, `security`, `deps`
- `none`: `docs`, `test`, `chore`, `ci`, `build`, `style`

Highest bump wins across the scanned commit range:

`major > minor > patch > none`

## Explicit Overrides

The following tokens can appear in commit subject or body:

- `[semver:major]`
- `[semver:minor]`
- `[semver:patch]`
- `[semver:none]`

Override precedence:

1. Explicit semver token
2. Breaking markers
3. Type-based mapping

Ignore commits containing:

- `[release:skip]`

## Commit Selection Rules

- Scan commits in `BASE..HEAD`
- Ignore merge commits (`Merge ...`)
- Ignore pure version-tag commits like `chore(release): vX.Y.Z`
- If a revert commit clearly reverts a commit in range, exclude both from bump evidence when possible

## Decision Modes

- `decide`: all commits in range are parseable and follow contract
- `recommend`: one or more commits are unclear/non-standard

If unclear commits exist, still compute a recommendation but set confidence lower.

## Agent Procedure

1. Resolve baseline version tag.
2. Read commit subject + body in `BASE..HEAD`.
3. Classify each commit bump using rules above.
4. Pick highest bump across all included commits.
5. Increment baseline:
- `major`: `X+1.0.0`
- `minor`: `X.Y+1.0`
- `patch`: `X.Y.Z+1`
- `none`: no version change
6. Return result with evidence commit hashes.

## Suggested Commands

```bash
BASE_TAG="$(git describe --tags --abbrev=0 2>/dev/null || echo v0.1.0)"
git log --no-merges --format='%H%n%s%n%b%n--END--' "${BASE_TAG}..HEAD"
```

## Output Format

```text
mode: decide|recommend
baseline: vX.Y.Z
range: vX.Y.Z..HEAD
recommended_bump: major|minor|patch|none
recommended_version: vA.B.C
confidence: high|medium|low
evidence:
- <hash> <subject> -> <bump>
- <hash> <subject> -> <bump>
notes:
- <any ambiguity or override used>
```
