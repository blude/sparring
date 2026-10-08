// Shared UTC timestamp for generated-diagram filenames, e.g. 20260901-154332
// (YYYYMMDD-HHMMSS). Every generator appends it: `<base>.<STAMP>.svg`, so
// each run is archived instead of overwriting the last render.
export const STAMP = new Date()
  .toISOString()
  .slice(0, 19)
  .replace(/[-:]/g, "")
  .replace("T", "-");
