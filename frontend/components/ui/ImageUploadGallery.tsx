"use client";

import React, { useMemo, useRef, useState } from "react";
import { Camera, ChevronLeft, ChevronRight, Upload, X } from "lucide-react";
import { validateUploadFiles } from "@/lib/uploadValidation";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";

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

export function FittedImageFrame({
  src,
  alt,
  className = "",
  variant = "feed",
}: {
  src: string;
  alt: string;
  className?: string;
  variant?: "feed" | "viewer";
}) {
  return (
    <div className={`relative w-full overflow-hidden bg-black ${
      variant === "viewer" ? "h-[min(75vh,720px)] min-h-60" : "h-[clamp(180px,52vw,420px)]"
    } ${className}`}>
      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img
        src={src}
        alt=""
        aria-hidden="true"
        className="absolute inset-0 h-full w-full scale-110 object-cover blur-2xl brightness-50"
      />
      <div aria-hidden="true" className="absolute inset-0 bg-black/20" />
      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img src={src} alt={alt} className="relative z-10 block h-full w-full object-contain" />
    </div>
  );
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
      <FittedImageFrame src={activeImage.src} alt={title} />
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
  avatarFallback,
  maxBytes = 5 * 1024 * 1024,
  allowedTypes = ["image/jpeg", "image/png", "image/webp"],
  maxFiles = 15,
  existingFilesCountTowardsLimit = true,
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
  avatarFallback?: React.ReactNode;
  maxBytes?: number;
  allowedTypes?: readonly string[];
  maxFiles?: number;
  existingFilesCountTowardsLimit?: boolean;
}) {
  const [activeIndex, setActiveIndex] = useState(0);
  const activeImage = images[Math.min(activeIndex, Math.max(images.length - 1, 0))] ?? null;
  const isAvatar = variant === "avatar";
  const hasMany = !isAvatar && images.length > 1;
  const [validationError, setValidationError] = useState<string | null>(null);
  const [avatarMenuOpen, setAvatarMenuOpen] = useState(false);
  const fileInput = useRef<HTMLInputElement>(null);

  const inputId = useMemo(() => `image-upload-${label.toLowerCase().replace(/\W+/g, "-")}`, [label]);

  async function handleFileChange(event: React.ChangeEvent<HTMLInputElement>) {
    const files = Array.from(event.target.files ?? []);
    try {
      const validation = validateUploadFiles(
        files,
        { maxBytes, allowedTypes, maxFiles: isAvatar ? 1 : maxFiles },
        isAvatar || !existingFilesCountTowardsLimit ? 0 : images.length,
      );
      if (!validation.valid) {
        setValidationError(validation.error);
        if (isAvatar) setAvatarMenuOpen(true);
        return;
      }
      setValidationError(null);
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

  const input = <input
    ref={fileInput}
    id={inputId}
    type="file"
    accept={allowedTypes.join(",")}
    multiple={!isAvatar}
    disabled={disabled || uploading}
    tabIndex={isAvatar ? -1 : undefined}
    aria-hidden={isAvatar || undefined}
    onChange={(event) => { void handleFileChange(event); }}
    className="peer sr-only"
  />;

  if (isAvatar) {
    return <div className="shrink-0">
      {input}
      <Popover open={avatarMenuOpen} onOpenChange={setAvatarMenuOpen}>
        <PopoverTrigger asChild>
          <button type="button" aria-label="Edit profile picture" disabled={disabled || uploading}
            data-avatar-wrapper="true"
            className="relative block h-12 w-12 rounded-full bg-warm-100 transition-shadow hover:ring-2 hover:ring-brand-green-500/30 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green-500 disabled:opacity-50 sm:h-20 sm:w-20">
            <span data-avatar-crop="true" className="flex h-full w-full items-center justify-center overflow-hidden rounded-full border border-warm-200 text-2xl font-bold text-brand-green-800">
              {activeImage ? (
                // eslint-disable-next-line @next/next/no-img-element
                <img src={activeImage.src} alt={activeImage.name} className="block h-full w-full object-cover" />
              ) : avatarFallback ?? emptyText}
            </span>
            <span aria-hidden="true" className="absolute -bottom-1 -right-1 flex h-6 w-6 items-center justify-center rounded-full border-2 border-white bg-forest-900 text-white">
              <Camera className="h-3.5 w-3.5" />
            </span>
          </button>
        </PopoverTrigger>
        <PopoverContent align="start" sideOffset={8} className="w-[min(16rem,calc(100vw-2rem))] p-1">
          <button type="button" onClick={() => { setAvatarMenuOpen(false); fileInput.current?.click(); }}
            className="flex min-h-11 w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-semibold text-warm-700 hover:bg-warm-50 focus-visible:bg-warm-50 focus-visible:outline-none">
            <Upload aria-hidden="true" className="h-4 w-4 shrink-0" />{uploadLabel ?? "Choose profile picture"}
          </button>
          {activeImage && <button type="button" disabled={deletingImageId === activeImage.id}
            onClick={() => { setAvatarMenuOpen(false); void removeImage(activeIndex); }}
            className="flex min-h-11 w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-semibold text-red-700 hover:bg-red-50 focus-visible:bg-red-50 focus-visible:outline-none">
            <X aria-hidden="true" className="h-4 w-4 shrink-0" />{removeLabel ?? `Remove ${activeImage.name}`}
          </button>}
          {(error ?? validationError) && <p role="alert" className="px-3 py-2 text-xs font-semibold text-red-700">{error ?? validationError}</p>}
        </PopoverContent>
      </Popover>
    </div>;
  }

  const uploadControl = (
      <label
        htmlFor={disabled || uploading ? undefined : inputId}
        aria-disabled={disabled || uploading}
        className={`inline-flex min-h-9 w-full items-center justify-center gap-2 rounded-lg border px-3 py-2 text-xs font-bold uppercase tracking-wider transition-colors peer-focus-visible:ring-2 peer-focus-visible:ring-emerald-500/40 ${
          disabled || uploading
            ? "cursor-not-allowed border-warm-200 bg-warm-100 text-warm-400"
            : "cursor-pointer border-forest-900 bg-forest-900 text-white hover:bg-forest-800"
        }`}
      >
        <Upload className="h-3.5 w-3.5" />
        {uploading ? "Uploading..." : (uploadLabel ?? "Upload image")}
      </label>
  );

  return (
    <div className="space-y-2">
      <label htmlFor={inputId} className="text-xs font-semibold text-warm-500 uppercase tracking-wider">
        {label}
      </label>
      {input}
      {uploadControl}
      {(error ?? validationError) && (
        <div className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">
          {error ?? validationError}
        </div>
      )}

      {activeImage ? (
        <div
          className="relative overflow-hidden rounded-lg border border-warm-200 bg-white"
        >
          <FittedImageFrame src={activeImage.src} alt={activeImage.name} />
          <button
            type="button"
            aria-label={removeLabel ?? `Remove ${activeImage.name}`}
            onClick={() => removeImage(activeIndex)}
            disabled={disabled || deletingImageId === activeImage.id}
            className="absolute right-2 top-2 z-20 inline-flex h-8 min-w-8 items-center justify-center rounded-full bg-forest-900/90 px-2 text-white shadow-sm hover:bg-forest-800 disabled:cursor-wait disabled:opacity-70"
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
        <div className="rounded-lg border border-dashed border-warm-200 bg-white p-6 text-center text-sm text-warm-400">
          {emptyText}
        </div>
      )}
    </div>
  );
}
