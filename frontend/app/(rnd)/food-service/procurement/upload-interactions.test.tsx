/** @vitest-environment jsdom */
import React, { act } from "react";
import { createRoot } from "react-dom/client";
import { expect, test, vi } from "vitest";
import ProcurementPage from "./page";

vi.mock("next/navigation", () => ({ useSearchParams: () => new URLSearchParams("poId=example-po") }));
globalThis.IS_REACT_ACT_ENVIRONMENT = true;

test("vendor uploads append, removal targets one saved image, and errors stay in the affected section", async () => {
  const attachments = [
    { id: "receipt-one", type: "receipt", url: "/receipt.png" },
    { id: "proof-one", type: "proof", url: "/proof-one.png" },
    { id: "proof-two", type: "proof", url: "/proof-two.png" },
  ];
  const group = { id: "vendor-one", supplier: { name: "Example Vendor" }, status: "pending", items: [],
    total_amount: "0", use_calculated_total: true, attachments, evidence_requirements: { receipt_uploaded: true, proof_uploaded: true } };
  const po = { id: "example-po", display_name: "Example PO", lifecycle_status: "open_execution", items: [], vendor_groups: [group], total_amount: "0", served_days: [] };
  const deleted: string[] = [];
  const uploaded: string[] = [];
  vi.stubGlobal("fetch", async (url: string, init?: RequestInit) => {
    if (init?.method === "DELETE") {
      const id = url.split("/").at(-1)!;
      deleted.push(id);
      group.attachments = group.attachments.filter(item => item.id !== id);
      return new Response(null, { status: 204 });
    }
    if (url.endsWith("/attachments") && init?.method === "POST") {
      const form = init.body as FormData;
      if (form.get("type") === "receipt") return Response.json({ message: "Receipt upload failed." }, { status: 422 });
      const files = form.getAll("files[]") as File[];
      uploaded.push(...files.map(file => file.name));
      group.attachments.push(...files.map((file, index) => ({ id: `${uploaded.length}-${index}`, type: "proof", url: `/${file.name}` })));
      return Response.json({ data: [] });
    }
    return Response.json({ data: url.endsWith("/purchase-orders/example-po") ? po : [], meta: { current_page: 1, last_page: 1, per_page: 10, total: 0 } });
  });
  const container = document.createElement("div");
  document.body.append(container);
  const root = createRoot(container);
  const button = (name: string) => [...container.querySelectorAll<HTMLButtonElement>("button")]
    .find(item => (item.getAttribute("aria-label") ?? item.textContent?.trim()) === name);
  const drop = async (label: string, name: string) => {
    const event = new Event("drop", { bubbles: true, cancelable: true });
    Object.defineProperty(event, "dataTransfer", { value: { files: [new File(["image"], name, { type: "image/png" })] } });
    await act(async () => container.querySelector(`[aria-label="Drop files for ${label}"]`)!.dispatchEvent(event));
  };
  try {
    await act(async () => root.render(<ProcurementPage />));
    await act(async () => button("Open")!.click());
    await act(async () => button("Remove Proof 2")!.click());
    expect(container.querySelector('[role="dialog"]')).toBeNull();
    expect(deleted).toEqual(["proof-two"]);
    expect(button("Remove Proof 1")).toBeDefined();
    expect(button("Remove Receipt 1")).toBeDefined();
    await drop("Proof of purchase", "first.png");
    await drop("Proof of purchase", "second.png");
    expect(uploaded).toEqual(["first.png", "second.png"]);
    expect(group.attachments.filter(item => item.type === "proof")).toHaveLength(3);
    expect(button("Remove Proof 3")).toBeDefined();
    await drop("Receipt images", "failed.png");
    expect(container.querySelector('section[aria-label="Receipt images"] [role="alert"]')?.textContent).toContain("Receipt upload failed");
    expect(container.querySelector('section[aria-label="Proof of purchase"] [role="alert"]')).toBeNull();
  } finally {
    await act(async () => root.unmount());
    container.remove();
    vi.unstubAllGlobals();
  }
});
