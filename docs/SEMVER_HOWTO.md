# SemVer HOW TO

This is the practical guide for using the SemVer agent workflow in this repo.

## 1) Write Commit Messages That Classify Cleanly

Use Conventional Commit style:

- `feat(scope): ...`
- `fix(scope): ...`
- `refactor(scope): ...`
- `docs(scope): ...`
- `chore(scope): ...`

Use breaking markers when needed:

- `feat(scope)!: ...`
- body line: `BREAKING CHANGE: ...`

Optional explicit overrides:

- `[semver:major]`
- `[semver:minor]`
- `[semver:patch]`
- `[semver:none]`

## 2) Ask The Agent After You Commit

Use this prompt:

```text
Use /Users/davepeloso/Sites/Gallerie/SEMVER_AGENT.md as the only semver policy.
Run in decide mode for range v4.0.0..HEAD.
Return exactly:
mode, baseline, range, recommended_bump, recommended_version, confidence, evidence, notes.
```

If you want to evaluate only recent work from another tag:

```text
Use /Users/davepeloso/Sites/Gallerie/SEMVER_AGENT.md as the only semver policy.
Run in decide mode for range <tag>..HEAD.
Return exactly:
mode, baseline, range, recommended_bump, recommended_version, confidence, evidence, notes.
```

## 3) Interpret The Output

- `mode: decide` means commit history was clean enough for deterministic output.
- `mode: recommend` means history had ambiguity; recommendation is still usable.
- `recommended_bump` is the bump class.
- `recommended_version` is the next version you should release.

## 4) Release Step

After you approve the version:

1. Create the release commit/tag.
2. Update baseline in [SEMVER_AGENT.md](/Users/davepeloso/Sites/Gallerie/SEMVER_AGENT.md) to the newly released version.
3. Use the new version as the next range base.

## 5) Quick Example

- Baseline: `v4.0.0`
- New commits include `feat(...)` and `fix(...)`
- No breaking markers
- Result: `minor` -> `v4.1.0`

## 6) Team Rule Of Thumb

If a change breaks external behavior (API contract, route contract, payload shape, auth expectations, migration behavior), mark it as breaking in the commit message so the agent can reliably choose `major`.

