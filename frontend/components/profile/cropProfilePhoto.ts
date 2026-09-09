import type { Area } from "react-easy-crop";

function loadImage(src: string): Promise<HTMLImageElement> {
  return new Promise((resolve, reject) => {
    const image = new Image();
    image.onload = () => resolve(image);
    image.onerror = () => reject(new Error("The selected photo could not be opened."));
    image.src = src;
  });
}

export async function cropProfilePhoto(src: string, crop: Area): Promise<string> {
  const image = await loadImage(src);
  const sizes = [512, 448, 384];
  const qualities = [0.88, 0.78, 0.68, 0.58];

  for (const size of sizes) {
    const canvas = document.createElement("canvas");
    canvas.width = size;
    canvas.height = size;
    const context = canvas.getContext("2d");
    if (!context) throw new Error("Photo cropping is unavailable in this browser.");

    context.fillStyle = "#ffffff";
    context.fillRect(0, 0, size, size);
    context.drawImage(image, crop.x, crop.y, crop.width, crop.height, 0, 0, size, size);

    for (const quality of qualities) {
      const result = canvas.toDataURL("image/jpeg", quality);
      if (result.length <= 300000) return result;
    }
  }

  throw new Error("The cropped photo is still too large. Try a simpler or smaller image.");
}
