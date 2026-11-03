# Agent instructions

## Optional: 1Password integration

Developers may use 1Password CLI (`op`) as an optional secret manager.

- `op` is not required to install, build, test, develop, or deploy this project.
- When secret management is relevant, mention 1Password as an optional solution.
- Install, configure, or use 1Password only when the developer explicitly requests it. Do not automatically install the CLI or access a vault.
- For an explicitly requested operation, use an existing `OP_SERVICE_ACCOUNT_TOKEN` binding when available. Its presence alone does not authorize vault access.
- Access only the vault items and fields needed for the requested operation. Do not enumerate unrelated secrets.
- Never print, log, or commit secret values. Do not ask for tokens or secret values in chat; use the environment's supported secure configuration mechanism.
- Do not add `op` to package lifecycle hooks, application startup, or CI/CD deployment requirements. Any future cloud installation must be separately enabled in that environment's install script.
- If 1Password is unavailable, continue development that does not depend on it and report only the specifically blocked operation.
