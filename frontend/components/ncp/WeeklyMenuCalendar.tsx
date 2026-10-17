"use client";

import type { ReactNode } from "react";
import { MenuSnackVisibility, useMenuSnackVisibility } from "./MenuSnackVisibility";

export const MENU_DAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"] as const;
export const MENU_SLOTS = [
  ["breakfast", "Breakfast"], ["am_snack", "AM Snack"], ["lunch", "Lunch"],
  ["pm_snack", "PM Snack"], ["dinner", "Dinner"],
] as const;

export function WeeklyMenuCalendar({ renderCell }: {
  renderCell: (day: string, meal: string) => ReactNode;
}) {
  const [showSnacks, setShowSnacks] = useMenuSnackVisibility("nutriscope.ncp.show-snacks");
  return <div><MenuSnackVisibility visible={showSnacks} onChange={setShowSnacks} /><div className="overflow-x-auto rounded-xl border border-warm-200 bg-white" tabIndex={0} aria-label="Scrollable weekly menu">
    <table aria-label="Weekly menu calendar" className="w-full min-w-[1120px] table-fixed border-collapse text-left text-sm">
      <thead><tr className="bg-warm-50 text-warm-700">
        <th scope="col" className="w-24 border-b border-r border-warm-200 p-3">Meal</th>
        {MENU_DAYS.map((day) => <th scope="col" key={day} className="border-b border-r border-warm-200 p-3 font-bold last:border-r-0">{day}</th>)}
      </tr></thead>
      <tbody>{MENU_SLOTS.filter(([meal]) => showSnacks || (meal !== "am_snack" && meal !== "pm_snack")).map(([meal, label]) => <tr key={meal}>
        <th scope="row" className="border-b border-r border-warm-200 bg-warm-50 p-3 align-top text-xs font-bold text-warm-600">{label}</th>
        {MENU_DAYS.map((day) => <td key={day} data-day={day} data-meal={meal} className="border-b border-r border-warm-100 p-2 align-top last:border-r-0">{renderCell(day, meal)}</td>)}
      </tr>)}</tbody>
    </table>
  </div></div>;
}
