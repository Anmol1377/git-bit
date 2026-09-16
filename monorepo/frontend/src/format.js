import { PAYLOAD_VERSION } from '../../shared/version.js';

export const label = e => `${e.kind} · v${PAYLOAD_VERSION} · ${new Date(e.at).toISOString().slice(11, 19)}`;
