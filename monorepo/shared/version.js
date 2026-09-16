// Shared by frontend AND worker. Change this file → both pipelines must run.
export const PAYLOAD_VERSION = 2;
export const isValidEvent = e =>
  !!e && typeof e.id === 'string' && e.v === PAYLOAD_VERSION;
