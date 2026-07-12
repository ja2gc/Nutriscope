// @vitest-environment jsdom

import { act } from "react";
import { createRoot, type Root } from "react-dom/client";
import { afterEach, beforeEach, describe, expect, test, vi } from "vitest";
import userEvent from "@testing-library/user-event";
import { AuditLogServiceError } from "@/services/auditLogService";
import { AuditExportButton, exportErrorMessage } from "./AuditExportButton";

function deferred<T>() {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>((res) => { resolve = res; });
  return { promise, resolve };
}

describe("AuditExportButton", () => {
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
    vi.restoreAllMocks();
  });

  test("disables while exporting, downloads the blob with a fixed name, and revokes the URL", async () => {
    const pending = deferred<Blob>();
    const requestExport = vi.fn(() => pending.promise);
    const createObjectURL = vi.fn(() => "blob:audit-export");
    const revokeObjectURL = vi.fn();
    Object.defineProperty(URL, "createObjectURL", { configurable: true, value: createObjectURL });
    Object.defineProperty(URL, "revokeObjectURL", { configurable: true, value: revokeObjectURL });
    let downloadedName = "";
    vi.spyOn(HTMLAnchorElement.prototype, "click").mockImplementation(function (this: HTMLAnchorElement) {
      downloadedName = this.download;
    });
    act(() => root.render(<AuditExportButton filters={{ category: "security" }} requestExport={requestExport} />));
    const button = container.querySelector("button")!;

    await act(async () => userEvent.setup().click(button));
    expect(button.disabled).toBe(true);
    expect(button.textContent).toContain("Exporting");

    const blob = new Blob(["event_id\nevt_1\n"], { type: "text/csv" });
    await act(async () => pending.resolve(blob));
    expect(createObjectURL).toHaveBeenCalledWith(blob);
    expect(downloadedName).toBe("nutriscope-audit-events.csv");
    expect(revokeObjectURL).toHaveBeenCalledWith("blob:audit-export");
    expect(button.disabled).toBe(false);
  });

  test.each([
    [401, "Sign in again before exporting audit events."],
    [403, "You do not have permission to export audit events."],
    [422, "The selected filters are no longer valid. Refresh and try again."],
    [500, "Audit export is unavailable. Try again later."],
  ])("uses a safe inline message for status %s", (status, message) => {
    expect(exportErrorMessage(new AuditLogServiceError("RAW-BACKEND-BODY", status))).toBe(message);
  });

  test("renders the safe failure beside the button", async () => {
    const requestExport = vi.fn(async () => {
      throw new AuditLogServiceError("RAW-BACKEND-BODY", 403);
    });
    act(() => root.render(<AuditExportButton filters={{}} requestExport={requestExport} />));

    await act(async () => userEvent.setup().click(container.querySelector("button")!));

    expect(container.querySelector('[role="alert"]')?.textContent).toBe(
      "You do not have permission to export audit events.",
    );
    expect(container.textContent).not.toContain("RAW-BACKEND-BODY");
  });
});
