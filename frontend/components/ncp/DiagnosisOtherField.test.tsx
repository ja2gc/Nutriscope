// @vitest-environment jsdom
import React, { act, useState } from "react";
import { createRoot } from "react-dom/client";
import { fireEvent, screen } from "@testing-library/dom";
import { expect, it } from "vitest";
import DiagnosisOtherField from "./DiagnosisOtherField";

it("reveals a labelled 75-character field only while Other is checked", () => {
  Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true });
  const container = document.createElement("div");
  document.body.append(container);
  const root = createRoot(container);
  function Example() {
    const [checked, setChecked] = useState(false);
    const [value, setValue] = useState("");
    return <DiagnosisOtherField id="cause" label="Other etiology" checked={checked} value={value} onToggle={setChecked} onChange={setValue} />;
  }
  try {
    act(() => root.render(<Example />));
    expect(screen.queryByRole("textbox")).toBeNull();
    act(() => fireEvent.click(screen.getByRole("checkbox", { name: "Other etiology" })));
    const input = screen.getByRole("textbox", { name: "Specify other etiology" });
    act(() => fireEvent.change(input, { target: { value: "x".repeat(90) } }));
    expect((input as HTMLTextAreaElement).value).toHaveLength(75);
    expect(screen.getByText("75/75")).toBeTruthy();
    act(() => fireEvent.click(screen.getByRole("checkbox", { name: "Other etiology" })));
    expect(screen.queryByRole("textbox")).toBeNull();
  } finally {
    act(() => root.unmount());
    container.remove();
  }
});
