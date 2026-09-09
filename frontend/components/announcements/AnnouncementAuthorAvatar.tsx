"use client";

import { useEffect, useState } from "react";

type AnnouncementAuthor = {
  name?: string | null;
  profile_photo?: string | null;
};

function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join("");
}

export function AnnouncementAuthorAvatar({
  author,
  size = "md",
}: {
  author?: AnnouncementAuthor | null;
  size?: "sm" | "md";
}) {
  const [photoFailed, setPhotoFailed] = useState(false);
  const photo = author?.profile_photo ?? null;
  const name = author?.name?.trim() || "User";
  const sizeClass = size === "sm" ? "h-9 w-9 text-xs" : "h-11 w-11 text-sm";

  useEffect(() => {
    setPhotoFailed(false);
  }, [photo]);

  return (
    <div className={`${sizeClass} shrink-0 overflow-hidden rounded-full bg-forest-900 text-white flex items-center justify-center font-bold uppercase`}>
      {photo && !photoFailed ? (
        // eslint-disable-next-line @next/next/no-img-element
        <img
          src={photo}
          alt={`${name} profile photo`}
          className="h-full w-full object-cover"
          onError={() => setPhotoFailed(true)}
        />
      ) : (
        <span aria-hidden="true">{initials(name)}</span>
      )}
    </div>
  );
}
