// @vitest-environment jsdom

import { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { afterEach, beforeEach, describe, expect, test } from "vitest";
import { ClinicalAttribution } from "@/components/ncp/ClinicalAttribution";

const projectRoot = process.cwd();

function read(path: string) {
  return readFileSync(join(projectRoot, path), "utf8");
}

describe("shared RND attribution UI", () => {
  let container: HTMLDivElement;
  let root: Root;

  beforeEach(() => {
    (globalThis as typeof globalThis & { IS_REACT_ACT_ENVIRONMENT: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
    container = document.createElement("div");
    document.body.append(container);
    root = createRoot(container);
  });

  afterEach(() => {
    act(() => root.unmount());
    container.remove();
  });

  test("renders populated creator and latest clinical actor attribution", () => {
    act(() => root.render(
      <ClinicalAttribution
        creator={{ id: "creator-uuid", kind: "user", name: "Cycle Creator", role: "RND" }}
        lastAction={{
          actor: { id: "actor-uuid", kind: "user", name: "Latest Clinician", role: "RND" },
          occurred_at: "2026-07-14T08:30:00Z",
        }}
        formatDate={() => "Jul 14, 2026"}
      />,
    ));

    expect(container.textContent).toContain("Created by Cycle Creator");
    expect(container.textContent).toContain("Last clinical action by Latest Clinician · Jul 14, 2026");
  });

  test("renders explicit empty attribution states", () => {
    act(() => root.render(
      <ClinicalAttribution creator={null} lastAction={null} formatDate={(value) => value} />,
    ));

    expect(container.textContent).toContain("Created by Not recorded");
    expect(container.textContent).toContain("Last clinical action by No action recorded");
  });

  test("patient rows and NCP cards bind the correct attribution fields", () => {
    const patientList = read("app/(rnd)/ncp/patients/page.tsx");
    const patientProfile = read("app/(rnd)/ncp/patients/[patientId]/page.tsx");
    const service = read("services/patientService.ts");

    expect(patientList).toContain("patient.latest_ncp_created_by");
    expect(patientList).toContain("patient.last_clinical_action");
    expect(patientProfile).toContain("record.created_by");
    expect(patientProfile).toContain("record.last_clinical_action");
    expect(service).toContain("occurred_at");
  });
});
