// @vitest-environment jsdom
import React, { act, Suspense } from "react";
import { createRoot } from "react-dom/client";
import { screen } from "@testing-library/dom";
import { expect, it, vi } from "vitest";
import Page from "./page";
import { fetchPatientById, fetchPatientNcpRecords } from "@/services/patientService";
vi.mock("@/services/patientService");
vi.mock("next/navigation", () => ({ useRouter: () => ({push:vi.fn()}) }));
vi.mock("@/components/ReportPreview", () => ({ ReportPreview: () => null }));

it("opens the current patient without waiting for past care-cycle history", async () => {
  Object.assign(globalThis,{IS_REACT_ACT_ENVIRONMENT:true});
  vi.mocked(fetchPatientById).mockResolvedValue({id:"fictional",display_name:"Fictional Patient",first_name:"Fictional",last_name:"Patient",dob:"1990-01-01",status:"Active",sex:"Female"} as never);
  vi.mocked(fetchPatientNcpRecords).mockImplementation((_id,_page,_cycle,options) => options?.scope === "past" ? new Promise(()=>{}) : Promise.resolve({data:[],meta:{current_page:1,per_page:10,total:0,last_page:1}}));
  const container=document.createElement("div");document.body.append(container);const root=createRoot(container);
  try {
    const params=Promise.resolve({patientId:"fictional"});
    await act(async()=>root.render(<Suspense><Page params={params}/></Suspense>));
    expect(screen.getByRole("heading",{name:"Fictional Patient"})).toBeTruthy();
  } finally { act(()=>root.unmount());container.remove();vi.resetAllMocks(); }
});
