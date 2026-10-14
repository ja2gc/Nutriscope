import { afterEach, describe, expect, test, vi } from "vitest";
import { addCalendarDays, manilaCalendarToday } from "./calendarDate";
afterEach(() => vi.useRealTimers());

describe("addCalendarDays", () => {
  test("keeps Monday and subsequent service dates in Manila across month boundaries", () => {
    expect(addCalendarDays("2026-09-28", 0)).toBe("2026-09-28");
    expect(addCalendarDays("2026-09-28", 1)).toBe("2026-09-29");
    expect(addCalendarDays("2026-09-28", 3)).toBe("2026-10-01");
    expect(addCalendarDays("2026-09-28", 6)).toBe("2026-10-04");
  });
});

test("uses Manila's date for default procurement dates after midnight", () => {
  vi.useFakeTimers();
  vi.setSystemTime(new Date("2026-10-13T16:30:00Z"));
  expect(manilaCalendarToday()).toBe("2026-10-14");
});
