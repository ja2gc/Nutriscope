# NCP Navigation Gates Design

## Goal
NCP pages should be reachable before a patient is selected so their existing "No Patient Selected" UI can explain what to do. Step locking should apply only inside a real patient NCP cycle, where users may go back to completed steps but cannot skip ahead before saving required prior steps.

## Behavior
- Sidebar NCP step links route to placeholder pages when no real patient and NCP cycle are active.
- Assessment is always available inside a cycle.
- Diagnosis is available only after the assessment exists.
- Intervention is available only after at least one diagnosis exists.
- Monitoring is available only after intervention exists. Before that, the Monitoring page shows a follow-up/second-visit explanation instead of the monitoring UI.
- Patient profile cycle buttons mirror the same gates and show disabled explanatory states.

## Architecture
Add one frontend workflow helper that computes step availability from the cycle data shape already returned by `fetchPatientNcpRecords`. Sidebar only needs URL behavior; step pages and patient profile use direct data/load results for gates. Backend gates remain source of truth and unchanged.

## Testing
Use Vitest unit tests for the pure workflow helper. Run targeted helper tests, then TypeScript check.
