import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, it, vi } from "vitest";

import SearchInput from "./SearchInput";

describe("SearchInput", () => {
  it("renders one accessible search field with reusable clear and loading states", () => {
    const html = renderToStaticMarkup(
      <SearchInput
        label="Search reports"
        value="inventory"
        onChange={vi.fn()}
        placeholder="Search by report name"
        loading
      />,
    );

    expect(html).toContain('type="search"');
    expect(html).toContain("Search reports");
    expect(html).toContain("Search by report name");
    expect(html).toContain('role="status"');

    const idleHtml = renderToStaticMarkup(
      <SearchInput label="Search reports" value="inventory" onChange={vi.fn()} />,
    );
    expect(idleHtml).toContain('aria-label="Clear search"');
  });
});
