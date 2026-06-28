# FSS Mobile Execution Flow

Reference chart: `docs/modules/Flowcharts/Food Service Operations.md`.

Purpose: show how FSS mobile navigation, data entry, backend processing, PO lifecycle updates, and required displays connect from login through APK-ready production demo.

```mermaid
flowchart TD
    A[FSS opens mobile app] --> B{Stored Sanctum token?}
    B -- No --> C[Login screen]
    C --> D[POST /api/auth/login\nplatform app\ndevice_name Expo App]
    D --> E{Laravel auth result}
    E -- 401 --> E1[Show invalid credentials\nproduction seed or password issue]
    E -- 403 --> E2[Show wrong role\nmobile app is FSS only]
    E -- No token --> E3[Show API origin error\nmobile is probably hitting web domain]
    E -- Token + FSS user --> F[Store token\nAuthorization Bearer for API calls]
    B -- Yes --> F

    F --> G[Bottom navigation\nDashboard\nMenu\nPrep & Accomp.\nProcurement]
    F --> H[Header navigation\nAnnouncements\nNotifications\nSettings -> Profile]

    G --> DASH[Dashboard]
    DASH --> DSUM[GET /api/fss/dashboard/summary]
    DSUM --> DKPI[Display meals to log today\npending PO count\ntoday service]
    DSUM --> DPO[Display pending PO list\nPO number\ntrack\nwaiting_on reasons]
    DASH --> DANN[GET /api/fss/announcements\nDisplay FSS announcements]
    H --> DNOTIF[GET /api/notifications\nDisplay badge and notifications]

    G --> PREP[Prep & Accomp.]
    PREP --> PSUM[GET /api/fss/dashboard/summary\nToday's service]
    PSUM --> PMARK[FSS taps Mark served]
    PMARK --> PCOMP[POST /api/fss/menu-cycles/{cycleId}/complete-day]
    PCOMP --> PSHORT{Ingredient stock shortfall?}
    PSHORT -- Yes --> PCONFIRM[Show prep shortfall confirmation\nFSS does not manage inventory]
    PCONFIRM --> PCOMP2[POST complete-day\nallow_shortfall true]
    PSHORT -- No --> PCONSUME[ConsumptionService completes day]
    PCOMP2 --> PCONSUME
    PCONSUME --> PLOG[MealPrepLog completed\nbackend inventory deduction]
    PLOG --> PLIFE[PurchaseOrderLifecycleService refreshes POs for service date]

    PREP --> DFORM[Diet-list/accomplishment form]
    DFORM --> DPOST[POST /api/fss/diet-list-counts\nservice_date\nward\npopulation\noff_duty\n7 task flags\nmenu_cycle_id optional]
    DPOST --> DROW[Store row under current FSS user]
    DROW --> DSYNC[Sync served population to MealPrepLog when matching log exists]
    DSYNC --> PLIFE
    DROW --> DARCH[WeeklyAccomplishmentArchiveService updates archive]
    G --> MENU[Menu tab]
    PREP --> MENUOPEN[Can also open Menu from Prep]
    MENUOPEN --> MENU
    MENU --> MCYCLES[GET /api/fss/menu-cycles]
    MCYCLES --> MDETAIL[GET /api/fss/menu-cycles/{id}]
    MDETAIL --> MPROFILE[Display day meals\nrecipe/item profiles\nserved population rows]
    MPROFILE --> MSERVED[FSS edits served population]
    MSERVED --> MPATCH[PATCH /api/fss/menu-cycles/{id}/served-population]
    MPATCH --> MLOG[Create/update MealPrepLog unless completed PO locks date]
    MLOG --> PLIFE

    G --> PROC[Procurement]
    PROC --> POLIST[GET /api/fss/purchase-orders]
    POLIST --> PODETAIL[Open PO detail\nvendor groups\nread-only line items]
    PODETAIL --> ORSAVE[FSS saves OR number]
    ORSAVE --> ORPATCH[PATCH /api/fss/purchase-order-vendor-groups/{id}\nor_number only for FSS]
    PODETAIL --> UPLOAD[FSS uploads receipt or proof]
    UPLOAD --> ATTACH[POST /api/fss/purchase-order-vendor-groups/{id}/attachments]
    ATTACH --> STORE[Store attachment on public disk]
    ATTACH --> RECEIPT{Attachment type receipt?}
    RECEIPT -- No --> PROOF[Proof saved for audit/display]
    RECEIPT -- Yes --> REC[Mark vendor group received\nreceived_at set]
    REC --> STOCK[ReceivingService receives vendor group once\nstocked_at set]
    STOCK --> PLIFE

    PLIFE --> TRACK{PO track}
    TRACK -- Supplies --> SUPCHECK{All vendor groups have receipts?}
    SUPCHECK -- No --> OPEN1[PO remains open\nDashboard waiting_on receipts]
    SUPCHECK -- Yes --> SUPDONE[Supplies PO completed\nlocked]
    TRACK -- Food --> FOODCHECK{All receipts complete\nand all covered dates have served population\nand budget exists?}
    FOODCHECK -- No --> OPEN2[PO remains open\nDashboard waiting_on receipts or served_population\nbudget blocker should be surfaced]
    FOODCHECK -- Yes --> FOODDONE[Food PO completed\nactual budget/head/day calculated\nmenu day snapshots locked]
    FOODDONE --> LEDGER[Budget ledger PO deduction]
    SUPDONE --> LEDGER

    H --> PROFILE[Profile]
    PROFILE --> PGET[GET /api/user]
    PGET --> PDISPLAY[Display name\nemail\ncontact number\nrole\nstatus\nprofile photo if present]
    PDISPLAY --> PPATCH[PATCH profile\nname\nemail\ncontact_number]

    PREP --> REPORTS[Reports]
    REPORTS --> RDATA[Read accomplishment/report data\nFSS should see own mobile rows\nRND/Admin use broader report routes]
```

