import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, it } from "vitest";

const source = (path: string) => readFileSync(join(process.cwd(), path), "utf8");

describe("NCP visit workflow", () => {
  it("places shared visit controls in the patient header and a persistent resume banner in the RND layout", () => {
    expect(source("app/(rnd)/ncp/_components/NcpPatientHeader.tsx")).toContain("NcpVisitBar");
    expect(source("app/(rnd)/layout.tsx")).toContain("ActiveVisitBanner");
  });

  it("removes intervention-only encounter context", () => {
    const intervention = source("app/(rnd)/ncp/[patientId]/intervention/[ncpId]/page.tsx");
    expect(intervention).not.toContain("EncounterContextTab");
    expect(intervention).not.toContain('key: "encounter"');
  });

  it("supports explicit visit completion, early ending, mistaken-start discard, and scheduling", () => {
    const bar = source("components/ncp/NcpVisitBar.tsx");
    expect(bar).toContain("Finish Visit");
    expect(bar).toContain("End Early");
    expect(bar).toContain("Discard Mistaken Start");
    expect(bar).toContain("Schedule Next");
    expect(bar).toContain("<Popover");
    expect(bar).toContain("MoreHorizontal");
    expect(bar).toContain('scope: "upcoming"');
    expect(bar).toContain('...(source === "walk_in" ? { ncp_record_id: ncpId } : {})');
  });

  it("keeps attendance actions in patient appointments and dashboard queue", () => {
    const appointments = source("components/ncp/PatientAppointments.tsx");
    const dashboard = source("app/(rnd)/dashboard/page.tsx");
    for (const action of ["Reschedule", "No-show", "Cancel"]) {
      expect(appointments).toContain(action);
      expect(dashboard).toContain(action);
    }
    expect(appointments).toContain("Upcoming Appointments");
    expect(appointments).toContain("Past Appointments");
    expect(appointments).toContain("Administered by:");
    expect(dashboard).toContain("Open NCP");
  });
});
