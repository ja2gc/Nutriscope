/** @vitest-environment jsdom */
import React, { act, useState } from "react";
import { createRoot, type Root } from "react-dom/client";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, expect, test } from "vitest";
import { ImageCarousel, ImageUploadGallery, type UploadImage } from "./ImageUploadGallery";
import { ImageFilePicker } from "./ImageFilePicker";
import { FileUploadField } from "./FileUploadField";

globalThis.IS_REACT_ACT_ENVIRONMENT = true;
let container: HTMLDivElement;
let root: Root;
beforeEach(() => {
  container = document.createElement("div");
  document.body.append(container);
  root = createRoot(container);
});
afterEach(async () => {
  await act(async () => root.unmount());
  container.remove();
});
const image = (name: string) => new File(["image bytes"], name, { type: "image/png" });
const saved: UploadImage[] = [
  { id: "one", name: "one.png", src: "/one.png" },
  { id: "two", name: "two.png", src: "/two.png" },
  { id: "three", name: "three.png", src: "/three.png" },
];
const button = (name: string) => [...container.querySelectorAll<HTMLButtonElement>("button")]
  .find(item => (item.getAttribute("aria-label") ?? item.textContent?.trim()) === name);
async function drop(files: File[]) {
  const zone = container.querySelector('[aria-label^="Drop files for"]');
  expect(zone, "a shared accessible drop area is available").not.toBeNull();
  const event = new Event("drop", { bubbles: true, cancelable: true });
  Object.defineProperty(event, "dataTransfer", { value: { files, types: ["Files"] } });
  await act(async () => { zone!.dispatchEvent(event); });
  // Real FileReader is asynchronous in jsdom.
  await act(async () => { await new Promise(resolve => setTimeout(resolve, 20)); });
}

test("removing any individual image preserves every sibling", async () => {
  let remaining: UploadImage[] = [];
  function Gallery() {
    const [images, setImages] = useState(saved);
    return <ImageUploadGallery images={images} onImagesChange={next => { remaining = next; setImages(next); }} />;
  }
  await act(async () => root.render(<Gallery />));
  expect(button("Remove two.png")).toBeDefined();
  await act(async () => button("Remove two.png")!.click());
  expect(remaining.map(item => item.id)).toEqual(["one", "three"]);
  expect(button("Remove one.png")).toBeDefined();
  expect(button("Remove three.png")).toBeDefined();
});

test("repeated drops append images and count existing images toward the limit", async () => {
  function Gallery() {
    const [images, setImages] = useState<UploadImage[]>([]);
    return <ImageUploadGallery images={images} onImagesChange={setImages} maxFiles={2} />;
  }
  await act(async () => root.render(<Gallery />));
  await drop([image("first.png")]);
  await drop([image("second.png")]);
  expect(button("Remove first.png")).toBeDefined();
  expect(button("Remove second.png")).toBeDefined();
  await drop([image("third.png")]);
  expect(container.querySelector('[role="alert"]')?.textContent).toContain("no more than 2");
  expect(button("Remove third.png")).toBeUndefined();
});

test("removing one occurrence of the same file keeps the other occurrence", async () => {
  let count = 0;
  function Gallery() {
    const [images, setImages] = useState<UploadImage[]>([]);
    count = images.length;
    return <ImageUploadGallery images={images} onImagesChange={setImages} />;
  }
  await act(async () => root.render(<Gallery />));
  const sameFile = image("same.png");
  await drop([sameFile]);
  await drop([sameFile]);
  expect(count).toBe(2);
  await act(async () => button("Remove same.png")!.click());
  expect(count).toBe(1);
  expect(button("Remove same.png")).toBeDefined();
});

test("a rejected batch leaves saved previews untouched", async () => {
  await act(async () => root.render(<ImageUploadGallery images={saved} onImagesChange={() => { throw new Error("must not change"); }} />));
  await drop([new File(["text"], "wrong.txt", { type: "text/plain" })]);
  expect(container.querySelector('[role="alert"]')?.textContent).toContain("not supported");
  expect(button("Remove one.png")).toBeDefined();
  expect(button("Remove two.png")).toBeDefined();
});

test("pending selection prevents a second drop and disables removal", async () => {
  let finish!: () => void;
  const pending = new Promise<void>(resolve => { finish = resolve; });
  let calls = 0;
  await act(async () => root.render(<ImageUploadGallery images={saved} onImagesChange={() => undefined}
    onFilesSelected={async () => { calls++; await pending; }} />));
  await drop([image("first.png")]);
  expect(button("Remove one.png")?.disabled).toBe(true);
  await drop([image("second.png")]);
  expect(calls).toBe(1);
  await act(async () => finish());
});

