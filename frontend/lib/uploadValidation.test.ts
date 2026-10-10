import { describe, expect, it } from "vitest";
import { validateUploadFile, validateUploadFiles } from "./uploadValidation";

const options = {
  maxBytes: 5 * 1024 * 1024,
  allowedTypes: ["image/jpeg", "image/png", "image/webp"],
};

describe("upload validation", () => {
  it("accepts allowed files at the byte limit", () => {
    expect(validateUploadFile({ size: options.maxBytes, type: "image/jpeg", name: "receipt.jpg" }, options))
      .toEqual({ valid: true });
  });

  it("rejects files over the byte limit and unsupported MIME types", () => {
    expect(validateUploadFile({ size: options.maxBytes + 1, type: "image/jpeg", name: "large.jpg" }, options).valid)
      .toBe(false);
    expect(validateUploadFile({ size: 10, type: "image/gif", name: "animated.gif" }, options).valid)
      .toBe(false);
  });

  it("rejects a batch that exceeds the file-count limit", () => {
    const files = Array.from({ length: 16 }, (_, index) => ({
      size: 10,
      type: "image/jpeg",
      name: `receipt-${index}.jpg`,
    }));

    expect(validateUploadFiles(files, { ...options, maxFiles: 15 }).valid).toBe(false);
  });
});
