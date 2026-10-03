export type UploadFileLike = {
  size?: number;
  type: string;
  name?: string;
};

export type UploadValidationOptions = {
  maxBytes: number;
  allowedTypes: readonly string[];
  maxFiles?: number;
};

export type UploadValidationResult = { valid: true } | { valid: false; error: string };

export function validateUploadFile(
  file: UploadFileLike,
  options: UploadValidationOptions,
): UploadValidationResult {
  if (file.size !== undefined && (!Number.isFinite(file.size) || file.size <= 0 || file.size > options.maxBytes)) {
    return { valid: false, error: `Choose a file no larger than ${formatBytes(options.maxBytes)}.` };
  }

  if (!options.allowedTypes.includes(file.type.toLowerCase())) {
    return { valid: false, error: "This file type is not supported." };
  }

  return { valid: true };
}

export function validateUploadFiles(
  files: readonly UploadFileLike[],
  options: UploadValidationOptions,
  currentCount = 0,
): UploadValidationResult {
  if (options.maxFiles !== undefined && currentCount + files.length > options.maxFiles) {
    return { valid: false, error: `Choose no more than ${options.maxFiles} files.` };
  }

  for (const file of files) {
    const result = validateUploadFile(file, options);
    if (!result.valid) return result;
  }

  return { valid: true };
}

function formatBytes(bytes: number): string {
  if (bytes % (1024 * 1024) === 0) return `${bytes / (1024 * 1024)} MiB`;
  return `${Math.round(bytes / 1024)} KiB`;
}
