import { describe, expect, test } from "vitest";
import { paginateAiDrafts } from "./aiDraftPagination";

describe("paginateAiDrafts", () => {
  test("returns stable two-item pages and pagination metadata", () => {
    const drafts = [{ id: "a" }, { id: "b" }, { id: "c" }];

    expect(paginateAiDrafts(drafts, 1)).toEqual({
      items: [{ id: "a" }, { id: "b" }],
      meta: { current_page: 1, per_page: 2, total: 3, last_page: 2 },
    });
    expect(paginateAiDrafts(drafts, 2).items).toEqual([{ id: "c" }]);
  });
});
