import { describe, expect, it } from "vitest";
import { isPhoneOrTablet } from "./pwa";

describe("PWA device targeting", () => {
  it("treats coarse pointer devices as phone or tablet", () => {
    expect(isPhoneOrTablet({ coarsePointer: true, viewportWidth: 1440 })).toBe(true);
  });

  it("treats viewports up to 1024 CSS pixels as phone or tablet", () => {
    expect(isPhoneOrTablet({ coarsePointer: false, viewportWidth: 1024 })).toBe(true);
  });

  it("treats a wide fine-pointer device as desktop", () => {
    expect(isPhoneOrTablet({ coarsePointer: false, viewportWidth: 1440 })).toBe(false);
  });

  it("always treats an installed standalone window as the mobile app", () => {
    expect(isPhoneOrTablet({ coarsePointer: false, viewportWidth: 1600, standalone: true })).toBe(true);
  });
});
