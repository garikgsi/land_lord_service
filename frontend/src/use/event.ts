import mitt from 'mitt';
import type { AppEvents } from '../types/use/Event';

const events = mitt<AppEvents>();

/** Shared bus for components, stores and other application scripts. */
export default function useEvent() {
  return events;
}
