import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, test, vi } from "vitest";
import { NcpBreadcrumb } from "./NcpBreadcrumb";

vi.mock("next/link", () => ({ default: ({ children, href }: { children: React.ReactNode; href: string }) => <a href={href}>{children}</a> }));

describe("NcpBreadcrumb", () => {
  test("uses Home, Nutrition Care, and the current step", () => {
    const html = renderToStaticMarkup(<NcpBreadcrumb step="Nutrition Intervention" />);
    expect(html).toContain('href="/dashboard"');
    expect(html).toContain('href="/ncp/patients"');
    expect(html).toContain("Home");
    expect(html).toContain("Nutrition Care");
    expect(html).toContain("Nutrition Intervention");
    expect(html).not.toContain("Directory");
  });
});
