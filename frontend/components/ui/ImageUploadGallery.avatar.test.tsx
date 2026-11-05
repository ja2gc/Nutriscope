import React from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, it } from "vitest";
import { ImageUploadGallery } from "./ImageUploadGallery";

describe("ImageUploadGallery avatar layout", () => {
  it("makes the photo itself an accessible edit trigger without a separate upload control", () => {
    const markup = renderToStaticMarkup(<ImageUploadGallery
      images={[{ id: "photo", name: "Profile photo", src: "/current-photo.png" }]}
      onImagesChange={() => undefined} variant="avatar" uploadLabel="Change profile picture"
    />);
    expect(markup).toContain('aria-label="Edit profile picture"');
    expect(markup).toContain('src="/current-photo.png"');
    expect(markup).not.toContain("Change profile picture");
  });

  it("clips the image inside the circular edit trigger", () => {
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
  });
});
