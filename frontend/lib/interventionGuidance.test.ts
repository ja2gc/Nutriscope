import { expect, test } from "vitest";
import { interventionGuidanceCharacters } from "./interventionGuidance";

test("counts Unicode characters consistently with the server", () => {
  expect(interventionGuidanceCharacters(["a😀", "b"])).toBe(3);
});
