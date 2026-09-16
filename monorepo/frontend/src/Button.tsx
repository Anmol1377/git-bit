// Illustrative only — the React bit of the monorepo.
import type { Event } from '../../shared/types';

export function TrackedButton({ onEvent }: { onEvent: (e: Event) => void }) {
  return <button onClick={() => onEvent({ id: crypto.randomUUID(), v: 2, kind: 'click', at: Date.now() })}>
    Click me
  </button>;
}
