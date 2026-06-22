import { describe, it, expect } from "vitest";
import { coerceBiochemicalValue } from "./biochemical";

describe("coerceBiochemicalValue", () => {
  it("returns null for empty string", () => {
    expect(coerceBiochemicalValue("albumin", "")).toBeNull();
  });
  it("returns a number for numeric lab fields", () => {
    expect(coerceBiochemicalValue("albumin", "3.2")).toBe(3.2);
  });
  it("keeps bp and abg as strings", () => {
    expect(coerceBiochemicalValue("bp", "120/80")).toBe("120/80");
    expect(coerceBiochemicalValue("abg", "7.35")).toBe("7.35");
  });
  it("returns null for non-numeric input on numeric fields", () => {
    expect(coerceBiochemicalValue("glucose", "abc")).toBeNull();
  });
});
