export type MobileHelpRole = 'Shared' | 'FSS';

export type MobileHelpItem = {
  id: string;
  role: MobileHelpRole;
  topic: string;
  question: string;
  answer: string;
  popular?: boolean;
  keywords?: string[];
};

export const MOBILE_HELP_ITEMS: MobileHelpItem[] = [
  { id: 'shared-forgot-password', role: 'Shared', topic: 'Account and access', question: 'I forgot my password. What should I do?', answer: 'On the sign-in page, choose Forgot password and enter your verified recovery email. Use the newest reset link you receive. If no verified recovery email is attached, ask an Admin to reset the password.', popular: true, keywords: ['reset', 'login', 'recovery email'] },
  { id: 'shared-generic-reset-response', role: 'Shared', topic: 'Account and access', question: 'Why does password recovery always say a link was sent?', answer: 'The same confirmation is shown whether or not an account matches. This prevents the recovery page from revealing which email addresses have NutriScope accounts.', keywords: ['email not received', 'security'] },
  { id: 'shared-reset-link-expired', role: 'Shared', topic: 'Account and access', question: 'My reset link does not work. What now?', answer: 'Request a new reset link and use only the most recent message. Links can expire or become invalid after a newer request or password change. Ask Admin for a reset if the newest link still fails.', keywords: ['expired token', 'invalid link'] },
  { id: 'shared-first-login', role: 'Shared', topic: 'Account and access', question: 'What happens on first login?', answer: 'A new account is asked to replace its temporary password and add a recovery email. You may defer setup, but NutriScope continues showing a reminder until both security steps are finished.', popular: true, keywords: ['temporary password', 'onboarding'] },
  { id: 'shared-fss-platform', role: 'Shared', topic: 'Account and access', question: 'Why can FSS not sign in on the website?', answer: 'FSS work is restricted to the mobile app. RND and Admin use the web console. Sign in on the platform assigned to your role.', keywords: ['web', 'mobile', 'role'] },
  { id: 'shared-deactivated', role: 'Shared', topic: 'Account and access', question: 'Why does sign-in say my account is deactivated?', answer: 'An inactive account cannot sign in. Contact an Admin to confirm whether access should be restored. Do not create a second account as a workaround.', keywords: ['disabled', 'inactive', 'status'] },
  { id: 'shared-profile', role: 'Shared', topic: 'Account and access', question: 'Can I update my profile information?', answer: 'Open Profile to update editable name, sign-in email, contact number, and available photo fields. Your role and active status are controlled by Admin and remain read-only.', keywords: ['name', 'email', 'phone', 'photo'] },
  { id: 'shared-email-types', role: 'Shared', topic: 'Account and access', question: 'What is the difference between sign-in and recovery email?', answer: 'The sign-in email identifies the account during login. The verified recovery email receives password-reset messages and can be different. Changing one does not automatically change the other.', keywords: ['login email', 'verification'] },
  { id: 'shared-change-password', role: 'Shared', topic: 'Account and access', question: 'How do I change my password while signed in?', answer: 'Open Profile, enter the current password, then enter and confirm a new password of at least eight characters. If the current password is unknown, use recovery or contact Admin.', keywords: ['security', 'profile'] },
  { id: 'shared-session-revoked', role: 'Shared', topic: 'Account and access', question: 'Why was I signed out after an Admin change?', answer: 'Role, status, and password changes revoke active sign-in tokens so old access cannot continue. Sign in again with the current account details after the change is confirmed.', keywords: ['token', 'logged out', 'role change'] },
  { id: 'shared-role-status', role: 'Shared', topic: 'Account and access', question: 'Can I change my own role or account status?', answer: 'No. Role and active status are administrative access controls. Contact Admin if your assigned responsibilities have changed.', keywords: ['permission', 'active'] },
  { id: 'shared-protected-redirect', role: 'Shared', topic: 'Using NutriScope', question: 'Why was I redirected to sign-in?', answer: 'The page requires a valid session. Your session may have expired, been revoked, or never finished loading. Sign in again; if it repeats, verify connectivity and contact Admin.', keywords: ['session expired', 'protected page'] },
  { id: 'shared-role-boundary', role: 'Shared', topic: 'Using NutriScope', question: 'Why can I not see an operation another role can see?', answer: 'NutriScope separates work by role and platform. Missing navigation is expected when an operation is outside your responsibilities. Ask Admin to verify your role if the assignment is wrong.', keywords: ['permission', 'navigation'] },
  { id: 'shared-pagination', role: 'Shared', topic: 'Using NutriScope', question: 'Why are some lists split across pages?', answer: 'Growing lists use pagination to remain readable and fast. Use the available page controls; filtering can reduce the number of matching records.', keywords: ['next page', 'previous', 'records'] },
  { id: 'shared-save-failed', role: 'Shared', topic: 'Using NutriScope', question: 'What should I do if a save fails?', answer: 'Keep the page open, read the message, correct missing values, and retry once after checking connectivity. If it still fails, report the screen, time, action, and safe error wording without sending passwords or patient data.', popular: true, keywords: ['error', 'validation', 'network'] },
  { id: 'shared-announcement-sop', role: 'Shared', topic: 'Announcements and SOP', question: 'What is the difference between an announcement and an SOP?', answer: 'Announcements communicate updates to a selected audience. The SOP is the current department procedure and keeps version history when revised. Read the current SOP before relying on an older announcement.', popular: true, keywords: ['procedure', 'post', 'history'] },
  { id: 'shared-sop-revision', role: 'Shared', topic: 'Announcements and SOP', question: 'Who can revise the SOP?', answer: 'RND and Admin can revise the current SOP. FSS has read-only access. Each revision preserves its timestamp, author, role, title, and content in history.', keywords: ['edit procedure', 'version'] },
  { id: 'shared-announcement-edit', role: 'Shared', topic: 'Announcements and SOP', question: 'Who can edit or delete announcements?', answer: 'RND and Admin can manage announcements within their authorized interface. FSS reads announcements from the mobile app and cannot publish, edit, or delete them.', keywords: ['publish', 'remove'] },
  { id: 'shared-notification-state', role: 'Shared', topic: 'Announcements and SOP', question: 'What do notification states mean?', answer: 'Unread means the notification has not been opened. Read means it was reviewed. Opening one may navigate to a related record when the destination exists and you are authorized to view it.', keywords: ['read', 'unread', 'open'] },
  { id: 'shared-report-problem', role: 'Shared', topic: 'Getting help', question: 'What details should I provide when reporting a problem?', answer: 'State the page, record, action, time, and exact message you saw. Do not include passwords, verification codes, or unnecessary patient details in screenshots or messages.', keywords: ['error', 'support', 'privacy', 'screenshot'] },
  { id: 'fss-five-tabs', role: 'FSS', topic: 'Navigation', question: 'What are the five main tabs?', answer: 'The five tabs are Home, Menu, Meal Prep, Accomplish, and Purchase. Help is in Settings so the daily workflow keeps exactly five primary tabs.', popular: true, keywords: ['home', 'menu', 'meal prep', 'accomplish', 'purchase'] },
  { id: 'fss-start-day', role: 'FSS', topic: 'Daily workflow', question: 'What should I do first each day?', answer: 'Check Home for meals to log, pending purchase orders, the active menu cycle, today\'s service, and announcements. Then handle Purchase receipts, Meal Prep, and Accomplish as work occurs.', popular: true, keywords: ['home', 'today', 'start', 'pending'] },
  { id: 'fss-no-active-menu', role: 'FSS', topic: 'Menu and meal service', question: 'Why does Home say there is no active menu cycle?', answer: 'RND has not activated a current cycle. Contact the supervising RND; FSS cannot create or activate a menu cycle.', keywords: ['missing menu', 'activate', 'RND'] },
  { id: 'fss-menu-read-only', role: 'FSS', topic: 'Menu and meal service', question: 'Can I edit the menu?', answer: 'No. Menu recipes, items, and preparation details are read-only for FSS. You may enter or backfill actual served population for service dates where the app provides that action.', keywords: ['read only', 'recipe', 'served population'] },
  { id: 'fss-meals-served', role: 'FSS', topic: 'Menu and meal service', question: 'How do I mark today\'s meals served?', answer: 'Open Meal Prep, enter the actual total patient population when requested, review today\'s service rows, then use the served or completion action. Confirm a shortfall warning only when service should proceed with the recorded exception.', popular: true, keywords: ['meal prep', 'complete', 'shortfall', 'population'] },
  { id: 'fss-daily-accomplishment', role: 'FSS', topic: 'Accomplishments and reports', question: 'Where should I enter my daily accomplishment?', answer: 'Use Accomplish. Enter the ward and meals distributed, select completed duties, or mark Off duty or absent. Save one accurate entry for the day; add other ward entries only when needed.', popular: true, keywords: ['ward', 'duties', 'distributed', 'daily'] },
  { id: 'fss-off-duty', role: 'FSS', topic: 'Accomplishments and reports', question: 'Why does Off duty save an X?', answer: 'It is the explicit daily record for a non-working day and counts toward Monday-to-Sunday report completeness.', keywords: ['absent', 'weekly', 'X'] },
  { id: 'fss-purchase-order', role: 'FSS', topic: 'Purchase orders', question: 'What can I change on a purchase order?', answer: 'While the order is open, FSS can save the OR number and upload or delete receipt and proof images. Vendor items, quantities, prices, suppliers, and lifecycle state are read-only.', popular: true, keywords: ['PO', 'OR number', 'receipt', 'proof'] },
  { id: 'fss-receipt-status', role: 'FSS', topic: 'Purchase orders', question: 'Why did uploading a receipt change the vendor status?', answer: 'Receipt upload is the server-side receiving event. There is no separate FSS Mark received button.', keywords: ['received', 'vendor group', 'upload'] },
  { id: 'fss-completed-po', role: 'FSS', topic: 'Purchase orders', question: 'Why can I no longer edit a completed purchase order?', answer: 'Completed and archived purchase orders are locked to protect filed operational history.', keywords: ['locked', 'archive', 'edit'] },
  { id: 'fss-camera-upload', role: 'FSS', topic: 'Purchase orders', question: 'Why does camera or photo upload fail?', answer: 'Allow camera or photo-library permission, confirm network access, and retry with a supported image. If permission was denied earlier, enable it in the device settings.', keywords: ['permission', 'receipt', 'image', 'network'] },
  { id: 'fss-my-reports', role: 'FSS', topic: 'Accomplishments and reports', question: 'Where are my reports?', answer: 'Open Accomplish, then My reports. FSS sees only their own archived accomplishment reports.', keywords: ['archive', 'weekly', 'own reports'] },
  { id: 'fss-offline-or-timeout', role: 'FSS', topic: 'Troubleshooting', question: 'What should I do after a timeout or connection error?', answer: 'Check the connection and refresh before submitting again. Confirm whether the first action was saved so you do not create a duplicate receipt, accomplishment, or report.', keywords: ['offline', 'network', 'duplicate', 'retry'] },
];

export function filterMobileHelpItems(query: string): MobileHelpItem[] {
  const normalized = query.trim().replace(/\s+/g, ' ').toLocaleLowerCase();
  if (!normalized) return MOBILE_HELP_ITEMS;
  return MOBILE_HELP_ITEMS.filter((item) =>
    [item.question, item.answer, item.topic, ...(item.keywords ?? [])]
      .join(' ')
      .toLocaleLowerCase()
      .includes(normalized),
  );
}

export function groupMobileHelpItems(items: MobileHelpItem[]) {
  return Array.from(items.reduce((groups, item) => {
    const current = groups.get(item.topic) ?? [];
    current.push(item);
    groups.set(item.topic, current);
    return groups;
  }, new Map<string, MobileHelpItem[]>()));
}
