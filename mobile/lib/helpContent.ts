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
  { id: 'shared-forgot-password', role: 'Shared', topic: 'Account', question: 'I forgot my password. What should I do?', answer: 'Tap Forgot password on sign-in and use your verified recovery email. If you cannot access it, contact an administrator.', popular: true, keywords: ['reset', 'login'] },
  { id: 'shared-fss-platform', role: 'Shared', topic: 'Account', question: 'Why can I not sign in on the website?', answer: 'Food Service Staff use the NutriScope Android app. RND and Admin use the website. Each platform rejects accounts for the other platform.', keywords: ['web', 'mobile', 'role'] },
  { id: 'shared-save-failed', role: 'Shared', topic: 'Troubleshooting', question: 'What should I do if a save fails?', answer: 'Read the message, correct highlighted values, check your connection, and retry once. Confirm whether the first action saved before repeating uploads or logs.', popular: true, keywords: ['error', 'network', 'duplicate'] },
  { id: 'shared-report-problem', role: 'Shared', topic: 'Troubleshooting', question: 'What should I include when reporting a problem?', answer: 'Provide the page, action, time, and exact safe error wording. Never send passwords, codes, or unnecessary patient information.', keywords: ['support', 'privacy'] },
  { id: 'fss-main-tabs', role: 'FSS', topic: 'Navigation', question: 'What are the main tabs?', answer: 'Home shows priorities; Announcement contains separate Announcements and SOP views; Menu shows the read-only weekly plan; Meal Prep records actual population served; Accomplish contains Daily Log and My Reports; Purchase handles receiving.', popular: true, keywords: ['home', 'announcement', 'sop', 'menu', 'meal prep', 'accomplish', 'purchase'] },
  { id: 'fss-header-menu', role: 'FSS', topic: 'Navigation', question: 'Where are notifications, profile, Help, and Settings?', answer: 'The header bell opens notifications. The profile icon opens the side menu for Profile, Notifications, Help, Settings, update checking, and Sign out.', keywords: ['bell', 'profile', 'side menu'] },
  { id: 'fss-no-active-menu', role: 'FSS', topic: 'Menu and meal service', question: 'Why is there no active menu cycle?', answer: 'RND has not activated a menu for the current week. Contact RND; Food Service Staff cannot create or activate menu cycles.', keywords: ['missing menu', 'RND'] },
  { id: 'fss-menu-read-only', role: 'FSS', topic: 'Menu and meal service', question: 'Can I edit a menu or recipe?', answer: 'No. Menu and food profiles are read-only in the FSS app. Tap a meal to open its scaled ingredients and preparation notes on a separate page.', keywords: ['recipe', 'food profile', 'servings'] },
  { id: 'fss-served-population', role: 'FSS', topic: 'Menu and meal service', question: 'Where do I record actual population served?', answer: 'Open Meal Prep, select a planned date, review its meals, then record or update the actual headcount. This supports purchase-order completion and actual cost per served patient-day.', popular: true, keywords: ['backfill', 'census', 'PO'] },
  { id: 'fss-backdated-log', role: 'FSS', topic: 'Accomplishments and reports', question: 'Can I enter a missed Daily Log?', answer: 'Yes. In Accomplish → Daily Log, choose any past date. Future dates are blocked. Tap Today to return to the default current-day log.', popular: true, keywords: ['date', 'today', 'missing log'] },
  { id: 'fss-multiple-wards', role: 'FSS', topic: 'Accomplishments and reports', question: 'How do I record more than one ward?', answer: 'Save one working entry per ward for the selected date. The weekly report combines completed duties and sums meals across those ward entries.', keywords: ['ward', 'meals', 'sum'] },
  { id: 'fss-off-duty', role: 'FSS', topic: 'Accomplishments and reports', question: 'How does Off duty work?', answer: 'Off duty records X for that date and counts toward weekly completeness. It cannot be combined with working entries for the same date.', keywords: ['absent', 'weekly', 'X'] },
  { id: 'fss-my-reports', role: 'FSS', topic: 'Accomplishments and reports', question: 'Where are my reports and PDF files?', answer: 'Open Accomplish → My Reports. Tap a report to view its frozen weekly details, then choose Open or save PDF. You can access only your own reports.', keywords: ['download', 'share', 'weekly'] },
  { id: 'fss-purchase-order', role: 'FSS', topic: 'Purchase orders', question: 'What can I update on a purchase order?', answer: 'While open, review actual quantity and price, attach receipt and proof, optionally add an OR number, correct an eligible vendor, and mark the vendor received. Planned quantities and completed records remain locked.', popular: true, keywords: ['receipt', 'proof', 'vendor', 'actual'] },
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
