import React from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, test, vi } from "vitest";

vi.mock("react-easy-crop", () => ({
  default: ({ cropShape, aspect }: { cropShape: string; aspect: number }) => (
    <div data-crop-shape={cropShape} data-aspect={aspect} />
  ),
}));

import { ProfilePhotoCropDialog } from "./ProfilePhotoCropDialog";

describe("ProfilePhotoCropDialog", () => {
  test("provides circular drag and zoom controls before applying a profile photo", () => {
    const markup = renderToStaticMarkup(
      <ProfilePhotoCropDialog
        image="data:image/jpeg;base64,photo"
        onCancel={() => undefined}
        onApply={() => undefined}
      />
    );

    expect(markup).toContain("role=\"dialog\"");
    expect(markup).toContain("data-crop-shape=\"round\"");
    expect(markup).toContain("data-aspect=\"1\"");
    expect(markup).toContain("aria-label=\"Profile photo zoom\"");
    expect(markup).toContain("Drag the photo to reposition it");
    expect(markup).toContain("Apply crop");
    expect(markup).toContain("Cancel");
  });
});
