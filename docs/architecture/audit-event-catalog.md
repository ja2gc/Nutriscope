# Audit Event Catalog

This is the maintained audit-event matrix for the July 15 redesign. `backend/app/Enums/AuditAction.php`, `backend/app/Services/Audit/AuditEventPolicy.php`, `backend/config/audit.php`, and the route-coverage tests remain the executable sources of truth.

## Reading the catalog

- **Module** is the Admin tab. **Category** is the privacy/retention class: `S` security, `C` clinical, `O` operations.
- **Admin detail** lists only fields allowed through the typed DTO. It never implies access to raw `properties`.
- **Context trail** describes RND/FSS/Admin page-level history. Only authorized roles receive it.
- **History** is `drawer` for typed details/changes, `field names` for clinical redaction, or a registered immutable event-time revision.
- **Reason** describes current behavior. Manual ledger reasons are required. New cross-domain delete/correction/reversal reason enforcement was owner-deferred and is not falsely claimed here.

## Security & Administration

| Event family | Actions | Cat. | Actor and subject/context | Safe Admin detail | Context trail | Reason | History | Primary writer/source |
|---|---|---:|---|---|---|---|---|---|
| Successful sign-in | `login_succeeded` | S | Authenticated user; semantic login subject | actor, role, platform/reason tokens when present, time, outcome/severity | None | None | Drawer | `AuthController`; `AuditLogger` |
| Rejected sign-in | `login_failed`, `authentication_failed` | S | Anonymous or resolved safe actor; semantic login/protected-route subject | bounded reason/status/route template; no email, password, token, header, body, IP, or full URL | None | None | Drawer | `AuthController`; `RecordSecurityRejections`; deduplicator |
| Sign-out | `logout` | S | Actual authenticated user | actor, time, outcome | None | None | Drawer | `AuthController` |
| Password lifecycle | `password_changed`, `password_reset` | S | Actual user or Admin performing reset; account context | actor, target account safe label, time, outcome | None | None | Drawer | `AuthController`; `PasswordResetController`; Admin `UserController` |
| Recovery email lifecycle | `recovery_email_changed`, `recovery_email_verified` | S | Actual user and account context | action and time only; email/code never stored or returned | None | None | Drawer | `RecoveryEmailController` |
| Profile change | `profile_changed` | S | Actual user and account context | allow-listed changed field names; safe account name values where permitted | None | Routine reason not required | Drawer | `AuthController` |
| Rate limiting | `rate_limit_exceeded` | S | User or anonymous actor; named limiter and route template | limiter, method, safe route template, status, retry seconds, recurrence count | None | None | Drawer | `AppServiceProvider`; `SecurityAuditDeduplicator` |
| Authorization rejection | `authorization_denied` | S | User/anonymous actor; protected route | method, safe route template, status/reason code, outcome | None | None | Drawer | `RecordSecurityRejections` |
| Account creation and ordinary edit | `created`, `updated`, `deleted` | S | Admin actor; user subject | first/last/display name, role, active state, allow-listed field changes; no credentials | None | New cross-domain destructive reason enforcement deferred | Drawer | Admin `UserController` |
| Account activation state | `account_blocked`, `account_unblocked` | S | Admin actor; user subject | actor, target safe label, previous/new active state | None | None | Drawer | Admin `UserController` |
| Audit oversight access | `audit_log_viewed` | S | Admin or RND actor; semantic audit/patient/NCP context | actor, safe route/context reference, time | Relevant patient/NCP trail may include the access event under its policy | None | Drawer | Admin `AuditLogController`; `ActivityController`; deduplicated |
| Retention control | `settings_changed` | O | Admin actor; retention-setting subject | old/new `retention_enabled`, fixed periods, time | Admin global only | Explicit enable confirmation; no free-text reason | Drawer | `SetAuditRetentionState` |
| Retention run | `completed` | O | `Audit retention` system actor | eligible/deleted/held/failure counts and outcome; no payload values | Admin global only | None | Drawer | `PruneAuditEvents` |
| AI usage limits | `settings_changed` | O | Admin actor; AI-limit subject | allow-listed setting field names/safe values | Admin global only | Routine reason not required | Drawer | Admin `AiUsageLimitController` |
| Announcements | `created`, `updated`, `deleted` | O | Actual RND/Admin actor; announcement subject | safe status/field metadata; announcement body is not an audit payload | Admin global only | New delete-reason enforcement deferred | Drawer | RND/Admin `AnnouncementController` |
| SOP revisions | `created` | O | Actual RND/Admin actor; SOP subject | safe revision metadata, not arbitrary document payload | Existing SOP application history remains separate; Admin global event | None | Drawer | `SopController` |

## Nutrition Care

Patient-linked rows use category Clinical and the field-name-only boundary. RND Food Library and RND recipe rows are safe category Operations but remain in the Nutrition Care module.

