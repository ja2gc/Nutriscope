# Clinical, Appointments, and Notifications

## Reusable clinical contracts

- One current draft/active record; terminal history stays separate and immutable.
- Completion/protection is explicit and requires the system’s clinically complete prerequisites.
- Appointment time alone does not start or finish clinical work; explicit workflow actions bind and close visits.
- Preserve source, purpose, status, actor attribution, completion snapshot, cancellation reason, and replacement links when the schema supports them.
- Historical plans and nutrient snapshots remain historical; do not silently replace them with current values.
- Use the project’s current goal-definition and calculation authority. Do not create a parallel clinical engine.
- Template load/copy preserves children and snapshots and obeys owner/role scope.

## Notification contracts

- Deep links use the public source identity and open the exact originating record.
- Read ≠ resolved. Informational/resolved items may dismiss; unresolved action-required items stay visible and cannot be dismissed until the workflow resolves them.
- Completing the required work anywhere may resolve the item; direct notification entry is not required.
- Reminder generation uses the real idempotent command/service. Never seed fabricated reminder state.
- Shared notification behavior applies to every relevant role, not one role only.
- Attribution records qualifying work, not passive viewing, preview, download, export, or audit reads.
