import { Page } from '@playwright/test';

export interface StatsSnapshot {
  [statName: string]: string;
}

export async function readStatSlots(
  page: Page,
  player: 'self' | 'opponent' | string,
): Promise<StatsSnapshot> {
  return await page.evaluate((p) => {
    const out: Record<string, string> = {};
    document
      .querySelectorAll<HTMLElement>(`[data-stat][data-player="${p}"]`)
      .forEach((node) => {
        const key = node.getAttribute('data-stat') || '';
        out[key] = (node.textContent || '').trim();
      });
    return out;
  }, player);
}

const DEFAULT_TOKENS = new Set([
  '0',
  '0%',
  '0 ms',
  '0/0',
  '—',
  '-',
  '',
  'null',
  'undefined',
]);

export function isNonDefaultStatValue(value: string): boolean {
  if (DEFAULT_TOKENS.has(value)) return false;
  // also reject "0 / 0" or "0/0" with whitespace
  const compact = value.replace(/\s+/g, '');
  if (DEFAULT_TOKENS.has(compact)) return false;
  return true;
}

export function hasAnyNonDefault(snapshot: StatsSnapshot): boolean {
  return Object.values(snapshot).some(isNonDefaultStatValue);
}

export function formatSnapshot(snapshot: StatsSnapshot): string {
  return Object.entries(snapshot)
    .map(([k, v]) => `${k}=${JSON.stringify(v)}`)
    .join(', ');
}