| Event family | Actions | Cat. | Actor and subject/context | Safe Admin detail | RND context trail | Reason | History | Primary writer/source |
|---|---|---:|---|---|---|---|---|---|
| Patient lifecycle | `created`, `updated`, `deleted` | C | Actual RND actor; patient root | patient display name snapshot, actor, action/time, record type, pseudonymous NCP reference when available, changed field names only | All active RNDs see the same privacy-safe event on authorized patient/NCP trails | New delete-reason enforcement deferred | Field names | `Patient` + `AuditsChanges`; explicit delete handling in `PatientController` |
| NCP lifecycle | `created`, `updated`, `deleted` | C | Actual RND actor; patient/NCP root | approved patient identity and metadata only; no NCP values | All active RNDs; creator is attribution only | New delete-reason enforcement deferred | Field names | `NcpRecord` + `AuditsChanges` |
| Assessment | `created`, `updated`, `deleted`, `uploaded` | C | Actual RND actor; assessment subject under patient/NCP | approved patient identity; changed/uploaded field names/type only; no assessment/file/OCR contents | All active RNDs may view/edit another RND's assessment | New delete-reason enforcement deferred | Field names | `Assessment` + `AuditsChanges`; `AssessmentController` upload event |
| Diagnosis | `created`, `updated`, `deleted` | C | Actual RND actor; diagnosis under patient/NCP | approved patient identity and changed field names only | All active RNDs | New delete-reason enforcement deferred | Field names | `Diagnosis` + `AuditsChanges` |
| AI diagnosis generation/approval | `generated`, `approved` | C | Actual RND actor; NCP context | source/status/count metadata only; no prompt, output, diagnosis value, or clinical content | All active RNDs on authorized trail | None | Field names | `AiDiagnosisController` |
| Intervention | `created`, `updated`, `deleted` | C | Actual RND actor; intervention under patient/NCP | approved patient identity and changed field names only | All active RNDs may edit another RND's intervention | New delete-reason enforcement deferred | Field names | `Intervention` + `AuditsChanges` |
| Monitoring | `created`, `updated`, `deleted` | C | Actual RND actor; monitoring under patient/NCP | approved patient identity and changed field names only | All active RNDs may edit another RND's monitoring | New delete-reason enforcement deferred | Field names | `Monitoring` + `AuditsChanges` |
| Patient meal plan | `created`, `updated`, `deleted`, `generated` | C | Actual RND actor; meal plan/item under patient/NCP | approved patient identity; field names, generation type/count/status only; no meal contents/nutrients | All active RNDs may view/edit/delete where current workflow permits | New delete-reason enforcement deferred | Field names | `MealPlan` + `AuditsChanges`; `MealPlanController`; `MealPlanItemController` |
| Screening document | `created`, `updated`, `deleted`, `viewed`, `downloaded` | C | Actual RND actor; document under patient/NCP | document/attachment type, status, actor/time; no file, OCR, screening result, risk, or clinical value | All active RNDs may access another RND's record where applicable | New delete-reason enforcement deferred | Field names | `ScreeningDocument` + `AuditsChanges`; `ScreeningDocumentController` |
| Patient chart access | `viewed` | C | Actual RND actor; patient root | approved patient identity, action/time, record type, pseudonymous NCP reference | Authorized patient trail; view events are deduplicated | None | Field names | `PatientController` |
| RND Food Library item | `created`, `updated`, `deleted` | O | Actual RND actor; food item | safe name, category, serving/nutrient fields, price, USDA reference, active/ready state | Not a patient trail; Admin Nutrition Care tab | New delete-reason enforcement deferred | Drawer | `FoodItemController`; `FoodItemAuditValues` |
| USDA food import | `imported` | O | Actual RND actor; imported food item | safe source/count/reference and typed food values; no raw upstream response | Admin Nutrition Care tab | None | Drawer | `UsdaController` |
| RND clinical recipe | `created`, `updated`, `deleted` | O | Actual RND actor; recipe | safe recipe label/totals/servings and structured ingredients | Admin Nutrition Care tab | New delete-reason enforcement deferred | `rnd_recipe` revision | `RecipeController`; `AuditRevisionWriter` |

## Food Service Operations