test("keyboard upload action opens the file chooser without submitting its form", async () => {
  let opened = 0;
  let submitted = 0;
  await act(async () => root.render(<form onSubmit={e => { e.preventDefault(); submitted++; }}>
    <ImageUploadGallery images={[]} onImagesChange={() => undefined} />
  </form>));
  container.querySelector('input[type="file"]')!.addEventListener("click", () => { opened++; });
  const add = button("Add images");
  expect(add).toBeDefined();
  await act(async () => { add!.focus(); await userEvent.setup().keyboard("{Enter}"); });
  expect(opened).toBe(1);
  expect(submitted).toBe(0);
});

test("logo drops replace one selection and clearing it restores the saved preview", async () => {
  let selectedName: string | null = null;
  await act(async () => root.render(<ImageFilePicker id="logo" name="logo_left" label="Left logo" current="saved" currentSrc="/saved-logo.png" onFileChange={file => { selectedName = file?.name ?? null; }} />));
  expect(container.querySelector('input[type="file"]')?.hasAttribute("multiple")).toBe(false);
  await drop([image("first.png")]);
  expect(selectedName).toBe("first.png");
  await drop([image("second.png")]);
  expect(selectedName).toBe("second.png");
  expect(container.textContent).not.toContain(".png");
  expect(container.querySelectorAll('img')).toHaveLength(1);
  await act(async () => button("Clear selected Left logo")!.click());
  expect(container.querySelector('img')?.getAttribute("src")).toBe("/saved-logo.png");
  expect(selectedName).toBeNull();
});

test("a multiple-file logo drop is rejected without replacing the valid selection", async () => {
  let selectedName = "";
  await act(async () => root.render(<ImageFilePicker id="logo" name="logo_left" label="Left logo" onFileChange={file => { selectedName = file?.name ?? ""; }} />));
  await drop([image("keep.png")]);
  await drop([image("wrong-one.png"), image("wrong-two.png")]);
  expect(container.querySelector('[role="alert"]')?.textContent).toContain("no more than 1");
  expect(selectedName).toBe("keep.png");
  expect(container.textContent).not.toContain(".png");
});

test("the small remove action acts immediately and failure preserves the images", async () => {
  let calls = 0;
  await act(async () => root.render(<ImageUploadGallery images={saved}
    onImagesChange={async () => { calls++; throw new Error("Connection failed. Try again."); }} />));
  await act(async () => button("Remove two.png")!.click());
  expect(container.querySelector('[role="dialog"]')).toBeNull();
  expect(calls).toBe(1);
  expect(container.querySelector('[role="alert"]')?.textContent).toContain("Connection failed");
  expect(button("Remove one.png")).toBeDefined();
  expect(button("Remove two.png")).toBeDefined();
  expect(button("Remove three.png")).toBeDefined();
});

