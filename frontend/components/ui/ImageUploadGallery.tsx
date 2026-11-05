"use client";

import React, { useMemo, useRef, useState } from "react";
import { Camera, ChevronLeft, ChevronRight, Upload, X } from "lucide-react";
import { validateUploadFiles } from "@/lib/uploadValidation";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { FileUploadField } from "./FileUploadField";
import { ImageViewer } from "./ImageViewer";
import { RemoveButton } from "./RemoveButton";

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
  const [viewerOpen, setViewerOpen] = useState(false);

  function move(offset: number) {
    if (!hasMany) return;
    setActiveIndex((current) => (current + offset + images.length) % images.length);
  }

  if (!activeImage) return null;

  return (
    <>
    <div onClick={event => event.stopPropagation()} onKeyDown={event => {
      if (event.key === "Enter" || event.key === " ") event.stopPropagation();
    }} className={`relative overflow-hidden rounded-lg border border-warm-200 bg-warm-50 ${className}`}>
      <button type="button" aria-label={`Open ${title} image`} onClick={event => { event.stopPropagation(); setViewerOpen(true); }} className="block w-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-green-500">
        <FittedImageFrame src={activeImage.src} alt={title} />
      </button>
      {hasMany && (
        <>
          <button
            type="button"
            aria-label="Previous image"
            onClick={() => move(-1)}
            className="absolute left-2 top-1/2 z-20 inline-flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-forest-900/80 text-white hover:bg-forest-800"
          >
            <ChevronLeft className="h-5 w-5" />
          </button>
          <button
            type="button"
            aria-label="Next image"
            onClick={() => move(1)}
            className="absolute right-2 top-1/2 z-20 inline-flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-forest-900/80 text-white hover:bg-forest-800"
          >
            <ChevronRight className="h-5 w-5" />
          </button>
          <div className="absolute bottom-2 left-1/2 z-20 -translate-x-1/2 rounded-full bg-forest-900/80 px-2.5 py-1 text-xs font-bold text-white">
            {activeIndex + 1} / {images.length}
          </div>
        </>
      )}
    </div>
    {viewerOpen && <ImageViewer images={images} activeIndex={activeIndex} onIndexChange={setActiveIndex} onClose={() => setViewerOpen(false)} />}
    </>
  );
}

export function ImageUploadGallery({
  images,
  onImagesChange,
  onFilesSelected,
  onBusyChange,
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
  onBusyChange?: (busy: boolean) => void;
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
  const [validationError, setValidationError] = useState<string | null>(null);
  const [avatarMenuOpen, setAvatarMenuOpen] = useState(false);
  const fileInput = useRef<HTMLInputElement>(null);
  const [selecting, setSelecting] = useState(false);
  const [removing, setRemoving] = useState<string | null>(null);
  const [mutationError, setMutationError] = useState<string | null>(null);
  const [previewOpen, setPreviewOpen] = useState(false);

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

  const locked = disabled || uploading || selecting || removing !== null || deletingImageId !== null;
  async function selectImages(files: File[]) {
    setSelecting(true);
    onBusyChange?.(true);
    setMutationError(null);
    try {
      if (onFilesSelected) await onFilesSelected(files);
      else {
        const added = (await readImages(files)).map(image => ({ ...image, id: crypto.randomUUID() }));
        await onImagesChange([...images, ...added]);
      }
    } finally { setSelecting(false); onBusyChange?.(false); }
  }
  async function removeSelectedImage(image: UploadImage) {
    if (locked) return;
    setRemoving(image.id);
    onBusyChange?.(true);
    setMutationError(null);
    try {
      const next = images.filter(item => item.id !== image.id);
      await onImagesChange(next);
      setActiveIndex(current => Math.max(0, Math.min(current, next.length - 1)));
    } catch (caught) {
      setMutationError(caught instanceof Error ? caught.message : "Could not remove this image. Try again.");
    } finally { setRemoving(null); onBusyChange?.(false); }
  }

  return <FileUploadField label={label} allowedTypes={allowedTypes} maxBytes={maxBytes} maxFiles={maxFiles}
    currentCount={existingFilesCountTowardsLimit ? images.length : 0} disabled={locked} busy={uploading}
    buttonLabel={uploadLabel ?? "Add images"} error={mutationError ?? error} onFilesSelected={selectImages}>
    {images.length > 0 && <div className="grid min-w-0 grid-cols-[repeat(auto-fill,minmax(min(100%,7rem),1fr))] gap-3">
      {images.map((image, index) => <div key={image.id} className="relative min-w-0">
        <button type="button" aria-label={"View " + image.name}
          onClick={() => { setActiveIndex(index); setPreviewOpen(true); }} className="block w-full min-w-0 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green-500">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src={image.src} alt={image.name} className="aspect-[4/3] w-full rounded-lg bg-warm-50 object-contain" />
        </button>
        <RemoveButton label={removeLabel ?? "Remove " + image.name} disabled={locked}
          aria-busy={removing === image.id || deletingImageId === image.id}
          onClick={() => { void removeSelectedImage(image); }} className="absolute right-1 top-1 bg-white/90" />
      </div>)}
    </div>}
    {(removing !== null || deletingImageId !== null) && <span role="status" className="sr-only">Removing...</span>}
    {previewOpen && activeImage && <ImageViewer images={images} activeIndex={activeIndex} onIndexChange={setActiveIndex}
      onClose={() => setPreviewOpen(false)} onRemove={removeSelectedImage} busy={locked} error={mutationError ?? error} />}
  </FileUploadField>;
}