| Event family | Actions | Cat. | Actor and subject/context | Safe Admin detail | FSS/Admin context trail | Reason | History | Primary writer/source |
|---|---|---:|---|---|---|---|---|---|
| FSS catalog item/ingredient | `created`, `updated`, `deleted` | O | Actual FSS actor; catalog item | safe name, kind/category, units, costs, vendor/active state | Admin global; authorized FSS workflow | New delete-reason enforcement deferred | Drawer | `FsItemController`; `FsItemAuditValues` |
| FSS recipe | `created`, `updated`, `deleted` | O | Actual FSS actor; recipe | safe recipe metadata and ingredient structure | Admin global; authorized FSS workflow | New delete-reason enforcement deferred | `food_service_recipe` revision | `FoodServiceRecipeController` |
| Supplier | `created`, `updated`, `deleted` | O | Actual FSS actor; supplier | safe name/category/contact/address/payment terms/status according to allow-list | Admin global; authorized FSS workflow | New delete-reason enforcement deferred | Drawer | `SupplierController`; `SupplierAuditValues` |
| Menu-cycle template | `created`, `updated`, `deleted` | O | Actual FSS actor; template | safe template metadata and complete slot structure | Admin global | New delete-reason enforcement deferred | `menu_cycle_template` revision | `MenuCycleTemplateController` |
| Menu cycle/weekly plan | `created`, `updated`, `deleted` | O | Actual FSS actor; menu cycle | cycle dates/status/totals and full event-time slots/day totals | Admin global | New delete-reason enforcement deferred | `menu_cycle` revision | `MenuCycleController` |
| Shopping list | `created`, `updated`, `deleted`, `generated` | O | Actual FSS actor; shopping list and safe menu context | safe state, counts/totals, item structure, linked references | Admin global; authorized FSS workflow | New delete-reason enforcement deferred | `shopping_list` revision | `ShoppingListController` |
| Purchase-order draft/edit/delete | `updated`, `deleted`, `uploaded` | O | Actual FSS actor; PO root, vendor groups/attachments as context | safe status, totals, counts, lines, vendor groups, attachment metadata; no file contents | PO trail for authorized FSS/Admin | New delete/correction reason enforcement deferred | `purchase_order` revision | `PurchaseOrderController` |
| PO lifecycle | `approved`, `ordered`, `received`, `completed`, `archived` | O | Actual FSS or named system actor; PO root | state transition, safe totals/counts/references, actor/time | PO trail | Reason shown when supplied; no new approval workflow | `purchase_order` revision | `PurchaseOrderController`; `PurchaseOrderLifecycleService`; `ReceivingService` |
| PO line price correction | `price_corrected` | O | Actual FSS actor; PO root/corrected line context | old/new typed unit price and safe totals/counts/reference | PO trail | Existing bounded reason shown; broader enforcement deferred | `purchase_order` revision | `PurchaseOrderController` |
| Hospital population/diet-list count | `created` | O | Actual FSS actor; service-date context | population/date/count typed values | Admin global | None | Drawer | `DietListCountController` |
| Meal-service/preparation log | `created`, `updated`, `adjusted`, `completed`, `reversed` | O | Actual FSS actor; service/menu context | served/estimated population, variance, status, task booleans, safe totals | Admin global | Reversal/correction reason shown when supplied; new enforcement deferred | Drawer | `MealPrepLogController`; `MealPrepLog` audit concern |
| Food-service setting | `settings_changed` | O | Actual Admin/FSS actor; setting subject | old/new `per_head_day_limit`, reevaluated PO count where applicable | Admin global; budget context when linked | Routine reason optional/current behavior | Drawer | `FoodServiceSettingController` |
| Fiscal-year budget setup | `created`, `adjusted` | O | Actual FSS actor; budget/fiscal-year context | fiscal year, opening allocation, per-head/day, balances, source | Budget trail; Admin read-only | Existing request behavior | `budget` revision | `BudgetController` |
| Manual ledger entry | `adjusted` | O | Actual FSS actor; budget/ledger context | type/source, amount/signed amount, balance before/after, fiscal year, safe reference | Budget trail; Admin read-only | Required bounded reason | `budget` revision where parent event qualifies | `BudgetController`; `BudgetLedgerListener` |
| PO budget deduction | `adjusted` | O | Named system actor; budget and PO context | amount, balance before/after, fiscal year, PO public reference | Budget and PO trails; Admin read-only | System-generated reason/reference | `budget`/PO event-time context | `BudgetLedgerListener`; PO lifecycle service |

There is no budget approve/reject/flag event and no ledger reversal workflow. `reversed` refers to meal-service completion reversal, not a budget-ledger reversal.

## Reports

Report events expose safe report type/status/format/count/period metadata only. Patient-linked report events use category Clinical and may expose only the dedicated patient display-name snapshot plus the standard clinical metadata boundary. Report parameters, snapshots, generated contents, files, data URLs, and patient filters never enter audit output or revisions.