test("image preview opens a plain viewer with navigation, individual removal and Escape exit", async () => {
  function Gallery() {
    const [images, setImages] = useState(saved);
    return <ImageUploadGallery images={images} onImagesChange={setImages} />;
  }
  await act(async () => root.render(<Gallery />));
  await act(async () => { button("View two.png")!.focus(); button("View two.png")!.click(); });
  const dialog = () => document.querySelector<HTMLElement>('[role="dialog"][aria-label="Image preview"]');
  expect(dialog()).not.toBeNull();
  expect(dialog()!.querySelectorAll('img')).toHaveLength(1);
  expect(dialog()!.querySelector('img')?.getAttribute("alt")).toBe("two.png");
  const action = (name: string) => dialog()!.querySelector<HTMLButtonElement>(`button[aria-label="${name}"]`)!;
  await act(async () => action("Next image").click());
  expect(dialog()!.querySelector('img')?.getAttribute("alt")).toBe("three.png");
  await act(async () => action("Remove image").click());
  expect(button("Remove three.png")).toBeUndefined();
  expect(button("Remove one.png")).toBeDefined();
  expect(button("Remove two.png")).toBeDefined();
  await act(async () => { dialog()!.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape", bubbles: true })); });
  expect(dialog()).toBeNull();
  expect(document.activeElement).toBe(button("View two.png"));
});

test("announcement viewing keeps keyboard focus inside the viewer and Escape does not close its parent", async () => {
  let parentClicks = 0;
  let parentEscapes = 0;
  const onEscape = (event: KeyboardEvent) => { if (event.key === "Escape") parentEscapes++; };
  window.addEventListener("keydown", onEscape);
  try {
    await act(async () => root.render(<div onClick={() => { parentClicks++; }}><ImageCarousel images={saved} title="Notice" /></div>));
    await act(async () => button("Next image")!.click());
    expect(parentClicks).toBe(0);
    await act(async () => button("Previous image")!.click());
    await act(async () => { button("Open Notice image")!.focus(); button("Open Notice image")!.click(); });
    const dialog = document.querySelector<HTMLElement>('[role="dialog"]')!;
    expect(parentClicks).toBe(0);
    expect(dialog.querySelector('[aria-label="Remove image"]')).toBeNull();
    const close = dialog.querySelector<HTMLButtonElement>('[aria-label="Close image preview"]')!;
    const next = dialog.querySelector<HTMLButtonElement>('[aria-label="Next image"]')!;
    await act(async () => { close.focus(); await userEvent.setup().keyboard("{Shift>}{Tab}{/Shift}"); });
    expect(document.activeElement).toBe(next);
    await act(async () => userEvent.setup().keyboard("{ArrowRight}"));
    expect(dialog.querySelector('img')?.getAttribute("src")).toBe("/two.png");
    await act(async () => userEvent.setup().keyboard("{Escape}"));
    expect(parentEscapes).toBe(0);
    expect(document.querySelector('[role="dialog"]')).toBeNull();
  } finally { window.removeEventListener("keydown", onEscape); }
});

test("a saved logo opens one image without navigation or a delete-saved-logo action", async () => {
  await act(async () => root.render(<ImageFilePicker id="logo" name="logo_left" label="Left logo" current="saved" currentSrc="/saved-logo.png" />));
  await act(async () => button("View Left logo")!.click());
  const dialog = document.querySelector('[role="dialog"]')!;
  expect(dialog.querySelectorAll('img')).toHaveLength(1);
  expect(dialog.querySelector('[aria-label="Next image"]')).toBeNull();
  expect(dialog.querySelector('[aria-label="Remove image"]')).toBeNull();
});

test("clinical file selection accepts one PDF and rejects an extra file without submitting it", async () => {
  const received: string[] = [];
  await act(async () => root.render(<FileUploadField label="Lab results" multiple={false} maxBytes={10 * 1024 * 1024}
    allowedTypes={["application/pdf", "image/jpeg", "image/png"]} onFilesSelected={files => { received.push(files[0].name); }} />));
  const pdf = new File(["%PDF-example"], "lab.pdf", { type: "application/pdf" });
  await drop([pdf]);
  expect(received).toEqual(["lab.pdf"]);
  await drop([pdf, image("extra.png")]);
  expect(received).toEqual(["lab.pdf"]);
  expect(container.querySelector('[role="alert"]')?.textContent).toContain("no more than 1");
});

test("failed upload feedback leaves existing images and permits retry", async () => {
  let attempts = 0;
  await act(async () => root.render(<ImageUploadGallery images={saved} onImagesChange={() => undefined}
    onFilesSelected={async () => { attempts++; if (attempts === 1) throw new Error("Upload interrupted."); }} />));
  await drop([image("retry.png")]);
  expect(container.querySelector('[role="alert"]')?.textContent).toContain("Upload interrupted");
  expect(button("Remove two.png")).toBeDefined();
  await drop([image("retry.png")]);
  expect(attempts).toBe(2);
  expect(container.querySelector('[role="alert"]')).toBeNull();
});

test("the native chooser recovers after an invalid selection", async () => {
  let received = "";
  await act(async () => root.render(<FileUploadField label="Receipt" maxBytes={12} allowedTypes={["image/png"]}
    onFilesSelected={files => { received = files[0].name; }} />));
  const input = container.querySelector<HTMLInputElement>('input[type="file"]')!;
  const user = userEvent.setup({ applyAccept: false });
  await act(async () => user.upload(input, new File(["invalid"], "wrong.txt", { type: "text/plain" })));
  expect(container.querySelector('[role="alert"]')).not.toBeNull();
  await act(async () => user.upload(input, image("valid.png")));
  expect(received).toBe("valid.png");
  expect(container.querySelector('[role="alert"]')).toBeNull();
});
