# Repository instructions

Before any work, read `.agents/rules/workflow.md` for universal rules and
`.agents/AGENTS.md` for the rule map. Read the topic files and nested `AGENTS.md`
files applicable to the task. The `.agents/` files are loaded through this
explicit instruction; do not assume Codex discovers that directory automatically.

Read [DEVELOPERS.md](DEVELOPERS.md) to locate the current owner of a feature. Before adding a component, hook, validator or service, inspect matching existing implementations and callers; reuse them where responsibility and behavior match.

Keep DEVELOPERS.md and affected docs/developers guides current when changing locations, business-rule ownership, commands, contracts or conventions. Refresh their verified source revision; document only behavior actually inspected. Keep guides focused and link to existing domain references instead of duplicating them.

Use the grouped docs/developers/README.md index. For field, unit, status, calculation or snapshot changes, update the affected domain data guide with verified meaning, writer and consumers; use platform/data-semantics.md for tracing. Preserve unknowns explicitly and avoid duplicating the schema or copying provider secrets into docs.

Deployment and ownership instructions start at [operations](docs/developers/operations/README.md). Treat example environments as templates; never read non-example dotenv files or put credentials or patient data in documentation. Documentation is not authorization to run migrations, production commands, release or deployment.