## Display Contract

- Dashboard must show `meals_to_log_today`, `pending_pos_count`, `pending_pos[]`, `waiting_on`, today's service, and announcements.
- Dashboard should show today's service and a current active menu week summary/card that links to Menu.
- Prep must show today's service, prep shortfall confirmation, diet-list/accomplishment inputs, and route to full Menu Cycle.
- Menu tab must be visible in bottom navigation and show the menu-cycle list, with active cycle first.
- Menu Cycle must show active/upcoming/completed cycles, daily meals, food/recipe profiles, and served population inputs.
- Procurement must show PO details, vendor groups, read-only line items, OR number, receipt upload, proof upload, and attachment previews.
- Profile must show name, email, contact number, role, status, and existing profile photo when available.

## Data Processing Rules

- Mobile login must call Laravel directly, not the Next.js web proxy.
- FSS mobile uses Sanctum Bearer tokens.
- FSS can enter actual execution data only: served population, diet-list/accomplishment rows, OR number, receipt image, proof image.
- FSS cannot create or edit recipes, menu-cycle plans, shopping lists, PO structure, suppliers, inventory stock, prices, quantities, or budgets.
- Receipt upload is the backend event that marks vendor group received and triggers receiving logic.
- Food PO completion waits for receipts plus served population for every covered service date.
- Supplies PO completion waits for receipts only.
- Completed/archived PO data is locked.

## Verified Gaps To Implement

- Menu tab visibility is required; older code/docs hid it with `href: null`.
- Dashboard currently uses `pending_pos_count` but does not display `pending_pos[].waiting_on`.
- Dashboard currently shows today's service but not a current active menu week summary/card.
- `DietListCountController@index()` needs ordinary FSS self-scope.
- `PurchaseOrderController::updateVendorGroup()` needs backend role enforcement so FSS cannot patch `items` or `status`.
- Production mobile builds need `EXPO_PUBLIC_API_URL=https://api.nutriscope.live`, not `https://nutriscope.live`.
- Mobile login needs a no-token response guard to catch wrong API origin.
- Profile should display role/status/contact number because backend already provides those fields.
- Docs must remove stale FSS Inventory tab references and keep visible Menu tab references.

## Related Files

- `docs/modules/Flowcharts/Food Service Operations.md` - broad Food Service workflow used as the parent reference.
- `docs/superpowers/plan/2026-06-28-fss-mobile-login-production-readiness-plan.md` - implementation plan tied to this flow.
- `mobile/app/(tabs)/_layout.tsx` - FSS bottom navigation with visible Menu route.
- `mobile/components/AppHeader.tsx` - header navigation.
- `mobile/app/login.tsx` - mobile auth flow.
- `mobile/lib/api.ts` - Bearer API client.
- `mobile/app/(tabs)/index.tsx` - Dashboard displays.
- `mobile/app/(tabs)/prep.tsx` - Prep, mark served, diet-list/accomplishment.
- `mobile/app/(tabs)/menu.tsx` - full Menu Cycle and served population backfill.
- `mobile/app/(tabs)/procurement.tsx` - OR number and attachment workflow.
- `mobile/app/profile.tsx` - profile content.
- `backend/app/Http/Controllers/Auth/AuthController.php` - mobile role gate and token creation.
- `backend/app/Services/FSS/FssDashboardService.php` - dashboard source fields.
- `backend/app/Http/Controllers/FSS/MealPrepLogController.php` - mark served and served-population endpoints.
- `backend/app/Http/Controllers/FSS/DietListCountController.php` - diet-list/accomplishment rows.
- `backend/app/Http/Controllers/FSS/PurchaseOrderController.php` - vendor group and attachment endpoints.
- `backend/app/Services/FSS/PurchaseOrderLifecycleService.php` - completion rules.
- `backend/app/Services/FSS/ReceivingService.php` - receipt-driven receiving.
- `backend/app/Services/FSS/ConsumptionService.php` - meal completion inventory processing.
- `frontend/app/api/auth/login/route.ts` - web login proxy that mobile must not use.
- `mobile/eas.json` - APK build-time API origin.
- `docker-compose.prod.yml` - production backend exposure.
- `deployment.md` - VPS deployment and API subdomain instructions.
