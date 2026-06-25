"use client";

import React, { useMemo, useState } from "react";
import { ChevronLeft, ChevronRight, X } from "lucide-react";

export type UploadImage = {
  id: string;
  name: string;
  src: string;
};

export async function readImages(files: FileList | File[]): Promise<UploadImage[]> {
  return Promise.all(
    Array.from(files)
      .filter((file) => file.type.startsWith("image/"))
      .map((file) => (
        new Promise<UploadImage>((resolve, reject) => {
          const reader = new FileReader();
          reader.onerror = () => reject(reader.error);
          reader.onload = () => resolve({
            id: `${file.name}-${file.size}-${file.lastModified}`,
            name: file.name,
            src: typeof reader.result === "string" ? reader.result : "",
          });
          reader.readAsDataURL(file);
        })
      ))
  );
}

export function imageSrcs(images: UploadImage[]): string[] {
  return images.map((image) => image.src).filter(Boolean);
}

export function imagesFromSrcs(srcs: string[] | undefined | null, fallbackName = "Current image"): UploadImage[] {
  return (srcs ?? []).filter(Boolean).map((src, index) => ({
    id: `${fallbackName}-${index}-${src.slice(0, 24)}`,
    name: srcs?.length === 1 ? fallbackName : `${fallbackName} ${index + 1}`,
    src,
  }));
}

export function ImageCarousel({
  images,
  title,
  className = "",
}: {
  images: UploadImage[];
  title: string;
  className?: string;
}) {
  const [activeIndex, setActiveIndex] = useState(0);
  const activeImage = images[Math.min(activeIndex, Math.max(images.length - 1, 0))] ?? null;
  const hasMany = images.length > 1;

  function move(offset: number) {
    if (!hasMany) return;
    setActiveIndex((current) => (current + offset + images.length) % images.length);
  }

  if (!activeImage) return null;

  return (
    <div className={`relative overflow-hidden rounded-lg border border-zinc-200 bg-zinc-50 ${className}`}>
      <img src={activeImage.src} alt={title} className="block w-full max-h-[520px] object-cover" />
      {hasMany && (
        <>
          <button
            type="button"
            aria-label="Previous image"
            onClick={() => move(-1)}
            className="absolute left-2 top-1/2 inline-flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full bg-zinc-950/75 text-white hover:bg-zinc-900"
          >
            <ChevronLeft className="h-5 w-5" />
          </button>
          <button
            type="button"
            aria-label="Next image"
            onClick={() => move(1)}
            className="absolute right-2 top-1/2 inline-flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full bg-zinc-950/75 text-white hover:bg-zinc-900"
          >
            <ChevronRight className="h-5 w-5" />
          </button>
          <div className="absolute bottom-2 left-1/2 -translate-x-1/2 rounded-full bg-zinc-950/75 px-2.5 py-1 text-[10px] font-bold text-white">
            {activeIndex + 1} / {images.length}
          </div>
        </>
      )}
    </div>
  );
}

export function ImageUploadGallery({
  images,
  onImagesChange,
  label = "Images",
  emptyText = "Image preview appears here after upload.",
}: {
  images: UploadImage[];
  onImagesChange: (images: UploadImage[]) => void;
  label?: string;
  emptyText?: string;
}) {
  const [activeIndex, setActiveIndex] = useState(0);
  const activeImage = images[Math.min(activeIndex, Math.max(images.length - 1, 0))] ?? null;
  const hasMany = images.length > 1;

  const inputId = useMemo(() => `image-upload-${label.toLowerCase().replace(/\W+/g, "-")}`, [label]);

  async function handleFileChange(event: React.ChangeEvent<HTMLInputElement>) {
    const nextImages = await readImages(event.target.files ?? []);
    onImagesChange([...images, ...nextImages]);
    setActiveIndex(images.length);
    event.target.value = "";
  }

  function removeImage(index: number) {
    const nextImages = images.filter((_, imageIndex) => imageIndex !== index);
    onImagesChange(nextImages);
    setActiveIndex((current) => Math.max(0, Math.min(current, nextImages.length - 1)));
  }

  function move(offset: number) {
    if (!hasMany) return;
    setActiveIndex((current) => (current + offset + images.length) % images.length);
  }

  return (
    <div className="space-y-2">
      <label htmlFor={inputId} className="text-[10px] font-semibold text-zinc-500 uppercase tracking-wider">
        {label}
      </label>
      <input
        id={inputId}
        type="file"
        accept="image/*"
        multiple
        onChange={(event) => { void handleFileChange(event); }}
        className="block w-full text-xs text-zinc-600 file:mr-3 file:px-3 file:py-2 file:rounded-lg file:border-0 file:bg-zinc-950 file:text-white file:text-[10px] file:font-bold file:uppercase file:tracking-wider"
      />

      {activeImage ? (
        <div className="relative overflow-hidden rounded-lg border border-zinc-200 bg-white">
          <img src={activeImage.src} alt={activeImage.name} className="block h-56 w-full object-cover" />
          <button
            type="button"
            aria-label={`Remove ${activeImage.name}`}
            onClick={() => removeImage(activeIndex)}
            className="absolute right-2 top-2 inline-flex h-7 w-7 items-center justify-center rounded-full bg-zinc-950/80 text-white hover:bg-zinc-900"
          >
            <X className="h-4 w-4" />
          </button>

          {hasMany && (
            <>
              <button
                type="button"
                aria-label="Previous image"
                onClick={() => move(-1)}
                className="absolute left-2 top-1/2 inline-flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full bg-zinc-950/75 text-white hover:bg-zinc-900"
              >
                <ChevronLeft className="h-5 w-5" />
              </button>
              <button
                type="button"
                aria-label="Next image"
                onClick={() => move(1)}
                className="absolute right-2 top-1/2 inline-flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full bg-zinc-950/75 text-white hover:bg-zinc-900"
              >
                <ChevronRight className="h-5 w-5" />
              </button>
              <div className="absolute bottom-2 left-1/2 -translate-x-1/2 rounded-full bg-zinc-950/75 px-2.5 py-1 text-[10px] font-bold text-white">
                {activeIndex + 1} / {images.length}
              </div>
            </>
          )}
        </div>
      ) : (
        <div className="rounded-lg border border-dashed border-zinc-200 p-6 text-center text-xs text-zinc-400 bg-white">
          {emptyText}
        </div>
      )}
    </div>
  );
}
