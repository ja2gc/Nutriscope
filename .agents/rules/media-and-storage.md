# Media, Profile Photos, and Private Storage

## Storage and privacy

- Profile/receipt/proof/clinical/report bytes use existing authenticated private storage.
- Never store provider/public URL, private object key, long-lived base64 in domain rows.
- Private uploads ≠ backups; separate least-privilege storage scope.
- Verify metadata + bytes. Failed upload/seed leaves no orphan; failed replace preserves old file.
- DB cleanup ≠ R2/S3 cleanup. Verify both.

## Profile photos

- Seeded author uses real private profile photo when present; rerun preserves manual photo.
- Web select → circular crop, drag/pan, zoom, no stretch, square export, circular avatar.
- Cancel/validation/storage failure preserves current photo.
- Remove control accessible + above circle frame.

## Uploaded-image presentation

- Uploaded photo: full image, true ratio, centered, bounded responsive frame.
- Announcement/post: card-relative fixed responsive frame, blurred backdrop, `contain`; never consume page.
- Full viewer: black letter/pillarbox as needed; no distortion.
- Thumbnail/avatar may crop; full viewer contains all.
- Meaningful alt/accessibility labels.
- Exclude logos/QR/icons/decorative/layout graphics from user-photo rules.
- Announcement: real author photo; no “Announcement author” or redundant “Posted to department.”
- Reuse shared gallery/carousel/viewer; no module duplicates.
