import React from "react";
import { renderToStaticMarkup } from "react-dom/server";
import { describe, expect, test } from "vitest";

import { ImageCarousel, ImageUploadGallery, type UploadImage } from "./ImageUploadGallery";

const images: UploadImage[] = [
  { id: "one", name: "one.png", src: "data:image/png;base64,one" },
  { id: "two", name: "two.jpg", src: "data:image/jpeg;base64,two" },
];

describe("ImageUploadGallery", () => {
  test("fits post images inside a bounded responsive frame without cropping", () => {
    const markup = renderToStaticMarkup(
      <ImageCarousel images={[images[0]]} title="Case conference" />
    );

    expect(markup.match(/<img/g)).toHaveLength(2);
    expect(markup).toContain("aria-hidden=\"true\"");
    expect(markup).toContain("object-cover blur-2xl");
    expect(markup).toContain("object-contain");
    expect(markup).toContain("h-[clamp(180px,52vw,420px)]");
  });

  test("renders multiple upload input, individual removal and image viewer triggers", () => {
    const markup = renderToStaticMarkup(
      <ImageUploadGallery images={images} onImagesChange={() => undefined} label="Images" />
    );

    expect(markup).toContain("multiple=\"\"");
    expect(markup).toContain("data:image/png;base64,one");
    expect(markup).toContain("aria-label=\"Remove one.png\"");
    expect(markup).toContain("aria-label=\"Remove two.jpg\"");
    expect(markup).toContain("aria-label=\"View one.png\"");
    expect(markup).toContain("aria-label=\"View two.jpg\"");
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
    expect(markup).toContain("aria-label=\"Edit profile picture\"");
    expect(markup).not.toContain("Change profile picture");
    expect(markup).not.toContain("blur-2xl");
  });
});
