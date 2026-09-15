# Media, Profile Photos, and Private Storage

## Storage and privacy

- User, receipt, proof, clinical, and report bytes use existing authenticated private storage.
- Domain rows store references, not public URLs, long-lived base64, or provider secrets.
- Private uploads and backups are separate concerns with least-privilege access.
- Verify metadata, bytes, MIME, dimensions, limits, and authorization.
- Failed upload/replace preserves the prior valid state and leaves no orphan object.
- DB cleanup and object-store cleanup are separate operations; verify both.

## Image presentation

- Full images preserve true aspect ratio, center inside a bounded responsive frame, and never stretch.
- Contain/letterbox or pillarbox when the frame differs from the image ratio; crop only where the UI explicitly needs a thumbnail/avatar.
- Profile editing supports the established circular crop interaction (drag/pan/zoom) and preserves the current photo on cancel or failure.
- Close/remove controls stay accessible and above masking/crop layers.
- Use meaningful alt/accessibility labels and shared gallery/viewer primitives; no module-specific duplicates.
