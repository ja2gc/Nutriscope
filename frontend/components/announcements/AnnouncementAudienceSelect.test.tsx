// @vitest-environment jsdom
import React, { act, useState } from "react";
import { createRoot } from "react-dom/client";
import { fireEvent, within } from "@testing-library/dom";
import { expect, test } from "vitest";
import { AnnouncementAudienceSelect } from "./AnnouncementAudienceSelect";
import type { AnnouncementRole } from "@/services/announcementService";

test("selects multiple roles and keeps at least one recipient", async () => {
  Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });
  function Editor() {
    const [roles, setRoles] = useState<AnnouncementRole[]>(["RND"]);
    return <AnnouncementAudienceSelect value={roles} onChange={setRoles} />;
  }
  const container = document.createElement("div");
  const root = createRoot(container);
  try {
    await act(async () => root.render(<Editor />));
    const ui = within(container);
    const rnd = ui.getByRole("checkbox", { name: "RND" }) as HTMLInputElement;
    expect(rnd.disabled).toBe(true);
    await act(async () => fireEvent.click(ui.getByRole("checkbox", { name: "FSS" })));
    expect(rnd.disabled).toBe(false);
    expect((ui.getByRole("checkbox", { name: "Admin" }) as HTMLInputElement).checked).toBe(false);
    await act(async () => fireEvent.click(rnd));
    expect((ui.getByRole("checkbox", { name: "FSS" }) as HTMLInputElement).disabled).toBe(true);
  } finally {
    act(() => root.unmount());
  }
});
