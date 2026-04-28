# Production NPM Audit Policy

This frontend intentionally keeps the current Vue 2 / uni-app build chain stable for deployment.

## What Is Enforced

Use:

```sh
npm run audit:prod
```

The script runs:

```sh
npm audit --omit=dev --omit=optional --json
```

It fails on any unapproved production dependency vulnerability.

## Approved Exception

- `vue:1100238`
- Severity: low
- Reason: Vue 2 `parseHTML` ReDoS advisory. The H5 app uses precompiled uni-app/Vue SFC templates and currently has no `v-html`, dynamic `template`, or runtime template compilation from untrusted input.

## Deployment Rules

- Deploy only the static `dist/build/h5` output.
- Do not expose Vue CLI / webpack dev server publicly.
- Treat new production vulnerabilities as blocking unless explicitly reviewed.
- Treat full `npm audit` dev-tool findings as build-chain upgrade work, not an immediate production runtime blocker.

## Long-Term Fix

The complete fix is a separate frontend stack upgrade: Vue 3 plus a newer uni-app/Vite-compatible build chain. Do not run `npm audit fix --force` on the current Vue 2 stack without a full regression test plan.
