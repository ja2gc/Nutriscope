// @vitest-environment jsdom

import React, { act } from "react";
import { createRoot } from "react-dom/client";
import { afterEach, describe, expect, it, vi } from "vitest";
import { ReportPreview } from "./ReportPreview";

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

const documents = new Map<string, { numPages: number; destroy: ReturnType<typeof vi.fn> }>();

vi.mock("pdfjs-dist/webpack.mjs", () => ({
  getDocument: ({ url }: { url: string }) => {
    const documentProxy = { numPages: 0, destroy: vi.fn().mockResolvedValue(undefined) };
    documents.set(url, documentProxy);
    return { promise: Promise.resolve(documentProxy) };
  },
}));

afterEach(() => {
  documents.clear();
  document.body.innerHTML = "";
});

describe("ReportPreview document lifecycle", () => {
  it("does not destroy a cached PDF while its preview is still mounted", async () => {
    const container = document.createElement("div");
    document.body.appendChild(container);
    const root = createRoot(container);

    await act(async () => {
      root.render(
        <>
          {["/report-a", "/report-b", "/report-c", "/report-d"].map((src) => (
            <ReportPreview
              key={src}
              title={src}
              src={src}
              downloadUrl={src}
              onClose={() => undefined}
            />
          ))}
        </>,
      );
      await Promise.resolve();
      await Promise.resolve();
    });

    expect(documents.get("/report-a")?.destroy).not.toHaveBeenCalled();

    await act(async () => root.unmount());
  });
});
