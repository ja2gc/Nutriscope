import { afterEach, describe, expect, test, vi } from "vitest";
import { cropProfilePhoto } from "./cropProfilePhoto";

describe("cropProfilePhoto", () => {
  afterEach(() => vi.unstubAllGlobals());

  test("exports the selected square as a bounded JPEG data URL", async () => {
    const drawImage = vi.fn();
    const context = { fillStyle: "", fillRect: vi.fn(), drawImage };
    const canvas = {
      width: 0,
      height: 0,
      getContext: vi.fn(() => context),
      toDataURL: vi.fn(() => "data:image/jpeg;base64,cropped"),
    };
    class TestImage {
      onload: (() => void) | null = null;
      onerror: (() => void) | null = null;
      set src(_value: string) { this.onload?.(); }
    }

    vi.stubGlobal("Image", TestImage);
    vi.stubGlobal("document", { createElement: vi.fn(() => canvas) });

    const result = await cropProfilePhoto("data:image/png;base64,source", {
      x: 12, y: 24, width: 300, height: 300,
    });

    expect(result).toBe("data:image/jpeg;base64,cropped");
    expect(canvas.width).toBe(512);
    expect(canvas.height).toBe(512);
    expect(drawImage).toHaveBeenCalledWith(expect.any(TestImage), 12, 24, 300, 300, 0, 0, 512, 512);
    expect(canvas.toDataURL).toHaveBeenCalledWith("image/jpeg", 0.88);
  });
});
