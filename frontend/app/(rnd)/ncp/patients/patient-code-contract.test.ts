import { describe, expect, test } from "vitest";
import fs from "node:fs";
import path from "node:path";

const patientsDirectory = __dirname;
const listPage = fs.readFileSync(path.join(patientsDirectory, "page.tsx"), "utf8");
const profilePage = fs.readFileSync(path.join(patientsDirectory, "[patientId]", "page.tsx"), "utf8");

describe("patient code presentation", () => {
  test("shows the patient code only beneath the profile name", () => {
    expect(profilePage).toContain("{patient.patient_code}");
    expect(profilePage).toContain("text-xs");
    expect(profilePage).toContain("text-warm-400");
    expect(listPage).not.toContain("{patient.patient_code}");
    expect(listPage).toContain(">Name</th>");
    expect(listPage).not.toContain(">Name / ID</th>");
  });
});
