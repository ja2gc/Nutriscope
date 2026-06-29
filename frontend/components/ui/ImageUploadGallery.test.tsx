import React from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, test } from "vitest";

import { ImageUploadGallery, type UploadImage } from "./ImageUploadGallery";

const images: UploadImage[] = [
  { id: "one", name: "one.png", src: "data:image/png;base64,one" },
  { id: "two", name: "two.jpg", src: "data:image/jpeg;base64,two" },
];

describe("ImageUploadGallery", () => {
  test("renders multiple upload input, removable preview, and pagination controls", () => {
    const markup = renderToStaticMarkup(
      <ImageUploadGallery images={images} onImagesChange={() => undefined} label="Images" />
    );

    expect(markup).toContain("multiple=\"\"");
    expect(markup).toContain("data:image/png;base64,one");
    expect(markup).toContain("aria-label=\"Remove one.png\"");
    expect(markup).toContain("aria-label=\"Previous image\"");
    expect(markup).toContain("aria-label=\"Next image\"");
    expect(markup).toContain("1 / 2");
  });

  test("does not render arrows for a single image", () => {
    const markup = renderToStaticMarkup(
      <ImageUploadGallery images={[images[0]]} onImagesChange={() => undefined} />
    );

    expect(markup).not.toContain("aria-label=\"Previous image\"");
    expect(markup).not.toContain("aria-label=\"Next image\"");
  });

  test("renders consistent feedback for upload and delete states", () => {
    const markup = renderToStaticMarkup(
      <ImageUploadGallery
        images={images}
        onImagesChange={() => undefined}
        label="Receipt images"
        uploading
        deletingImageId="one"
        error="Upload failed."
      />
    );

    expect(markup).toContain("Uploading...");
    expect(markup).toContain("Upload failed.");
    expect(markup).toContain("disabled=\"\"");
    expect(markup).toContain("Removing...");
  });

  test("renders avatar mode as a circular single-image picker", () => {
    const markup = renderToStaticMarkup(
      <ImageUploadGallery
        images={[images[0]]}
        onImagesChange={() => undefined}
        label="Profile Photo"
        variant="avatar"
        uploadLabel="Change profile picture"
        removeLabel="Delete profile picture"
      />
    );

    expect(markup).not.toContain("multiple=\"\"");
    expect(markup).toContain("rounded-full");
    expect(markup).toContain("Change profile picture");
    expect(markup).toContain("aria-label=\"Delete profile picture\"");
  });
});
