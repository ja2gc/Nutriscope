export type HelpRole = "Shared" | "RND" | "Admin";
export type WebHelpRole = Exclude<HelpRole, "Shared">;

export interface HelpItem {
  id: string;
  role: HelpRole;
  category: string;
  question: string;
  answer: string;
  keywords: string[];
  popular?: boolean;
}

export interface HelpGroup {
  category: string;
  items: HelpItem[];
}

export const HELP_ITEMS: HelpItem[] = [
  {
    id: "shared-forgot-password",
    role: "Shared",
    category: "Account & Access",
    question: "I forgot my password. What should I do?",
    answer: "On the sign-in page, choose Forgot password and enter your sign-in email. NutriScope sends the newest reset link to the verified recovery email saved for that account. If no verified recovery email is attached, ask an Admin to reset the password.",
    keywords: ["reset", "login", "recovery email", "cannot sign in"],
    popular: true,
  },
  {
    id: "shared-generic-reset-response",
    role: "Shared",
    category: "Account & Access",
    question: "I requested a reset but received no email. What should I check?",
    answer: "Check the inbox and spam folder of your verified recovery email, which may differ from your sign-in email. The confirmation on screen does not guarantee delivery. Confirm your sign-in address before requesting again. If recovery was never verified or that inbox is unavailable, contact an Admin.",
    keywords: ["email not received", "security", "account exists"],
  },
  {
    id: "shared-reset-link-expired",
    role: "Shared",
    category: "Account & Access",
    question: "My reset link does not work. What now?",
    answer: "Reset links expire after 15 minutes. Request another link and open only the latest message. If the newest link still fails, contact an Admin.",
    keywords: ["expired token", "invalid link", "password"],
  },
  {
    id: "shared-first-login",
    role: "Shared",
    category: "Account & Access",
    question: "Why does the account setup reminder keep appearing?",
    answer: "A new account needs a new password and a recovery email verified with a six-digit code. Choosing Do later leaves a persistent reminder until both steps are complete. Select Open settings in the reminder to finish in Profile. Removing recovery brings the reminder back.",
    keywords: ["temporary password", "onboarding", "account setup"],
    popular: true,
  },
  {
    id: "shared-deactivated",
    role: "Shared",
    category: "Account & Access",
    question: "Why does sign-in say my account is deactivated?",
    answer: "An inactive account cannot sign in. Contact an Admin to confirm whether access should be restored. Do not create a second account as a workaround.",
    keywords: ["disabled", "inactive", "status", "login"],
  },
  {
    id: "shared-email-types",
    role: "Shared",
    category: "Account & Access",
    question: "How do I add or change my recovery email?",
    answer: "Open Profile. Beside Recovery email, choose Add if no address is set, or Edit > Change to replace it. Enter an inbox you can access and choose Send Verification Code (Send code on narrow screens). The code field appears after a successful send; enter the six-digit code within 10 minutes and choose Verify Recovery Email (Verify on narrow screens). Cancel discards the replacement and keeps the current address. To remove the current address, use Edit > Remove and confirm. Password-reset emails are unavailable until another recovery address is verified.",
    keywords: ["login email", "forgot password", "verification"],
  },
  {
    id: "shared-change-password",
    role: "Shared",
    category: "Account & Access",
    question: "How do I change my password while signed in?",
    answer: "Open Profile and choose Change Password (Change on narrow screens). Enter the current password, then enter and confirm a new password of at least eight characters. Choose Update Password (Save on narrow screens). If the current password is unknown, use password recovery or contact Admin.",
    keywords: ["security", "profile", "eight characters"],
  },
  {
    id: "shared-session-revoked",
    role: "Shared",
    category: "Account & Access",
    question: "Why was I signed out unexpectedly?",
    answer: "Sign in again with your current details. A password reset or an account change can end your sign-in. If the login page keeps returning, check your connection and contact an Admin.",
    keywords: ["token", "logged out", "role change", "password reset"],
  },
  {
    id: "shared-save-failed",
    role: "Shared",
    category: "Using NutriScope",
    question: "What should I do if a save fails?",
    answer: "Keep the page open, read the field or page message, correct missing values, and retry once after checking connectivity. If it still fails, report the screen, time, action, and safe error wording without sending passwords or patient data.",
    keywords: ["error", "validation", "network", "retry"],
    popular: true,
  },
  {
    id: "shared-announcement-sop",
    role: "Shared",
    category: "Announcements & SOP",
    question: "What is the difference between an announcement and an SOP?",
    answer: "Announcements communicate updates to a selected audience. The SOP is the current department procedure and keeps version history when revised. Read the current SOP before relying on an older announcement.",
    keywords: ["procedure", "post", "communication", "history"],
    popular: true,
  },
  {
    id: "shared-sop-revision",
    role: "Shared",
    category: "Announcements & SOP",
    question: "How do I find an older SOP version?",
    answer: "Open Announcements > SOP > History, then select the saved title. Check the current SOP before following an older procedure.",
    keywords: ["edit procedure", "history", "version"],
  },
  {
    id: "shared-notification-state",
    role: "Shared",
    category: "Announcements & SOP",
    question: "Why can I not dismiss a reminder?",
    answer: "Some reminders remain until the related task is resolved. Open the reminder and complete or correct the underlying task. Reading a reminder does not complete the work.",
    keywords: ["read", "unread", "open", "destination"],
  },

  {
    id: "rnd-start-patient",
    role: "RND",
    category: "Nutrition Care Process",
    question: "How do I start care for a new patient?",
    answer: "Open Nutrition Care > Patients, create the patient, and start an Assessment. Confirm the patient header before entering clinical information so data is saved to the intended record.",
    keywords: ["create patient", "assessment", "new NCP"],
  },
  {
    id: "rnd-patient-contact-details",
    role: "RND",
    category: "Nutrition Care Process",
    question: "How do I save a patient's preferred contact?",
    answer: "Open the patient's Overview > Edit contact details. Add the method and value, check Preferred beside the contact to use first, then Save contact details. Up to five additional contacts can be recorded.",
    keywords: ["phone", "email", "messenger", "preferred contact"],
  },
  {
    id: "rnd-multiple-cycles",
    role: "RND",
    category: "Nutrition Care Process",
    question: "Can a patient have more than one NCP cycle?",
    answer: "Yes. Prior cycles remain in ADIME Records. Start a new cycle for a distinct episode or approved follow-up workflow rather than overwriting historical care.",
    keywords: ["ADIME", "history", "new cycle"],
  },
  {
    id: "rnd-diagnosis-gate",
    role: "RND",
    category: "Nutrition Care Process",
    question: "Why does Diagnosis say Assessment Required?",
    answer: "Diagnosis needs a saved Assessment for the same NCP cycle. Return to Assessment, complete required fields, save, and then reopen Diagnosis.",
    keywords: ["blocked", "save assessment", "PES"],
  },
  {
    id: "rnd-intervention-gate",
    role: "RND",
    category: "Nutrition Care Process",
    question: "Why is Intervention blocked?",
    answer: "Intervention requires the cycle's Assessment and at least one saved Diagnosis. Complete and save those steps before creating goals, prescriptions, or meal plans.",
    keywords: ["blocked", "diagnosis", "goal"],
  },
  {
    id: "rnd-monitoring-gate",
    role: "RND",
    category: "Nutrition Care Process",
    question: "Why is Monitoring blocked?",
    answer: "Monitoring is intended for follow-up and requires saved Assessment, Diagnosis, and Intervention data for the cycle. Save each earlier step before opening Monitoring.",
    keywords: ["follow-up", "visit log", "progress"],
  },
  {
    id: "rnd-dry-weight",
    role: "RND",
    category: "Assessment",
    question: "Why must I enter dry weight when edema is present?",
    answer: "Edema can make measured weight unsuitable for nutrition calculations. NutriScope requires dry weight when edema is marked present so the saved intervention autofill uses the clinically intended weight basis.",
    keywords: ["edema", "calculation", "actual weight"],
    popular: true,
  },
  {
    id: "rnd-upload-no-ocr",
    role: "RND",
    category: "Assessment",
    question: "Does uploading a lab or referral file fill Assessment automatically?",
    answer: "No. Uploads are supporting documents for the NCP cycle. They do not run OCR or populate clinical fields. Enter and verify clinical values manually.",
    keywords: ["attachment", "OCR", "autofill", "document"],
  },
  {
    id: "rnd-summary",
    role: "RND",
    category: "Assessment",
    question: "What does Generate Summary do?",
    answer: "It drafts an RND Summary from current Assessment entries. Review and edit the text before saving; regeneration must not replace professional judgment.",
    keywords: ["AI", "draft", "regenerate", "undo"],
  },
  {
    id: "rnd-risk-score",
    role: "RND",
    category: "Assessment",
    question: "Is the nutritional risk score final clinical judgment?",
    answer: "No. Screening identifies patients needing further assessment; it does not establish a nutrition diagnosis or its severity. Review measurements, clinical findings and the BMI information button before making your clinical judgment.",
    keywords: ["screening", "clinical decision", "risk"],
  },
  {
    id: "rnd-pes",
    role: "RND",
    category: "Diagnosis & Intervention",
    question: "How do I write a nutrition diagnosis?",
    answer: "Select the Problem, Etiology and Signs/Symptoms supported by Assessment. Use Other when a needed choice is unavailable. Review the PES statement, add reasoning notes where needed, then save.",
    keywords: ["problem", "etiology", "signs", "PES"],
  },
  {
    id: "rnd-ai-diagnosis",
    role: "RND",
    category: "Diagnosis & Intervention",
    question: "How should I use AI Review suggestions?",
    answer: "Check each draft against Assessment and its supporting notes. Items marked May need review need closer checking; incomplete possible concerns cannot be accepted as diagnoses. Accept only a clinically supported statement, or enter the diagnosis manually. Check Assessment before generating again if no useful suggestion is returned.",
    keywords: ["suggestion", "accept", "edit", "checkbox", "dismiss", "manual", "review"],
  },
  {
    id: "rnd-intervention-goal",
    role: "RND",
    category: "Diagnosis & Intervention",
    question: "What happens when I set an intervention goal?",
    answer: "Choose the goal, review the prescription and adjust it to the patient's assessed needs, then save. Review fluid guidance separately from food portions; estimated fluid from foods does not represent the entire fluid prescription.",
    keywords: ["Save Goal", "prescription", "objective"],
  },
  {
    id: "rnd-meal-plan",
    role: "RND",
    category: "Diagnosis & Intervention",
    question: "Can I make a patient meal plan manually or from a template?",
    answer: "Yes. In the latest Intervention Plan, add menu items, load a template or use Auto-Generate. Review allergies, restrictions, portions and nutrient totals before using the plan. Menu changes save directly. Earlier plans are View only; create a new dated Intervention Plan when treatment changes.",
    keywords: ["template", "generate", "menu", "nutrition totals"],
  },
  {
    id: "rnd-tube-formulas",
    role: "RND",
    category: "Diagnosis & Intervention",
    question: "How do I plan Tube Feeding formulas?",
    answer: "Set Nutrition support to Tube Feeding in Assessment. In Intervention, add saved formulas, load a Tube Feeding template or choose Create formula. Review nutrients and adjust the recipe multiplier or ingredients. Reset recipe restores the captured original. Formula plans do not use weekdays or automatic prescription scaling.",
    keywords: ["tube feeding", "formula", "nutrition support", "per orem", "npo", "tpn", "portion", "reset"],
  },
  {
    id: "rnd-monitoring",
    role: "RND",
    category: "Monitoring",
    question: "How do I save and correct a follow-up visit?",
    answer: "In Monitoring, enter follow-up findings and the required Progress Assessment. Review any generated summary before choosing Use this summary, then Save Visit. Only the latest saved visit in an active cycle can be edited or deleted; older visits are View only. Use a new dated Intervention Plan to change treatment.",
    keywords: ["visit log", "progress trend", "follow-up"],
  },
  {
    id: "rnd-delete-record",
    role: "RND",
    category: "Patient Records",
    question: "Can I delete a patient or NCP cycle?",
    answer: "Deletion depends on the available authorized action and confirmation. Treat it as destructive because related records may be affected. Preserve history when correction or a new cycle is more appropriate.",
    keywords: ["remove", "destructive", "history"],
  },
  {
    id: "rnd-attachments",
    role: "RND",
    category: "Patient Records",
    question: "Where are patient attachments?",
    answer: "Patient-level files are available from the patient profile Attachments area. Assessment supporting files remain associated with their NCP cycle and should be opened from the relevant clinical context.",
    keywords: ["files", "documents", "upload", "profile"],
  },
  {
    id: "rnd-library-difference",
    role: "RND",
    category: "Food Library & Food Service",
    question: "Which food catalog should I prepare before planning?",
    answer: "Use Food Library ingredients, menu items and templates for patient Intervention plans. For the operational Menu Calendar and Shopping Lists, prepare Food Service > Inventory ingredients and its Menu Items tab. These are separate catalogs; a clinical recipe does not become an operational menu item.",
    keywords: ["recipes", "ingredients", "USDA", "catalog"],
    popular: true,
  },
  {
    id: "rnd-usda",
    role: "RND",
    category: "Food Library & Food Service",
    question: "How do I import food nutrient data?",
    answer: "In Food Library, choose Import from USDA, search for the food, inspect its nutrient profile, and confirm the import. Review serving units and values before using it in care planning.",
    keywords: ["USDA", "nutrients", "food data"],
  },
  {
    id: "rnd-inventory-units",
    role: "RND",
    category: "Food Library & Food Service",
    question: "Why can I not save an Inventory unit change?",
    answer: "The new unit must match quantities used by affected menu items. Follow the affected-item links to correct each quantity and unit, then return and save Inventory. Weight converts within weight units and volume within volume units; pieces and packs require the same unit. Auto Grocery ingredients need compatible units for Shopping Lists.",
    keywords: ["unit", "conversion", "recipe", "adjust to taste", "auto grocery"],
  },
  {
    id: "rnd-menu-cycle",
    role: "RND",
    category: "Food Library & Food Service",
    question: "How do I plan a Menu Calendar week?",
    answer: "Open the current Monday–Sunday week, fill meal slots, and save. Empty calendars for the current and next week are created automatically. You can also fill a past week or load a template. The purchase estimate is entered once later when generating a suggested shopping list.",
    keywords: ["week", "meal slots", "population"],
  },
  {
    id: "rnd-menu-template",
    role: "RND",
    category: "Food Library & Food Service",
    question: "What is a Menu Plan template?",
    answer: "It is a reusable weekly menu. Load it into a dated Menu Calendar, confirm replacement if prompted, review and save. Editing the dated week does not change the template.",
    keywords: ["reuse", "copy menu", "week"],
  },
  {
    id: "rnd-activate-menu",
    role: "RND",
    category: "Food Library & Food Service",
    question: "When does a weekly menu become current?",
    answer: "The week covering today's date is current automatically, even if empty. Fill and save its meals. Once a food PO has been generated, its covered planning data is locked.",
    keywords: ["FSS", "current menu", "weekly"],
  },
  {
    id: "rnd-shopping-list",
    role: "RND",
    category: "Procurement & Budget",
    question: "What must I prepare before generating a Shopping List?",
    answer: "Plan every included Menu Calendar date and check operational ingredients, units and vendors. Create a suggested food list, select its start date and duration, and enter Estimated servings. Review the preview and add any needed non-Auto Grocery items before Save Shopping List. Turn Into PO is a separate action that locks the list.",
    keywords: ["procurement", "menu", "generate", "purchase"],
  },
  {
    id: "rnd-shopping-quantity",
    role: "RND",
    category: "Procurement & Budget",
    question: "Does changing a Shopping List quantity change Estimated servings?",
    answer: "No. Purchase Qty changes only the amount to buy. Review Quantity, Cost/unit and Total together. Reset generated items restores original values at the current Estimated servings while keeping manual additions; older lists without a saved original cannot be reset.",
    keywords: ["round", "quantity", "estimate", "discard"],
  },
  {
    id: "rnd-supplies",
    role: "RND",
    category: "Procurement & Budget",
    question: "How are supplies purchased?",
    answer: "Use the supply purchasing workflow to select catalog items, quantities, and vendors. Review the request separately from menu-derived food requirements.",
    keywords: ["shopping list", "vendor", "catalog"],
  },
  {
    id: "rnd-vendor-contact-details",
    role: "RND",
    category: "Procurement & Budget",
    question: "Can a vendor have more than one contact method?",
    answer: "Yes. Open Procurement > Suppliers, create or edit the vendor, add contact methods and choose Preferred for the one to show first. Save Changes or Add Vendor saves the details. Completed procurement reports keep their saved details.",
    keywords: ["supplier", "phone", "email", "messenger", "preferred contact"],
  },
  {
    id: "rnd-convert-po",
    role: "RND",
    category: "Procurement & Budget",
    question: "What happens when a shopping list is converted?",
    answer: "Turn Into PO locks the list after confirmation and groups the order by vendor. Check vendors and quantities first. Actual values, receipt and proof are entered on the order; mark each vendor received after checking the delivery. OR number is optional.",
    keywords: ["PO", "vendor group", "receipt", "change vendor"],
  },
  {
    id: "rnd-po-inventory-reports",
    role: "RND",
    category: "Procurement & Budget",
    question: "Why have purchase costs not appeared in Budget yet?",
    answer: "Costs are recorded when the whole PO completes. Check each vendor's actual values, receipt, proof and received status. Suggested food POs also need served population for every covered date. Completion updates Inventory cost/vendor defaults and finalizes procurement reports.",
    keywords: ["completed PO", "vendor", "budget", "report"],
  },
  {
    id: "rnd-po-completion",
    role: "RND",
    category: "Procurement & Budget",
    question: "When does a food purchase order complete?",
    answer: "Each vendor needs actual quantities and costs, receipt, proof, and RND confirmation that the vendor was received. A suggested food PO also needs served population for each covered date; manual food and supplies do not.",
    keywords: ["received", "status", "receipt", "served days"],
  },
  {
    id: "rnd-budget-per-head",
    role: "RND",
    category: "Procurement & Budget",
    question: "What is budget per head per day?",
    answer: "It is the allowed food-service budget basis per person per service day. NutriScope uses it with population and period context; verify the active fiscal-year setup before interpreting comparisons.",
    keywords: ["population", "fiscal year", "cost"],
  },
  {
    id: "rnd-budget-edit",
    role: "RND",
    category: "Procurement & Budget",
    question: "How do I correct a budget balance?",
    answer: "Open Budget, select the fiscal year and add a manual adjustment with the correct amount, date and reason. Review the ledger afterward. Existing entries are not edited; use a documented correcting adjustment.",
    keywords: ["ledger", "adjustment", "Admin"],
  },
  {
    id: "rnd-accomplishment-review",
    role: "RND",
    category: "Reports",
    question: "Where do I review Food Service Staff accomplishment reports?",
    answer: "Open Reports → Accomplishment Report and select a staff name. That staff card has Approved Reports and To Review tabs, each with a report-month filter. Open a submitted PDF in To Review, then approve or reject with a required reason of up to 500 characters. Rejected periods return to FSS for correction and resubmission. Earlier generated reports remain in Approved Reports.",
    keywords: ["accomplishment", "FSS", "approve", "reject", "review"],
  },
  {
    id: "rnd-live-archive",
    role: "RND",
    category: "Reports",
    question: "What is the difference between live preview and archived report?",
    answer: "Active reports normally reflect current data. Completed-PO procurement reports are finalized at PO completion. An archived report also stays unchanged when source records are edited later.",
    keywords: ["snapshot", "history", "current data"],
  },

  {
    id: "admin-create-user",
    role: "Admin",
    category: "Users & Access",
    question: "How do I create a user?",
    answer: "Open Manage Users, create the active account with the correct role, and provide the temporary credentials securely. On first login, the user changes the password and confirms the recovery email with a six-digit verification code. Choosing Do later leaves a persistent reminder until both requirements are complete.",
    keywords: ["new account", "temporary password", "role"],
    popular: true,
  },
  {
    id: "admin-self-protection",
    role: "Admin",
    category: "Users & Access",
    question: "Can Admin deactivate or delete their own account?",
    answer: "Self-destructive account actions are restricted to prevent accidental loss of administrative access. Use another authorized Admin when an administrative account must be changed or retired.",
    keywords: ["delete self", "deactivate", "lockout"],
  },
  {
    id: "admin-reset-password",
    role: "Admin",
    category: "Users & Access",
    question: "What happens when Admin resets a password?",
    answer: "Existing sign-ins are ended. Give the replacement password to the verified account holder securely, ask them to sign in again and change it in Profile, and help them verify a recovery email if needed.",
    keywords: ["temporary password", "revoke session", "login"],
    popular: true,
  },
  {
    id: "admin-ai-caps",
    role: "Admin",
    category: "System Oversight",
    question: "Why are new AI requests being stopped?",
    answer: "Check the daily and monthly AI token caps and usage in Settings. Reaching a cap stops new requests until the period resets or the limit changes. Review usage and the available budget before raising a cap.",
    keywords: ["limits", "cost", "tokens", "AI usage"],
    popular: true,
  },
  {
    id: "admin-ai-cost-calculation",
    role: "Admin",
    category: "System Oversight",
    question: "Why does the AI cost estimate differ from the bill?",
    answer: "The dashboard estimates cost from NutriScope's recorded usage and configured rates. Provider-wide usage, exchange rates, credits and billing adjustments can differ. Compare matching dates and timezone in AI Usage Explorer and the provider account; use the provider invoice for payment.",
    keywords: ["estimated cost", "input rate", "output rate", "USD", "PHP", "billing"],
  },
  {
    id: "admin-audit-privacy",
    role: "Admin",
    category: "Audit Logs",
    question: "How do I find who changed a record?",
    answer: "Open Audit Logs, narrow the dates and available actor, action or category filters, then open the matching event and history. Use the event reference when reporting a concern and keep account and patient information within authorized channels.",
    keywords: ["events", "history", "filters", "privacy"],
    popular: true,
  },
  {
    id: "admin-budget",
    role: "Admin",
    category: "Budget & Reports",
    question: "How do I correct a budget balance?",
    answer: "Open Budget, select the fiscal year and add a manual adjustment with the correct amount, date and reason. Review the ledger afterward. Existing entries cannot be edited; enter a documented correcting adjustment.",
    keywords: ["setup", "adjustment", "fiscal year", "ledger", "completion date"],
  },
  {
    id: "admin-backup-schedules",
    role: "Admin",
    category: "Backup & Recovery",
    question: "Why did enabling automatic backups not create one immediately?",
    answer: "Enabling a schedule waits for its next due run after the displayed 1:30 AM Asia/Manila target. Use Create backup now for a separate manual restore point. If a due backup does not appear, check Failed and ask the system operator to check backup services.",
    keywords: ["automatic", "daily", "weekly", "monthly", "idempotent", "duplicate", "next backup"],
  },
  {
    id: "admin-backup-retention",
    role: "Admin",
    category: "Backup & Recovery",
    question: "How long are restore points kept, and what does Delete do?",
    answer: "Daily coverage lasts 4 days, weekly 14 days and monthly 3 calendar months. A backup may serve several schedules; check its Final expiration. Manual backups have no automatic expiry. Delete moves an eligible backup to Recently deleted for 7 days. Keep backup returns it with its original expiration. Delete permanently cannot be undone. Pre-restore backups stay protected during restoration and for 7 days afterward.",
    keywords: ["expires", "recently deleted", "keep backup", "delete permanently", "manual", "pre-restore", "safety snapshot"],
  },
  {
    id: "admin-backup-statuses",
    role: "Admin",
    category: "Backup & Recovery",
    question: "How do I know whether a backup is ready to use?",
    answer: "Use a verified entry in Restore points. Queued, Creating or Verifying means work is still in progress; Failed means no usable restore point was produced. Pre-restore identifies the safety snapshot made before a restoration attempt. Check Restoration history for the result; Ready to switch is still in progress.",
    keywords: ["status", "queued", "creating", "verifying", "preparing", "validating", "ready to switch", "restoring system", "restore points", "failed", "recently deleted", "daily", "weekly", "monthly", "manual", "pre-restore", "safety snapshot", "rolled back"],
  },
  {
    id: "admin-backup-restore",
    role: "Admin",
    category: "Backup & Recovery",
    question: "What should I check before restoring the whole system?",
    answer: "Confirm the restore point's date and that replacing all newer records is intended. Restore requires your current password and the exact confirmation phrase. NutriScope first creates a pre-restore safety snapshot and checks the selected backup before switching. Users are signed out after success. If the Admin page cannot open, ask the system operator to use the manual recovery guide.",
    keywords: ["whole system", "temporary database", "pre-restore", "safety snapshot", "signed out", "rollback", "maintenance"],
  },
];

export function getVisibleHelpItems(role: WebHelpRole): HelpItem[] {
  return HELP_ITEMS.filter((item) => item.role === "Shared" || item.role === role);
}

export function filterHelpItems(role: WebHelpRole, query: string): HelpItem[] {
  const normalized = query.trim().replace(/\s+/g, " ").toLocaleLowerCase();
  const visible = getVisibleHelpItems(role);
  if (!normalized) return visible;

  return visible.filter((item) =>
    [item.question, item.answer, item.category, ...item.keywords]
      .join(" ")
      .toLocaleLowerCase()
      .includes(normalized),
  );
}

export function getPopularHelpItems(role: WebHelpRole): HelpItem[] {
  return getVisibleHelpItems(role).filter((item) => item.popular).slice(0, 6);
}

export function groupHelpItems(items: HelpItem[]): HelpGroup[] {
  const groups = new Map<string, HelpItem[]>();
  for (const item of items) {
    const group = groups.get(item.category) ?? [];
    group.push(item);
    groups.set(item.category, group);
  }
  return Array.from(groups, ([category, groupedItems]) => ({
    category,
    items: groupedItems,
  }));
}
