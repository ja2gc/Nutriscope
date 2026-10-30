// @vitest-environment jsdom

import React, { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

let pathname = "/ncp/patient-uuid/assessment/ncp-uuid";
const push = vi.fn();
let userId = 7;
let authenticated = true;
(globalThis as typeof globalThis & { IS_REACT_ACT_ENVIRONMENT: boolean }).IS_REACT_ACT_ENVIRONMENT = true;

vi.mock("next/navigation", () => ({
  usePathname: () => pathname,
  useRouter: () => ({ push, replace: push }),
}));
vi.mock("next/link", () => ({
  default: ({ href, children, prefetch, ...props }: React.AnchorHTMLAttributes<HTMLAnchorElement> & { href: string; prefetch?: boolean }) =>
    <a href={href} data-prefetch={prefetch === true ? "full" : undefined} {...props}>{children}</a>,
}));
vi.mock("@/contexts/AuthContext", () => ({
  useAuth: () => ({ user: authenticated ? { id: userId, role: "RND" } : null, logout: vi.fn() }),
}));
vi.mock("@/components/ui/Logo", () => ({ Logo: () => <span>Logo</span> }));
vi.mock("@/components/ncp/NcpVisitBar", () => ({ NcpVisitBar: () => null }));
vi.mock("@/components/ui/InfoHint", () => ({ InfoHint: () => null }));
vi.mock("@/services/patientService", () => ({ fetchPatientNcpRecords: vi.fn() }));

import { Sidebar } from "./Sidebar";
import NcpPatientHeader from "../../app/(rnd)/ncp/_components/NcpPatientHeader";
import { fetchPatientNcpRecords } from "@/services/patientService";

describe("selected NCP patient navigation", () => {
  let container: HTMLDivElement;
  let root: Root;

  beforeEach(() => {
    pathname = "/ncp/patient-uuid/assessment/ncp-uuid";
    userId = 7;
    authenticated = true;
    push.mockClear();
    sessionStorage.clear();
    vi.mocked(fetchPatientNcpRecords).mockReset();
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

  it("fully prefetches only real selected-cycle navigation", async () => {
    await renderSidebar();
    for (const label of ["Assessment", "Diagnosis", "Intervention", "Monitoring"]) {
      const link = [...container.querySelectorAll("a")].find((a) => a.textContent?.trim() === label);
      expect(link?.dataset.prefetch).toBe("full");
    }
    expect(container.querySelector('a[href="/dashboard"]')?.getAttribute("data-prefetch")).toBeNull();
  });

  it("does not fully prefetch placeholder patient steps", async () => {
    pathname = "/food-library";
    await renderSidebar();
    for (const link of container.querySelectorAll('a[href*="select-patient"]')) {
      expect(link.getAttribute("data-prefetch")).toBeNull();
    }
  });

  it("selects the opened profile's current cycle without requiring a previous step visit", async () => {
    pathname = "/ncp/patients/new-patient";
    vi.mocked(fetchPatientNcpRecords).mockResolvedValue({ data: [{ id: "current-cycle" }], meta: {} } as never);
    await renderSidebar();
    expect(stepHref("Assessment")).toBe("/ncp/new-patient/assessment/current-cycle");
    expect(stepHref("Diagnosis")).toBe("/ncp/new-patient/diagnosis/current-cycle");
    expect(stepHref("Monitoring")).toBe("/ncp/new-patient/monitoring/current-cycle");
  });

  it("does not dereference a missing remembered cycle while authentication is loading", async () => {
    authenticated = false;
    pathname = "/ncp/patients/new-patient";
    await renderSidebar();
    expect(fetchPatientNcpRecords).not.toHaveBeenCalled();
  });

  it("never uses a previous patient's cycle when opening a different profile with no cycle", async () => {
    await renderSidebar();
    pathname = "/ncp/patients/no-cycle-patient";
    vi.mocked(fetchPatientNcpRecords).mockResolvedValue({ data: [], meta: {} } as never);
    await renderSidebar();
    expect(stepHref("Assessment")).toBe("/ncp/patients/no-cycle-patient");
    expect(stepHref("Diagnosis")).toBe("/ncp/patients/no-cycle-patient");
  });

  it("uses the latest past cycle when the opened patient has no current cycle", async () => {
    pathname = "/ncp/patients/past-patient";
    vi.mocked(fetchPatientNcpRecords).mockResolvedValueOnce({ data: [], meta: {} } as never)
      .mockResolvedValueOnce({ data: [{ id: "past-cycle" }], meta: {} } as never);
    await renderSidebar();
    expect(stepHref("Intervention")).toBe("/ncp/past-patient/intervention/past-cycle");
  });

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

  it("aligns the Food alerts help control with its label in the patient header", async () => {
    await act(async () => root.render(<NcpPatientHeader patient={{ id: "patient-uuid", name: "Fictional Patient" } as never} foodDetails={["Peanuts"]} />));
    const alerts = container.querySelector<HTMLElement>("[data-food-alerts]")!;
    expect(alerts.className).toContain("items-center");
    expect(alerts.querySelector("dd")?.className).toContain("flex items-center");
  });
});
