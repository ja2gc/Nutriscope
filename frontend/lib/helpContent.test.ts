import { describe, expect, test } from "vitest";
import {
  HELP_ITEMS,
  filterHelpItems,
  getPopularHelpItems,
  getVisibleHelpItems,
  groupHelpItems,
} from "./helpContent";

describe("role-specific Help content", () => {
  test("RND receives Shared and RND questions only", () => {
    const items = getVisibleHelpItems("RND");

    expect(new Set(items.map((item) => item.role))).toEqual(
      new Set(["Shared", "RND"]),
    );
    expect(items.some((item) => item.id === "shared-forgot-password")).toBe(true);
    expect(items.some((item) => item.id === "rnd-dry-weight")).toBe(true);
    expect(items.some((item) => item.role === "Admin")).toBe(false);
  });

  test("Admin receives Shared and Admin questions only", () => {
    const items = getVisibleHelpItems("Admin");

    expect(new Set(items.map((item) => item.role))).toEqual(
      new Set(["Shared", "Admin"]),
    );
    expect(items.some((item) => item.id === "shared-forgot-password")).toBe(true);
    expect(items.some((item) => item.id === "admin-ai-caps")).toBe(true);
    expect(items.some((item) => item.role === "RND")).toBe(false);
  });

  test("Admin Help explains token counting and estimated cost without exposing it to RND", () => {
    const adminQuestions = getVisibleHelpItems("Admin").map((item) => item.question);
    const rndQuestions = getVisibleHelpItems("RND").map((item) => item.question);

    expect(adminQuestions).toContain("How is AI token usage calculated?");
    expect(adminQuestions).toContain("How is estimated AI cost calculated?");
    expect(rndQuestions).not.toContain("How is AI token usage calculated?");
    expect(rndQuestions).not.toContain("How is estimated AI cost calculated?");
  });

  test("search matches questions, answers, categories, and keywords", () => {
    expect(filterHelpItems("RND", "dry weight").map((item) => item.id)).toContain(
      "rnd-dry-weight",
    );
    expect(filterHelpItems("Admin", "token caps").map((item) => item.id)).toContain(
      "admin-ai-caps",
    );
    expect(filterHelpItems("RND", "audit logs")).toEqual([]);
    expect(filterHelpItems("RND", "   ")).toEqual(getVisibleHelpItems("RND"));
  });

  test("popular and grouped results stay inside the role boundary", () => {
    expect(getPopularHelpItems("RND").every((item) => item.role !== "Admin")).toBe(true);
    const groups = groupHelpItems(filterHelpItems("Admin", "account"));
    expect(groups.length).toBeGreaterThan(0);
    expect(groups.flatMap((group) => group.items).every((item) => item.role !== "RND")).toBe(true);
    expect(HELP_ITEMS.length).toBeGreaterThan(45);
  });
});
