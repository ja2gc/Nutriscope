"use client";

import { useEffect, useState } from "react";
import Cropper, { type Area } from "react-easy-crop";
import { Button } from "@/components/ui/Button";
import { cropProfilePhoto } from "./cropProfilePhoto";

export function ProfilePhotoCropDialog({
  image,
  onCancel,
  onApply,
}: {
  image: string;
  onCancel: () => void;
  onApply: (croppedImage: string) => void;
}) {
  const [crop, setCrop] = useState({ x: 0, y: 0 });
  const [zoom, setZoom] = useState(1);
  const [croppedArea, setCroppedArea] = useState<Area | null>(null);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    function onKeyDown(event: KeyboardEvent) {
      if (event.key === "Escape" && !saving) onCancel();
    }
    window.addEventListener("keydown", onKeyDown);
    return () => window.removeEventListener("keydown", onKeyDown);
  }, [onCancel, saving]);

  async function applyCrop() {
    if (!croppedArea) return;
    setSaving(true);
    setError(null);
    try {
      onApply(await cropProfilePhoto(image, croppedArea));
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : "Failed to crop the photo.");
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="fixed inset-0 z-[80] flex items-center justify-center bg-black/60 p-4" role="presentation">
      <section
        role="dialog"
        aria-modal="true"
        aria-labelledby="profile-photo-crop-title"
        className="w-full max-w-lg overflow-hidden rounded-3xl border border-warm-200 bg-white shadow-2xl"
      >
        <div className="border-b border-warm-100 px-5 py-4">
          <h2 id="profile-photo-crop-title" className="text-base font-extrabold text-warm-900">
            Adjust profile picture
          </h2>
          <p className="mt-1 text-sm text-warm-500">Drag the photo to reposition it inside the circle.</p>
        </div>

        <div className="relative h-[min(68vw,420px)] min-h-72 bg-black sm:h-[420px]">
          <Cropper
            image={image}
            crop={crop}
            zoom={zoom}
            aspect={1}
            cropShape="round"
            showGrid={false}
            onCropChange={setCrop}
            onZoomChange={setZoom}
            onCropComplete={(_, pixels) => setCroppedArea(pixels)}
          />
        </div>

        <div className="space-y-4 px-5 py-4">
          <label className="block text-sm font-bold text-warm-700">
            Zoom
            <input
              type="range"
              min={1}
              max={3}
              step={0.01}
              value={zoom}
              aria-label="Profile photo zoom"
              onChange={(event) => setZoom(Number(event.target.value))}
              className="mt-2 block w-full accent-forest-900"
            />
          </label>
          {error && <p className="text-sm font-semibold text-red-600">{error}</p>}
          <div className="flex justify-end gap-3">
            <Button type="button" variant="secondary" onClick={onCancel} disabled={saving} className="w-auto">
              Cancel
            </Button>
            <Button type="button" onClick={() => void applyCrop()} loading={saving} disabled={!croppedArea} className="w-auto">
              Apply crop
            </Button>
          </div>
        </div>
      </section>
    </div>
  );
}
