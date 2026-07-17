import { describe, expect, test } from "vitest";

import { ncpRecordMatchesRoute, type NcpRecord } from "./patientService";

const record = {
  id: "01JZ123NCP",
  patient_id: "01JZ123PATIENT",
  rnd_user_id: 1,
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
