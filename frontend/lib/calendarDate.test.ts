import { afterEach, describe, expect, test, vi } from "vitest";
import { addCalendarDays, manilaCalendarToday, manilaRelativeDate } from "./calendarDate";
afterEach(() => vi.useRealTimers());

test("clinical review age follows Manila calendar days rather than elapsed hours", () => {
  const now = new Date("2026-11-06T16:01:00Z");
  expect(manilaRelativeDate("2026-11-06T16:00:00Z", now)).toBe("Today");
  expect(manilaRelativeDate("2026-11-06T15:59:00Z", now)).toBe("1 day ago");
  expect(manilaRelativeDate("2026-11-04T04:00:00Z", now)).toBe("3 days ago");
  expect(manilaRelativeDate(null, now)).toBe("");
  expect(manilaRelativeDate("invalid", now)).toBe("");
});

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
