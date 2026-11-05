export type MobileHelpRole = 'Shared' | 'FSS';

export interface MobileHelpItem {
  id: string;
  role: MobileHelpRole;
  topic: string;
  question: string;
  answer: string;
  popular?: boolean;
  keywords?: string[];
}

export const mobileHelpItems: MobileHelpItem[] = [
  { id: 'shared-forgot-password', role: 'Shared', topic: 'Account', question: 'I forgot my password. What should I do?', answer: 'Tap Forgot password and enter your sign-in email. Open the newest link sent to your verified recovery email, set a new password in the browser, then return to the app and sign in. If you never verified recovery or cannot access that inbox, contact an administrator.', popular: true, keywords: ['reset', 'login'] },
  { id: 'shared-first-login', role: 'Shared', topic: 'Account', question: 'Why does the setup reminder keep appearing?', answer: 'A new account needs a new password and a verified recovery email. Choosing Do later leaves a reminder until both are complete. Tap Open in the reminder to finish in Profile. Removing recovery brings the reminder back.', popular: true, keywords: ['temporary password', 'onboarding', 'banner'] },
  { id: 'shared-recovery-code', role: 'Shared', topic: 'Account', question: 'How do I verify or replace my recovery email?', answer: 'Open the profile menu > Profile, choose Add recovery email or Change recovery email, enter the address and tap Send verification code. Enter the six-digit code and tap Verify recovery email. Codes expire after 10 minutes; use only the newest code after resending. Your old verified address stays in use until the replacement is verified.', keywords: ['code expired', 'verification', 'resend'] },
  { id: 'shared-reset-email', role: 'Shared', topic: 'Account', question: 'Why did I not receive a working reset link?', answer: 'Check the inbox and spam folder of your verified recovery email. The confirmation on screen does not guarantee delivery. Enter your sign-in email when requesting a reset. Links expire after 15 minutes and only the newest works. If recovery was never verified or you cannot access that inbox, contact an administrator.', keywords: ['missing email', 'expired', 'password'] },
  { id: 'shared-save-failed', role: 'Shared', topic: 'Troubleshooting', question: 'What should I do if a save fails?', answer: 'Read the message, correct highlighted values, check your connection, and retry once. Confirm whether the first action saved before repeating uploads or logs.', popular: true, keywords: ['error', 'network', 'duplicate'] },
  { id: 'shared-report-problem', role: 'Shared', topic: 'Troubleshooting', question: 'What should I include when reporting a problem?', answer: 'Provide the page, action, time, and exact safe error wording. Never send passwords, codes, or unnecessary patient information.', keywords: ['support', 'privacy'] },
  { id: 'fss-no-active-menu', role: 'FSS', topic: 'Menu and meal service', question: 'Why is no weekly menu shown?', answer: 'Menu and Meal Prep open on today and its current week. If no meals are planned, the app says “Menu not planned for this week yet.” Contact RND if planning is overdue. Use the day arrows to review another date.', keywords: ['missing menu', 'RND', 'today'] },
  { id: 'fss-meal-quantities', role: 'FSS', topic: 'Menu and meal service', question: 'How do I check ingredient quantities for a planned meal?', answer: 'Open Menu, choose the service date and tap the meal. Review its scaled ingredients and preparation notes. Check the planned serving count before preparing food; report an incorrect plan to the supervising nutritionist.', keywords: ['recipe', 'food profile', 'servings'] },
  { id: 'fss-served-population', role: 'FSS', topic: 'Menu and meal service', question: 'Where do I record actual population served?', answer: 'Open Meal Prep, select a planned date, review its meals, then record or update the actual headcount. This supports purchase-order completion and actual cost per served patient-day.', popular: true, keywords: ['backfill', 'census', 'PO'] },
  { id: 'fss-backdated-log', role: 'FSS', topic: 'Accomplishments and reports', question: 'Can I enter a missed Daily Log?', answer: 'Yes. In Accomplish → Daily Log, choose any past date. Future dates are blocked. Tap Today to return to the default current-day log.', popular: true, keywords: ['date', 'today', 'missing log'] },
  { id: 'fss-correct-log', role: 'FSS', topic: 'Accomplishments and reports', question: 'How do I correct a saved Daily Log?', answer: 'Choose the same date in Accomplish > Daily Log, correct it and save. This updates the existing log. If its report is submitted, withdraw it from My Reports before editing. Rejected reports allow corrections; approved periods stay locked.', keywords: ['daily log', 'duplicate', 'locked', 'withdraw'] },
  { id: 'fss-off-duty', role: 'FSS', topic: 'Accomplishments and reports', question: 'How does Off duty work?', answer: 'Off duty labels that date in the report and keeps both numeric values at zero. Correct the log before submitting its report. Submitted periods need withdrawal before editing, and approved periods remain locked.', keywords: ['absent', 'off duty'] },
  { id: 'fss-my-reports', role: 'FSS', topic: 'Accomplishments and reports', question: 'Where are my reports and PDF files?', answer: 'Open Accomplish → My Reports to check each semi-monthly period, open any date, and submit once every date has a saved log. You can withdraw while RND is reviewing it. Rejection unlocks corrections; approval locks the logs. Approved and earlier PDFs are under Saved PDFs.', keywords: ['download', 'view', 'semi-monthly', 'PDF'] },
  { id: 'fss-purchase-order', role: 'FSS', topic: 'Purchase orders', question: 'Why is a delivered purchase order still pending?', answer: 'Check each vendor group for actual quantities and prices, receipt and proof of delivery. An OR number is optional. Ask the supervising nutritionist to review and mark the vendor received. Suggested food orders also need actual served population for every covered date in Meal Prep.', popular: true, keywords: ['receipt', 'proof', 'vendor', 'actual', 'incomplete'] },
  { id: 'fss-camera-upload', role: 'FSS', topic: 'Purchase orders', question: 'Why does a receipt photo fail?', answer: 'Allow camera or photo access, use a supported image, and check your connection. If permission was denied earlier, enable it in Android Settings.', keywords: ['permission', 'image'] },
  { id: 'fss-app-update', role: 'FSS', topic: 'App updates', question: 'How do I update NutriScope?', answer: 'The app checks periodically and shows an update message when a newer APK exists. You can also open the profile side menu → Check for updates. The website QR always opens the same latest-download page.', keywords: ['APK', 'version', 'QR'] },
];

export const MOBILE_HELP_ITEMS = mobileHelpItems;

export function filterMobileHelpItems(query: string): MobileHelpItem[] {
  const needle = query.trim().toLocaleLowerCase();
  if (!needle) return MOBILE_HELP_ITEMS;
  return MOBILE_HELP_ITEMS.filter((item) => [item.question, item.answer, item.topic, ...(item.keywords ?? [])]
    .some((value) => value.toLocaleLowerCase().includes(needle)));
}

export function groupMobileHelpItems(items: MobileHelpItem[]): [string, MobileHelpItem[]][] {
  const groups = new Map<string, MobileHelpItem[]>();
  items.forEach((item) => groups.set(item.topic, [...(groups.get(item.topic) ?? []), item]));
  return [...groups.entries()];
}
