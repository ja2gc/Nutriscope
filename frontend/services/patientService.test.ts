import { afterEach, describe, expect, test, vi } from "vitest";

import { fetchPatients, ncpRecordMatchesRoute, type NcpRecord } from "./patientService";

afterEach(() => vi.unstubAllGlobals());

test("patient searches forward cancellation to the network request", async () => {
  const request = vi.fn().mockResolvedValue(new Response(JSON.stringify({ data: [] })));
  vi.stubGlobal("fetch", request);
  const controller = new AbortController();
  await fetchPatients("fictional", "All", 1, 10, false, controller.signal);
  expect(request).toHaveBeenCalledWith("/api/patients?search=fictional&page=1&per_page=10", expect.objectContaining({ signal: controller.signal }));
});

const record = {
  id: "01JZ123NCP",
  patient_id: "01JZ123PATIENT",
  status: "active",
  created_at: "2026-07-16T00:00:00Z",
  updated_at: "2026-07-16T00:00:00Z",
} satisfies NcpRecord;

describe("ncpRecordMatchesRoute", () => {
  test("matches the public UUID returned by the patient NCP endpoint", () => {
    expect(ncpRecordMatchesRoute([record], "01JZ123NCP")).toBe(true);
  });

  test("rejects a care cycle from another patient route", () => {
    expect(ncpRecordMatchesRoute([record], "01JZ999OTHER")).toBe(false);
  });
});
