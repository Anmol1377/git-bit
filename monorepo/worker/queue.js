import { isValidEvent } from '../shared/version.js';

// Drops anything the shared contract does not recognise.
export const drain = events => events.filter(isValidEvent);
