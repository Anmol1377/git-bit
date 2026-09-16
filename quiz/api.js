/* Thin client for api/quiz.php. Every device talks to the same MySQL database,
   which is what makes the dashboard genuinely live across phones. */
import { API } from './config.js';

export const configured = () => !API.includes('REPLACE-ME');

async function call(action, body) {
  if (!configured())
    throw new Error('Quiz backend not configured — set the API URL in quiz/config.js');
  const r = await fetch(`${API}?action=${action}&t=${Date.now()}`, body
    ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }
    : undefined);
  const text = await r.text();
  let json;
  try { json = JSON.parse(text); }
  catch {
    // InfinityFree serves an HTML interstitial to requests it does not like
    throw new Error(r.ok ? 'Server replied with HTML, not JSON — check the API URL' : `Server error ${r.status}`);
  }
  if (!r.ok) throw new Error(json.error ?? `Server error ${r.status}`);
  return json;
}

/** Stable per-device id — the server uses it to allow one attempt per device. */
export function deviceId() {
  let id = localStorage.getItem('gitbit.device');
  if (!id) localStorage.setItem('gitbit.device', id = Math.random().toString(36).slice(2, 12));
  return id;
}

export const questions = () => call('questions');
export const state     = () => call('state');
export const join      = name => call('join', { name, device: deviceId() });
export const answer    = (playerId, qid, choice, ms) => call('answer', { playerId, qid, choice, ms });
export const reset     = key => call('reset', { key });
