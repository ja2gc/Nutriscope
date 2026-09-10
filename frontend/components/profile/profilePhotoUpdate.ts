export type ProfilePhotoIntent = "unchanged" | "replace" | "remove";

export function profilePhotoUpdate(
  intent: ProfilePhotoIntent,
  photo: string | null,
): { profile_photo?: string | null } {
  if (intent === "remove") {
    return { profile_photo: null };
  }

  if (intent === "replace" && photo?.startsWith("data:image/")) {
    return { profile_photo: photo };
  }

  return {};
}
