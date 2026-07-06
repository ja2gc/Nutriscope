"use client";

import React, { useMemo, useState } from "react";
import { ChevronLeft, ChevronRight, Upload, X } from "lucide-react";

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
    <div className={`relative overflow-hidden rounded-lg border border-warm-200 bg-warm-50 ${className}`}>
      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img src={activeImage.src} alt={title} className="block w-full max-h-[520px] object-cover" />
      {hasMany && (
        <>
          <button
            type="button"
            aria-label="Previous image"
            onClick={() => move(-1)}
            className="absolute left-2 top-1/2 inline-flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full bg-forest-900/80 text-white hover:bg-forest-800"
          >
            <ChevronLeft className="h-5 w-5" />
          </button>
          <button
            type="button"
            aria-label="Next image"
            onClick={() => move(1)}
            className="absolute right-2 top-1/2 inline-flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full bg-forest-900/80 text-white hover:bg-forest-800"
          >
            <ChevronRight className="h-5 w-5" />
          </button>
          <div className="absolute bottom-2 left-1/2 -translate-x-1/2 rounded-full bg-forest-900/80 px-2.5 py-1 text-xs font-bold text-white">
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
  onFilesSelected,
  label = "Images",
  emptyText = "Image preview appears here after upload.",
  uploading = false,
  deletingImageId = null,
  error = null,
  disabled = false,
  variant = "default",
  uploadLabel,
  removeLabel,
}: {
  images: UploadImage[];
  onImagesChange: (images: UploadImage[]) => void | Promise<void>;
  onFilesSelected?: (files: File[]) => void | Promise<void>;
  label?: string;
  emptyText?: string;
  uploading?: boolean;
  deletingImageId?: string | null;
  error?: string | null;
  disabled?: boolean;
  variant?: "default" | "avatar";
  uploadLabel?: string;
  removeLabel?: string;
}) {
  const [activeIndex, setActiveIndex] = useState(0);
  const activeImage = images[Math.min(activeIndex, Math.max(images.length - 1, 0))] ?? null;
  const isAvatar = variant === "avatar";
  const hasMany = !isAvatar && images.length > 1;

  const inputId = useMemo(() => `image-upload-${label.toLowerCase().replace(/\W+/g, "-")}`, [label]);

  async function handleFileChange(event: React.ChangeEvent<HTMLInputElement>) {
    const files = Array.from(event.target.files ?? []);
    try {
      if (onFilesSelected) {
        await onFilesSelected(files);
      } else {
        const nextImages = await readImages(files);
        await onImagesChange([...images, ...nextImages]);
        setActiveIndex(images.length);
      }
    } finally {
      event.target.value = "";
    }
  }

  async function removeImage(index: number) {
    const nextImages = images.filter((_, imageIndex) => imageIndex !== index);
    await onImagesChange(nextImages);
    setActiveIndex((current) => Math.max(0, Math.min(current, nextImages.length - 1)));
  }

  function move(offset: number) {
    if (!hasMany) return;
    setActiveIndex((current) => (current + offset + images.length) % images.length);
  }

  return (
    <div className="space-y-2">
      <label htmlFor={inputId} className="text-xs font-semibold text-warm-500 uppercase tracking-wider">
        {label}
      </label>
      <input
        id={inputId}
        type="file"
        accept="image/*"
        multiple={!isAvatar}
        disabled={disabled || uploading}
        onChange={(event) => { void handleFileChange(event); }}
        className="sr-only"
      />
      <label
        htmlFor={disabled || uploading ? undefined : inputId}
        aria-disabled={disabled || uploading}
        className={`inline-flex min-h-9 w-full items-center justify-center gap-2 rounded-lg border px-3 py-2 text-xs font-bold uppercase tracking-wider transition-colors ${
          disabled || uploading
            ? "cursor-not-allowed border-warm-200 bg-warm-100 text-warm-400"
            : "cursor-pointer border-forest-900 bg-forest-900 text-white hover:bg-forest-800"
        }`}
      >
        <Upload className="h-3.5 w-3.5" />
        {uploading ? "Uploading..." : (uploadLabel ?? "Upload image")}
      </label>
      {error && (
        <div className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">
          {error}
        </div>
      )}

      {activeImage ? (
        <div className={`relative overflow-hidden border border-warm-200 bg-white ${
          isAvatar ? "mx-auto h-36 w-36 rounded-full" : "rounded-lg"
        }`}>
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src={activeImage.src} alt={activeImage.name} className={`block object-cover ${
            isAvatar ? "h-full w-full" : "h-56 w-full"
          }`} />
          <button
            type="button"
            aria-label={removeLabel ?? `Remove ${activeImage.name}`}
            onClick={() => removeImage(activeIndex)}
            disabled={disabled || deletingImageId === activeImage.id}
            className="absolute right-2 top-2 inline-flex h-7 min-w-7 items-center justify-center rounded-full bg-forest-900/90 px-2 text-white hover:bg-forest-800 disabled:cursor-wait disabled:opacity-70"
          >
            {deletingImageId === activeImage.id ? <span className="text-xs font-bold">Removing...</span> : <X className="h-4 w-4" />}
          </button>

          {hasMany && (
            <>
              <button
                type="button"
                aria-label="Previous image"
                onClick={() => move(-1)}
                className="absolute left-2 top-1/2 inline-flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full bg-forest-900/80 text-white hover:bg-forest-800"
              >
                <ChevronLeft className="h-5 w-5" />
              </button>
              <button
                type="button"
                aria-label="Next image"
                onClick={() => move(1)}
                className="absolute right-2 top-1/2 inline-flex h-8 w-8 -translate-y-1/2 items-center justify-center rounded-full bg-forest-900/80 text-white hover:bg-forest-800"
              >
                <ChevronRight className="h-5 w-5" />
              </button>
              <div className="absolute bottom-2 left-1/2 -translate-x-1/2 rounded-full bg-forest-900/80 px-2.5 py-1 text-xs font-bold text-white">
                {activeIndex + 1} / {images.length}
              </div>
            </>
          )}
        </div>
      ) : (
        <div className={`border border-dashed border-warm-200 text-center text-sm text-warm-400 bg-white ${
          isAvatar ? "mx-auto flex h-36 w-36 items-center justify-center rounded-full p-4" : "rounded-lg p-6"
        }`}>
          {emptyText}
        </div>
      )}
    </div>
  );
}
