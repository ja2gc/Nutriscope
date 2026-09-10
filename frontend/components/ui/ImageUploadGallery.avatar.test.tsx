import React from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, it } from "vitest";
import { ImageUploadGallery } from "./ImageUploadGallery";

describe("ImageUploadGallery avatar layout", () => {
  it("clips the image in an inner circle without clipping the remove button", () => {
    const markup = renderToStaticMarkup(
      <ImageUploadGallery
        images={[{ id: "photo", name: "Profile photo", src: "data:image/png;base64,AA==" }]}
        onImagesChange={() => undefined}
        variant="avatar"
      />,
    );

    expect(markup).toContain('data-avatar-wrapper="true"');
    expect(markup).toContain('data-avatar-crop="true"');
    expect(markup).toMatch(/data-avatar-wrapper="true"[^>]*class="[^"]*relative[^"]*"/);
    expect(markup).not.toMatch(/data-avatar-wrapper="true"[^>]*class="[^"]*overflow-hidden[^"]*"/);
    expect(markup).toMatch(/data-avatar-crop="true"[^>]*class="[^"]*overflow-hidden[^"]*rounded-full[^"]*"/);
    expect(markup).toMatch(/aria-label="Remove Profile photo"[^>]*class="[^"]*z-20[^"]*"/);
  });
});
