import { describe, expect, it } from "vitest";
import {
  getCycleStepHref,
  getNcpStepState,
  getPlaceholderStepHref,
  type NcpWorkflowRecord,
} from "./ncpWorkflow";

const baseRecord: NcpWorkflowRecord = {
  id: 7,
  patient_id: 3,
  type: "new",
};

describe("NCP workflow gates", () => {
  it("keeps placeholder step pages reachable before selecting a patient", () => {
    expect(getPlaceholderStepHref("assessment")).toBe("/ncp/select-patient/assessment/select-ncp");
    expect(getPlaceholderStepHref("monitoring")).toBe("/ncp/select-patient/monitoring/select-ncp");
  });

  it("builds concrete cycle step hrefs", () => {
    expect(getCycleStepHref(3, "diagnosis", 7)).toBe("/ncp/3/diagnosis/7");
  });

  it("allows assessment for a new cycle", () => {
    expect(getNcpStepState(baseRecord, "assessment")).toMatchObject({
      available: true,
      href: "/ncp/3/assessment/7",
    });
  });

  it("locks diagnosis until assessment has been saved", () => {
    expect(getNcpStepState(baseRecord, "diagnosis")).toMatchObject({
      available: false,
      reason: "Save the assessment before starting diagnosis.",
    });
  });

  it("allows diagnosis after assessment exists", () => {
    expect(getNcpStepState({ ...baseRecord, assessment: {} }, "diagnosis")).toMatchObject({
      available: true,
      href: "/ncp/3/diagnosis/7",
    });
  });

  it("locks intervention until at least one diagnosis exists", () => {
    expect(getNcpStepState({ ...baseRecord, assessment: {} }, "intervention")).toMatchObject({
      available: false,
      reason: "Save at least one diagnosis before starting intervention.",
    });
  });

  it("allows intervention after diagnosis exists", () => {
    expect(getNcpStepState({ ...baseRecord, assessment: {}, diagnoses: [{}] }, "intervention")).toMatchObject({
      available: true,
      href: "/ncp/3/intervention/7",
    });
  });

  it("locks monitoring until intervention exists because monitoring is follow-up work", () => {
    expect(getNcpStepState({ ...baseRecord, assessment: {}, diagnoses: [{}] }, "monitoring")).toMatchObject({
      available: false,
      reason: "Monitoring starts on follow-up or second visit after the care plan is saved.",
    });
  });

  it("allows monitoring after intervention exists", () => {
    expect(getNcpStepState({ ...baseRecord, assessment: {}, diagnoses: [{}], intervention: {} }, "monitoring")).toMatchObject({
      available: true,
      href: "/ncp/3/monitoring/7",
    });
  });
});
