import { describe, expect, test } from "vitest";

import {
  changedPersonNameFields,
  personDisplayName,
  requiredPersonNameFields,
} from "@/lib/personName";

describe("person name contract", () => {
  test("uses the API display name without rewriting legacy text", () => {
    expect(personDisplayName({
      first_name: "Legacy Name",
      last_name: null,
      display_name: "  Legacy  Name  ",
      name: "Legacy Name",
    })).toBe("  Legacy  Name  ");
  });

  test("falls back without heuristically splitting compound names", () => {
    expect(personDisplayName({
      first_name: "Maria Luisa",
      last_name: "De la Cruz",
      name: "Deprecated",
    })).toBe("Maria Luisa De la Cruz");
    expect(personDisplayName({ first_name: "Madonna", last_name: null, name: "Madonna" }))
      .toBe("Madonna");
  });

  test("normalizes valid deliberate writes and rejects incomplete pairs", () => {
    expect(requiredPersonNameFields("  Maria   Luisa ", " De la Cruz ")).toEqual({
      first_name: "Maria Luisa",
      last_name: "De la Cruz",
    });
    expect(() => requiredPersonNameFields("Maria", " ")).toThrow(/first and last name/i);
    expect(() => requiredPersonNameFields("Maria\u0000", "Cruz")).toThrow(/control/i);
  });

  test("omits untouched legacy names but requires a full pair after a deliberate change", () => {
    const legacy = { first_name: "Juan Dela Cruz", last_name: null, name: "Juan Dela Cruz" };

    expect(changedPersonNameFields(legacy, "Juan Dela Cruz", "")).toBeNull();
    expect(() => changedPersonNameFields(legacy, "Juan", "Dela Cruz")).not.toThrow();
    expect(changedPersonNameFields(legacy, "Juan", "Dela Cruz")).toEqual({
      first_name: "Juan",
      last_name: "Dela Cruz",
    });
    expect(() => changedPersonNameFields(legacy, "Juan", "")).toThrow(/first and last name/i);
  });
});
