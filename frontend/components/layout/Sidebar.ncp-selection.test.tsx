// @vitest-environment jsdom

import React, { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

let pathname = "/ncp/patient-uuid/assessment/ncp-uuid";
const push = vi.fn();
let userId = 7;
(globalThis as typeof globalThis & { IS_REACT_ACT_ENVIRONMENT: boolean }).IS_REACT_ACT_ENVIRONMENT = true;

vi.mock("next/navigation", () => ({
  usePathname: () => pathname,
  useRouter: () => ({ push, replace: push }),
}));
vi.mock("next/link", () => ({
  default: ({ href, children, ...props }: React.AnchorHTMLAttributes<HTMLAnchorElement> & { href: string }) =>
    <a href={href} {...props}>{children}</a>,
}));
vi.mock("@/contexts/AuthContext", () => ({
  useAuth: () => ({ user: { id: userId, role: "RND" }, logout: vi.fn() }),
}));
vi.mock("@/components/ui/Logo", () => ({ Logo: () => <span>Logo</span> }));
vi.mock("@/components/ncp/NcpVisitBar", () => ({ NcpVisitBar: () => null }));
vi.mock("@/components/ui/InfoHint", () => ({ InfoHint: () => null }));

import { Sidebar } from "./Sidebar";
import NcpPatientHeader from "../../app/(rnd)/ncp/_components/NcpPatientHeader";

describe("selected NCP patient navigation", () => {
  let container: HTMLDivElement;
  let root: Root;

  beforeEach(() => {
    pathname = "/ncp/patient-uuid/assessment/ncp-uuid";
    userId = 7;
    push.mockClear();
    sessionStorage.clear();
    container = document.createElement("div");
    document.body.appendChild(container);
    root = createRoot(container);
  });

  afterEach(async () => {
    await act(async () => root.unmount());
    container.remove();
    sessionStorage.clear();
  });

  async function renderSidebar() {
    await act(async () => root.render(<Sidebar />));
  }

  function stepHref(label: string) {
    return [...container.querySelectorAll("a")].find((a) => a.textContent?.trim() === label)?.getAttribute("href");
  }

  it("keeps step links after visiting another page and on refresh", async () => {
    await renderSidebar();
    pathname = "/food-library";
    await renderSidebar();
    expect(stepHref("Diagnosis")).toBe("/ncp/patient-uuid/diagnosis/ncp-uuid");
    await act(async () => root.unmount());
    root = createRoot(container);
    await renderSidebar();
    expect(stepHref("Intervention")).toBe("/ncp/patient-uuid/intervention/ncp-uuid");
  });

  it("unselects from shared patient header and restores placeholder links", async () => {
    await renderSidebar();
    await act(async () => root.render(<NcpPatientHeader patient={{ id: "patient-uuid", name: "Fictional Patient" } as never} />));
    const button = [...container.querySelectorAll("button")].find((b) => b.textContent?.trim() === "Unselect Patient");
    expect(button).toBeDefined();
    await act(async () => button?.click());
    expect(push).toHaveBeenCalledWith("/ncp/patients");
    pathname = "/ncp/patients";
    await renderSidebar();
    expect(stepHref("Assessment")).toBe("/ncp/select-patient/assessment/select-ncp");
  });
});
