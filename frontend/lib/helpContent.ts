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
    answer: "On the sign-in page, choose Forgot password and enter your verified recovery email. Use the newest reset link you receive. If no verified recovery email is attached to the account, ask an Admin to reset the password.",
    keywords: ["reset", "login", "recovery email", "cannot sign in"],
    popular: true,
  },
  {
    id: "shared-generic-reset-response",
    role: "Shared",
    category: "Account & Access",
    question: "Why does password recovery always say a link was sent?",
    answer: "The same confirmation is shown whether or not an account matches. This prevents the recovery page from revealing which email addresses have NutriScope accounts.",
    keywords: ["email not received", "security", "account exists"],
  },
  {
    id: "shared-reset-link-expired",
    role: "Shared",
    category: "Account & Access",
    question: "My reset link does not work. What now?",
    answer: "Request a new reset link and use only the most recent message. Links can expire or become invalid after a newer request or password change. Ask Admin for a reset if the newest link still fails.",
    keywords: ["expired token", "invalid link", "password"],
  },
  {
    id: "shared-first-login",
    role: "Shared",
    category: "Account & Access",
    question: "What happens on first login?",
    answer: "A new account is asked to replace its temporary password and add a recovery email. You may defer setup, but NutriScope will continue showing a reminder until both security steps are finished.",
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
    id: "shared-profile",
    role: "Shared",
    category: "Account & Access",
    question: "Can I update my profile information?",
    answer: "Open Profile to update the editable name, sign-in email, contact number, and available photo fields. Your role and active status are controlled by Admin and remain read-only.",
    keywords: ["name", "email", "phone", "photo"],
  },
  {
    id: "shared-email-types",
    role: "Shared",
    category: "Account & Access",
    question: "What is the difference between sign-in and recovery email?",
    answer: "The sign-in email identifies the account during login. The verified recovery email receives password-reset messages and can be different. Changing one does not automatically change the other.",
    keywords: ["login email", "forgot password", "verification"],
  },
  {
    id: "shared-change-password",
    role: "Shared",
    category: "Account & Access",
    question: "How do I change my password while signed in?",
    answer: "Open Profile, enter the current password, then enter and confirm a new password of at least eight characters. If the current password is unknown, use password recovery or contact Admin.",
    keywords: ["security", "profile", "eight characters"],
  },
  {
    id: "shared-session-revoked",
    role: "Shared",
    category: "Account & Access",
    question: "Why was I signed out after an Admin change?",
    answer: "Role, status, and password changes revoke active sign-in tokens so old access cannot continue. Sign in again with the current account details after the change is confirmed.",
    keywords: ["token", "logged out", "role change", "password reset"],
  },
  {
    id: "shared-role-status",
    role: "Shared",
    category: "Account & Access",
    question: "Can I change my own role or account status?",
    answer: "No. Role and active status are administrative access controls. Contact Admin if your assigned responsibilities have changed.",
    keywords: ["permission", "access", "active"],
  },
  {
    id: "shared-protected-redirect",
    role: "Shared",
    category: "Using NutriScope",
    question: "Why was I redirected to sign-in?",
    answer: "The page requires a valid session. Your session may have expired, been revoked, or never finished loading. Sign in again; if the redirect repeats, verify connectivity and contact Admin.",
    keywords: ["session expired", "protected page", "login"],
  },
  {
    id: "shared-role-boundary",
    role: "Shared",
    category: "Using NutriScope",
    question: "Why can I not see an operation assigned to another role?",
    answer: "NutriScope separates work by role and platform. Missing navigation is expected when the operation is outside your assigned responsibilities. Ask Admin to verify your role if the assignment is wrong.",
    keywords: ["permission", "navigation", "missing page"],
  },
  {
    id: "shared-pagination",
    role: "Shared",
    category: "Using NutriScope",
    question: "Why are some lists split across pages?",
    answer: "Growing lists use pagination to remain readable and fast. Use Previous and Next or the available page controls; filtering can reduce the number of matching records.",
    keywords: ["next page", "previous", "records", "list"],
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
    question: "Who can revise the SOP?",
    answer: "RND and Admin can revise the current SOP. FSS has read-only access. Each revision preserves its timestamp, author, role, title, and content in history.",
    keywords: ["edit procedure", "history", "version"],
  },
  {
    id: "shared-announcement-edit",
    role: "Shared",
    category: "Announcements & SOP",
    question: "Who can edit or delete announcements?",
    answer: "RND and Admin can manage announcements within their authorized interface. FSS reads announcements from the mobile app and cannot publish, edit, or delete them.",
    keywords: ["post", "publish", "remove"],
  },
  {
    id: "shared-notification-state",
    role: "Shared",
    category: "Announcements & SOP",
    question: "What do notification states mean?",
    answer: "Unread means the notification has not been opened. Read means it was reviewed. Opening a notification may navigate to its related record when that destination still exists and you are authorized to view it.",
    keywords: ["read", "unread", "open", "destination"],
  },

  {
    id: "rnd-ncp-order",
    role: "RND",
    category: "Nutrition Care Process",
    question: "What is the correct Nutrition Care Process order?",
    answer: "Work in order: Assessment, Diagnosis, Intervention, then Monitoring. NutriScope enforces saved-step requirements so later work is not started without its required clinical context.",
    keywords: ["NCP", "ADIME", "workflow", "steps"],
    popular: true,
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
    id: "rnd-assessment-sections",
    role: "RND",
    category: "Assessment",
    question: "What are the Assessment sections?",
    answer: "Assessment covers Dietary History, Anthropometrics, Client History, Biochemical/Labs, Referral/Screening, and RND Summary. Review every relevant section before saving.",
    keywords: ["tabs", "labs", "anthropometrics", "summary"],
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
    answer: "No. The score is decision support based on recorded inputs. Confirm source data and apply clinical judgment before documenting risk or care decisions.",
    keywords: ["screening", "clinical decision", "risk"],
  },
  {
    id: "rnd-pes",
    role: "RND",
    category: "Diagnosis & Intervention",
    question: "How do I write a nutrition diagnosis?",
    answer: "Choose a supported problem, etiology, and signs/symptoms, then review the generated PES statement. Save only a statement that accurately represents the patient's assessed condition.",
    keywords: ["problem", "etiology", "signs", "PES"],
  },
  {
    id: "rnd-ai-diagnosis",
    role: "RND",
    category: "Diagnosis & Intervention",
    question: "Can AI create a diagnosis automatically?",
    answer: "AI can suggest a draft for review, but it does not become the saved diagnosis automatically. The RND must accept, edit, or dismiss it and remains responsible for the final record.",
    keywords: ["suggestion", "accept", "dismiss", "review"],
  },
  {
    id: "rnd-intervention-goal",
    role: "RND",
    category: "Diagnosis & Intervention",
    question: "What happens when I set an intervention goal?",
    answer: "The selected goal provides context for prescription and supporting intervention sections. Save the goal explicitly and review calculated values before saving the intervention.",
    keywords: ["Save Goal", "prescription", "objective"],
  },
  {
    id: "rnd-intervention-content",
    role: "RND",
    category: "Diagnosis & Intervention",
    question: "What is included in Intervention?",
    answer: "Intervention includes the goal, food and nutrient delivery prescription, patient meal planning, education or counseling, coordination, recommendations, and encounter context as applicable.",
    keywords: ["meal plan", "education", "coordination", "prescription"],
  },
  {
    id: "rnd-meal-plan",
    role: "RND",
    category: "Diagnosis & Intervention",
    question: "Can I make a patient meal plan manually or from a template?",
    answer: "Yes. Build meals manually, use available templates, or generate a draft where supported. Review foods, portions, totals, and patient suitability before saving.",
    keywords: ["template", "generate", "menu", "nutrition totals"],
  },
  {
    id: "rnd-monitoring",
    role: "RND",
    category: "Monitoring",
    question: "What can I record in Monitoring?",
    answer: "Record follow-up visits, progress toward goals, relevant measurements or observations, and plan updates. Use the cycle's existing context and keep entries tied to the correct patient.",
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
    question: "What is the difference between Food Library, Inventory, and Foods?",
    answer: "Food Library stores clinical food nutrient data and clinical recipes. Food Service Inventory is a reference catalog of ingredients and supplies. Food Service Foods stores operational recipes and single ingredients used in menus and purchasing.",
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
    id: "rnd-no-fss-inventory",
    role: "RND",
    category: "Food Library & Food Service",
    question: "Does FSS manage inventory quantities?",
    answer: "No. The current FSS app has no inventory or stock add/deduct workflow. The web Inventory page is an RND reference catalog.",
    keywords: ["stock", "quantity", "mobile"],
  },
  {
    id: "rnd-menu-cycle",
    role: "RND",
    category: "Food Library & Food Service",
    question: "How do I build a food-service menu cycle?",
    answer: "Create a dated cycle or load a template, fill meal slots, save, and activate it. Blank names are generated from the date span. The purchase estimate is entered once later when generating a suggested shopping list.",
    keywords: ["week", "meal slots", "activate", "population"],
  },
  {
    id: "rnd-menu-template",
    role: "RND",
    category: "Food Library & Food Service",
    question: "What is a menu-cycle template?",
    answer: "A template is a reusable menu pattern. Loading it copies the structure into a new dated cycle, so editing the cycle does not change the template.",
    keywords: ["reuse", "copy menu", "week"],
  },
  {
    id: "rnd-activate-menu",
    role: "RND",
    category: "Food Library & Food Service",
    question: "What does activating a menu cycle do?",
    answer: "Activation makes the cycle the current operational menu visible to FSS. Confirm the complete week and planned population first because daily execution depends on it.",
    keywords: ["FSS", "current menu", "publish"],
  },
  {
    id: "rnd-shopping-list",
    role: "RND",
    category: "Procurement & Budget",
    question: "How is a suggested food shopping list created?",
    answer: "Choose the menu date range, enter one estimated serving count for the span, and generate. Review calculated need, editable purchase values, vendors, exclusions, and the release checklist. Purchase-when-needed recipe ingredients are added manually only when required.",
    keywords: ["procurement", "menu", "generate", "purchase"],
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
    id: "rnd-convert-po",
    role: "RND",
    category: "Procurement & Budget",
    question: "What happens when a shopping list is converted?",
    answer: "When the release checklist passes, Create and release PO copies included rows into one vendor-grouped order and freezes quantities, units, and calculations. Before evidence or receiving, Change vendor for all changes a group and row-level Change vendor moves one item. Receiving then confirms actual values plus receipt and proof; OR is optional.",
    keywords: ["PO", "vendor group", "receipt", "change vendor"],
  },
  {
    id: "rnd-po-completion",
    role: "RND",
    category: "Procurement & Budget",
    question: "When does a food purchase order complete?",
    answer: "Each vendor needs reviewed actual values, receipt, proof, and explicit received status. A suggested food PO also needs served population for each covered date; manual food and supplies do not.",
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
    question: "Who can change the fiscal-year budget?",
    answer: "RND can use the editable food-service budget workflow. Admin has oversight access but the Admin Budget page is read-only.",
    keywords: ["ledger", "adjustment", "Admin"],
  },
  {
    id: "rnd-reports",
    role: "RND",
    category: "Reports",
    question: "Which reports can RND access?",
    answer: "RND can access authorized clinical and operational report types, including NCP and patient menu outputs plus food-service reports. Use live preview for current data and archive when a frozen record is required.",
    keywords: ["NCP summary", "patient menu", "food service", "archive"],
  },
  {
    id: "rnd-live-archive",
    role: "RND",
    category: "Reports",
    question: "What is the difference between live preview and archived report?",
    answer: "Live preview reflects current source data. An archived report is a frozen historical instance and does not change when source records are edited later.",
    keywords: ["snapshot", "history", "current data"],
  },

  {
    id: "admin-create-user",
    role: "Admin",
    category: "Users & Access",
    question: "How do I create a user?",
    answer: "Open Manage Users, create the account with the correct role and active status, and provide the temporary credentials securely. The user will be asked to change the password and add a recovery email on first login.",
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
    answer: "The account receives a new temporary password and active tokens are revoked. Share the temporary credential securely; the user must sign in again and complete password setup.",
    keywords: ["temporary password", "revoke session", "login"],
    popular: true,
  },
  {
    id: "admin-dashboard",
    role: "Admin",
    category: "System Oversight",
    question: "What can Admin see on the dashboard?",
    answer: "The dashboard summarizes system users, operational activity, account states, and authorized AI usage or cost information. It is oversight, not a route into patient clinical charts.",
    keywords: ["metrics", "AI usage", "accounts", "overview"],
  },
  {
    id: "admin-ai-caps",
    role: "Admin",
    category: "System Oversight",
    question: "What do AI token caps do?",
    answer: "Token caps limit permitted AI usage by the configured scope. Review usage and cost trends before changing caps; lower caps can stop new AI requests after the limit is reached.",
    keywords: ["limits", "cost", "tokens", "AI usage"],
    popular: true,
  },
  {
    id: "admin-audit-privacy",
    role: "Admin",
    category: "Audit Logs",
    question: "What can Admin do in Audit Logs?",
    answer: "Admin can filter events and inspect structured, authorized details and history. The interface avoids raw JSON and does not expose clinical old/new values or arbitrary patient data.",
    keywords: ["events", "history", "filters", "privacy"],
    popular: true,
  },
  {
    id: "admin-no-ncp",
    role: "Admin",
    category: "Privacy & Boundaries",
    question: "Can Admin open patient NCP details?",
    answer: "No. Administrative oversight does not grant access to patient Nutrition Care Process content. Patient-linked report types are also blocked from Admin report access.",
    keywords: ["clinical privacy", "patient", "reports"],
  },
  {
    id: "admin-settings",
    role: "Admin",
    category: "System Oversight",
    question: "What can Admin change in Settings?",
    answer: "Admin Settings contains the system-level controls currently exposed by the application. Read each control's explanation and confirm its operational effect before saving; unrelated clinical settings remain outside Admin access.",
    keywords: ["configuration", "system", "preferences"],
  },
  {
    id: "admin-budget",
    role: "Admin",
    category: "Budget & Reports",
    question: "What is Admin's Budget access?",
    answer: "Admin Budget is read-only oversight. RND owns the editable fiscal-year food-service budget workflow and ledger adjustments.",
    keywords: ["read only", "fiscal year", "ledger"],
  },
  {
    id: "admin-reports",
    role: "Admin",
    category: "Budget & Reports",
    question: "Which reports can Admin access?",
    answer: "Admin can access allow-listed operational and aggregate reports such as program activity, menu calendar, procurement pack, accomplishment, and demographic census. Patient Menu Plan and NCP Summary are blocked.",
    keywords: ["allow list", "aggregate", "accomplishment", "NCP summary"],
  },
  {
    id: "admin-announcements",
    role: "Admin",
    category: "Announcements & SOP",
    question: "Can Admin manage announcements and the SOP?",
    answer: "Yes. Admin can publish authorized announcements and revise the current SOP. Confirm audience and category before publishing; SOP revisions remain visible in version history.",
    keywords: ["publish", "audience", "procedure", "history"],
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
