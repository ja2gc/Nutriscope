// @vitest-environment jsdom
import React, { act, Suspense } from "react";
import { createRoot } from "react-dom/client";
import { fireEvent, screen } from "@testing-library/dom";
import { expect, it, vi } from "vitest";
import Page from "./page";
import * as monitoring from "@/services/monitoringService";
import { fetchPatientById } from "@/services/patientService";
import { fetchAssessment } from "@/services/assessmentService";
import { fetchDiagnoses } from "@/services/diagnosisService";
import { fetchIntervention } from "@/services/interventionService";
vi.mock("@/services/monitoringService");
vi.mock("@/services/patientService");
vi.mock("@/services/assessmentService");
vi.mock("@/services/diagnosisService");
vi.mock("@/services/interventionService");
vi.mock("../../../_components/NcpPatientHeader", () => ({ default: () => <div>Fictional patient</div> }));
vi.mock("./_components/EncounterLog", () => ({ default: () => <div>Visit history</div> }));

it("changes history pages without downloading the six unchanged clinical contexts", async () => {
  Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });
  vi.mocked(fetchPatientById).mockResolvedValue({ id: 1 } as never);
  vi.mocked(fetchAssessment).mockResolvedValue({} as never);
  vi.mocked(fetchDiagnoses).mockResolvedValue([{}] as never);
  vi.mocked(fetchIntervention).mockResolvedValue({ id: 1 } as never);
  vi.mocked(monitoring.fetchMonitoringPlan).mockResolvedValue({ visits: [], indicators: [], pes_statements: [] } as never);
  vi.mocked(monitoring.fetchMonitoringContext).mockResolvedValue({} as never);
  vi.mocked(monitoring.fetchMonitorings).mockResolvedValue({ data: [], meta: { current_page: 1, per_page: 5, total: 6, last_page: 2 } });
  const container = document.createElement("div"); document.body.append(container);
  const root = createRoot(container);
  try {
    const params = Promise.resolve({ patientId: "fictional", ncpId: "cycle" });
    await act(async () => root.render(<Suspense><Page params={params} /></Suspense>));
    await act(async () => fireEvent.click(screen.getByRole("button", { name: "Next page" })));
    expect(monitoring.fetchMonitorings).toHaveBeenLastCalledWith("cycle", 2);
    for (const fetchContext of [fetchPatientById, fetchAssessment, fetchDiagnoses, fetchIntervention, monitoring.fetchMonitoringPlan, monitoring.fetchMonitoringContext]) {
      expect(fetchContext).toHaveBeenCalledTimes(1);
    }
  } finally { act(() => root.unmount()); container.remove(); vi.resetAllMocks(); }
});