| Event family | Actions | Category | Actor and subject/context | Safe Admin detail | RND/FSS/Admin trail | Reason | History | Primary writer/source |
|---|---|---:|---|---|---|---|---|---|
| Report generation completed/failed | `generated` | O or C when patient-linked | Requesting user or report worker; report and safe context | report type, status, format, period reference, outcome; clinical boundary if patient-linked | Active RNDs share RND report workspace; FSS/Admin keep type limits | None | Drawer | `ReportController`; `GenerateReport`; `AccomplishmentReportArchiveService` |
| Report view | `viewed` | O or C | Actual viewer; report context | report type/status and time only | Authorized report trail | None | Drawer | `ReportController` |
| Report download/export-format access | `downloaded` | O or C | Actual viewer; report context | report type/format and time only; no bytes/contents | Authorized report trail | None | Drawer | `ReportController` |
| Report archive | `archived` | O or C | Actual actor or named system actor; report context | safe status/type/period/outcome | Authorized report trail | None | Drawer | `ReportController`; `AccomplishmentReportArchiveService` |
| Report deletion | `deleted` | O or C | Actual authorized actor; report context | safe type/status; clinical boundary if patient-linked | Authorized report trail remains attributable while retained | New delete-reason enforcement deferred | Drawer | `ReportController` |
| Branding/template update | `updated` | O | Actual Admin/RND actor under route policy; branding/template subject | allow-listed field names/status; no arbitrary template payload | Admin global | Routine reason not required | Drawer | `ReportBrandingController`; `ReportTemplateController` |

The `exported` action is not used for normal report file delivery. Audit CSV export is a guarded future-compatible Admin endpoint and is disabled/hidden.

## Complete action index

Every current `AuditAction` enum value appears below. This index prevents an action from existing without a documented meaning.

| Action | Current meaning and module(s) | Normal visibility |
|---|---|---|
| `created` | Account/admin, clinical model, Food Library/recipe, FSS catalog/menu/shopping/budget records | Typed by module; clinical values hidden |
| `updated` | Ordinary safe or clinical updates and report state changes | Typed before/after or clinical field names |
| `deleted` | Account, clinical, library/recipe, FSS, report deletion | Safe final state/history when available; clinical field names only |
| `viewed` | Patient chart, screening document, report | Privacy-classified metadata only |
| `downloaded` | Screening document or report access | Metadata only; never file content |
| `exported` | Disabled Admin audit CSV compatibility endpoint only | Hidden while disabled; backend guarded |
| `approved` | AI diagnosis approval and PO approval | Clinical metadata or safe PO lifecycle |
| `ordered` | PO ordered transition | Food Service Operations |
| `received` | PO receiving | Food Service Operations |
| `reversed` | Meal-service completion reversal | Food Service Operations; not a ledger reversal |
| `archived` | Report/PO archive lifecycle | Reports or Food Service Operations |
| `adjusted` | Budget/ledger and meal-service population/state correction | Food Service Operations |
| `uploaded` | Assessment or PO attachment workflow | Clinical field/type metadata or safe attachment metadata |
| `imported` | USDA food import | Nutrition Care Food Library |
| `generated` | AI/meal-plan, shopping-list, and report generation | Classified by subject/module |
| `completed` | PO/meal-service lifecycle and audit prune completion | Food Service Operations or Security & Administration oversight |
| `price_corrected` | PO line price correction | Food Service Operations |
| `profile_changed` | User profile change | Security & Administration |
| `settings_changed` | Retention, AI limit, food-service/report settings | Security & Administration, FSO, or Reports |
| `login_succeeded` | Successful authentication | Security & Administration |
| `login_failed` | Invalid/inactive/platform login rejection | Security & Administration |
| `authentication_failed` | Protected-route authentication rejection | Security & Administration |
| `logout` | Sign-out | Security & Administration |
| `password_changed` | Authenticated password change | Security & Administration |
| `password_reset` | User/Admin password reset | Security & Administration |
| `recovery_email_changed` | Recovery-email change | Security & Administration; address omitted |
| `recovery_email_verified` | Recovery-email verification | Security & Administration; address/code omitted |
| `rate_limit_exceeded` | Named limiter rejection | Security & Administration |
| `authorization_denied` | Role/policy rejection | Security & Administration |
| `audit_log_viewed` | Global audit or contextual clinical trail access | Security & Administration oversight |
| `account_blocked` | Admin deactivated account | Security & Administration |
| `account_unblocked` | Admin reactivated account | Security & Administration |

## Compatibility and exclusions

- Stored `category` and `domain` remain for privacy, retention, legacy classification, and internal filters. They are not normal UI filters; list requests containing them return `422`.
- Legacy event value `login` presents as `login_succeeded`. Unknown safe legacy actions remain visibly named; blank/unsafe values become `legacy_event` rather than `updated`.
- `IpBlocked`, `IpUnblocked`, IP-blocking configuration/model/controller/migration/UI, raw JSON presentation, mutable inventory history, and synthetic demo-audit seed events do not exist.
- `AccountBlocked` and `AccountUnblocked` remain active and are not IP blocking.
- Audit export stays disabled and omitted from normal filter metadata/capabilities. Its guarded backend/proxy surface remains only for future compatibility.
