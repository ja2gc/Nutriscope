import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

const root = process.cwd();

function read(path: string): string {
  return readFileSync(join(root, path), "utf8");
}

const auditedSource = [
  "app/(rnd)/dashboard/page.tsx",
  "app/admin/dashboard/page.tsx",
  "app/(rnd)/calendar/page.tsx",
  "app/(rnd)/food-service/recipes/page.tsx",
  "app/(rnd)/food-service/recipes/new/page.tsx",
  "app/(rnd)/food-service/recipes/[id]/page.tsx",
  "app/(rnd)/food-service/inventory/page.tsx",
  "app/(rnd)/food-service/menu-cycle/page.tsx",
  "app/(rnd)/food-library/page.tsx",
  "app/(rnd)/food-library/recipes/new/page.tsx",
  "app/(rnd)/food-library/recipes/[id]/page.tsx",
  "app/(rnd)/food-library/foods/new/page.tsx",
  "app/(rnd)/food-database/recipes/[id]/page.tsx",
  "app/(rnd)/notifications/page.tsx",
  "app/admin/notifications/page.tsx",
  "app/admin/settings/page.tsx",
  "app/admin/users/page.tsx",
  "app/admin/backups/page.tsx",
  "app/admin/audit-logs/page.tsx",
  "app/(rnd)/ncp/patients/page.tsx",
  "app/(rnd)/ncp/patients/[patientId]/page.tsx",
  "app/(rnd)/ncp/[patientId]/diagnosis/[ncpId]/page.tsx",
  "app/(rnd)/ncp/[patientId]/monitoring/[ncpId]/page.tsx",
  "app/(rnd)/ncp/[patientId]/intervention/[ncpId]/page.tsx",
  "app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/GoalPlanningTab.tsx",
  "app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/PrescriptionCalculationPanel.tsx",
  "app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/CounselingTab.tsx",
  "app/(rnd)/ncp/[patientId]/intervention/[ncpId]/_components/EducationTab.tsx",
  "app/login/page.tsx",
  "app/mobile-app/page.tsx",
  "components/help/HelpPage.tsx",
  "components/reports/ReportsBrowser.tsx",
  "components/announcements/AnnouncementsBoard.tsx",
  "components/foodservice/SuppliersPanel.tsx",
  "components/mobile-app/FssAppAccess.tsx",
].map(read).join("\n");

const redundantCopy = [
  "Admin Console",
  "RND Scheduling Calendar",
  "Clinical Calendar Scaffold",
  "Follow-ups, patient oversight, and a social-feed style announcement board",
  "Post content stays hidden until you open the composer.",
  "Facebook-style post view with background blur and author controls.",
  "Social-feed layout on the right.",
  "System configuration, active directories, AI consumption metrics, and logs.",
  "Latest 5 system events logged on the server.",
  "System operations will populate here live.",
  "RBAC &amp; credentials setup",
  "Filter &amp; monitor operational actions",
  "Broadcast system updates",
  "Track upcoming patient follow-ups, recheck cycles, and nutritional rounds.",
  "The upcoming calendar dashboard will display",
  "Manage recipes and single-ingredient food items used by the menu cycle.",
  "Ingredients sourced from the catalog. Cost calculates live.",
  "Use one catalog ingredient with a one-serving baseline.",
  "Clinical food &amp; recipe reference",
  "Build a clinical recipe from foods in your library.",
  "Update ingredients — macro totals recalculate on save.",
  "Update ingredients and recalculate macro totals.",
  "Catalogs of foods and Supplies",
  "Plan a fixed Monday-Sunday menu from food-service recipes and single items.",
  "Announcements and upcoming follow-up reminders.",
  "Announcements and system alerts addressed to you.",
  "Open a saved report with current source data",
  "Find answers for ${role} work",
  "Search your role&apos;s guidance",
  "Quick answers users commonly need.",
  "Manage hospital branding and your display preferences.",
  "Manage accounts, roles, active status, and password resets.",
  "Fill in all required fields.",
  "Manage restore points, automatic schedules, and staged whole-system recovery.",
  "Review security, clinical, and operational activity through privacy-safe event summaries.",
  "Create the patient record, then open the assessment page immediately",
  "This profile is the entry portal for the Nutrition Care Process.",
  "Each cycle is an independent ADIME workflow for this patient.",
  "Select all that apply for the",
  "Track clinical indices trends, gauge patient goal achievement",
  "Formula, substituted values, and current prescription.",
  "Links behavioral counseling goals to measurable nutrient targets.",
  "Goal-specific food guidance. RND to individualise based on patient tolerance.",
  "Specific, measurable nutrition goals agreed with the patient.",
  "Financial, cultural, lifestyle, or knowledge barriers to adherence.",
  "Motivational approaches and action steps to improve adherence.",
  "Record educational topics, handouts given, and key instructions discussed with the patient.",
  "Clinical & Operational Care Console",
  "Run the full Nutrition Care Process and hospital food service operations",
  "Enter your credentials below to access your workspace.",
  "RND and Admin web sign in",
  "Secure Connection - Activity Logs Active",
  "Download the Food Service Staff app for menu viewing",
  "Food Service Staff use the Android app. This is not a desktop app.",
  "Food Service Staff Android app",
  "Download the latest signed NutriScope APK.",
  "Used for allergen filtering in meal planning.",
  "Pinned posts float to the top. Click any post to view details or edit.",
  "Vendors used across procurement.",
  "Clinical records tracked",
  "Post and view department announcements.",
  "Broadcast notices to FSS, Admin, or all departments. Admin announcements support pinning.",
  "answers available for your role",
  "available to your role",
];

describe("UI helper copy contract", () => {
  test("removes approved redundant copy", () => {
    const remaining = redundantCopy.filter((copy) => auditedSource.includes(copy));
    expect(remaining).toEqual([]);
  });

  test("keeps operational, security, and data-integrity guidance", () => {
    const protectedSource = [
      "app/(rnd)/food-service/procurement/page.tsx",
      "app/(rnd)/ncp/[patientId]/assessment/[ncpId]/page.tsx",
      "components/backups/RecoveryRequestDialog.tsx",
      "components/profile/ProfilePageShell.tsx",
      "components/foodservice/MenuSlotRecipePage.tsx",
    ].map(read).join("\n");

    expect(protectedSource).toContain("Receipt and proof are required. OR number is not.");
    expect(protectedSource).toContain("Generated text is a draft. Review and edit it before saving the assessment.");
    expect(protectedSource).toContain("Do not enter provider credentials or patient information.");
    expect(protectedSource).toContain("Changing an existing recovery email requires a code");
    expect(protectedSource).toContain("Changes apply only to this menu slot. The original recipe stays unchanged.");
  });

  test("does not render a standalone Food Service Staff eyebrow on the Android page", () => {
    const mobileAppPage = read("app/mobile-app/page.tsx");
    expect(/<p[^>]*>\s*Food Service Staff\s*<\/p>/.test(mobileAppPage)).toBe(false);
  });
});
