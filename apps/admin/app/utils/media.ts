/**
 * Mirrors StoreMediaRequest::MAX_FILE_KB on the API — checking here lets
 * the UI reject an oversized photo instantly instead of uploading tens of
 * megabytes just to have the server say no.
 */
export const MAX_MEDIA_UPLOAD_BYTES = 25600 * 1024

export function isMediaFileTooLarge(file: File): boolean {
  return file.size > MAX_MEDIA_UPLOAD_BYTES
}
