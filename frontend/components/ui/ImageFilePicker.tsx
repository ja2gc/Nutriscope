"use client";

import { forwardRef, useState } from "react";
import { FileUploadField } from "./FileUploadField";
import { readImages, type UploadImage } from "./ImageUploadGallery";
import { ImageViewer } from "./ImageViewer";
import { RemoveButton } from "./RemoveButton";

type ImageFilePickerProps = {
  id: string;
  name: string;
  label: string;
  current?: string | null;
  currentSrc?: string;
  onFileChange?: (file: File | null) => void;
  onReadingChange?: (reading: boolean) => void;
  disabled?: boolean;
  maxBytes?: number;
};

export const ImageFilePicker = forwardRef<HTMLInputElement, ImageFilePickerProps>(function ImageFilePicker(
  { id, name, label, current, currentSrc, onFileChange, onReadingChange, disabled = false, maxBytes = 2 * 1024 * 1024 },
  ref,
) {
  const [selected, setSelected] = useState<UploadImage | null>(null);
  const [previewOpen, setPreviewOpen] = useState(false);
  const [reading, setReading] = useState(false);
  const blocked = disabled || reading;
  const src = selected?.src ?? currentSrc;
  function clearSelection() { setSelected(null); onFileChange?.(null); }

  return (
    <FileUploadField id={id} name={name} inputRef={ref} label={label} multiple={false} maxBytes={maxBytes}
      allowedTypes={["image/jpeg", "image/png", "image/webp"]} disabled={blocked} currentCount={src || current ? 1 : 0}
      buttonLabel={src || current ? "Change image" : "Choose image"} onFilesSelected={async files => {
        setReading(true);
        onReadingChange?.(true);
        try {
          const [preview] = await readImages(files);
          if (!preview) throw new Error("The selected image could not be opened.");
          setSelected(preview);
          onFileChange?.(files[0]);
        } finally { setReading(false); onReadingChange?.(false); }
      }}>
      {src && <div className="relative w-32 max-w-full">
        <button type="button" aria-label={`View ${label}`} onClick={() => setPreviewOpen(true)} className="block w-full rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-green-500">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src={src} alt={`${selected ? "Selected" : "Current"} ${label.toLowerCase()}`} className="aspect-square w-full rounded-lg bg-white p-2 object-contain" />
        </button>
        {selected && <RemoveButton label={`Clear selected ${label}`} disabled={blocked} onClick={clearSelection} className="absolute right-1 top-1 bg-white/90" />}
      </div>}
      <p role="status" className="text-xs text-warm-500">{selected ? "Ready to save." : current ? "Current image saved" : "No image saved"}</p>
      {previewOpen && src && <ImageViewer images={[{ id, name: label, src }]} activeIndex={0} onIndexChange={() => undefined} onClose={() => setPreviewOpen(false)}
        onRemove={selected ? () => { clearSelection(); setPreviewOpen(false); } : undefined} busy={blocked} removeLabel="Clear selection" />}
    </FileUploadField>
  );
});
