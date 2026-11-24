<script setup lang="ts" generic="T extends string">
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import type { CSSProperties } from 'vue';
import type { ActionMenuProps } from '../../types/components/ui/ActionMenu';

const props = defineProps<ActionMenuProps<T>>();
const emit = defineEmits<{ select: [action: T] }>();
const root = ref<HTMLElement | null>(null);
const trigger = ref<HTMLButtonElement | null>(null);
const open = ref(false);
const menu = ref<HTMLElement | null>(null);
const menuStyle = ref<CSSProperties>();

async function toggle() {
  if (props.disabled) return;

  open.value = !open.value;

  if (open.value) {
    await nextTick();

    if (props.floating && trigger.value && menu.value) {
      const anchor = trigger.value.getBoundingClientRect();
      const bounds = menu.value.getBoundingClientRect();
      const gap = 4;
      const edge = 8;
      const top =
        anchor.bottom + bounds.height + gap <= window.innerHeight
          ? anchor.bottom + gap
          : Math.max(edge, anchor.top - bounds.height - gap);

      menuStyle.value = {
        position: 'fixed',
        top: top + 'px',
        left:
          Math.max(
            edge,
            Math.min(anchor.right - bounds.width, window.innerWidth - bounds.width - edge),
          ) + 'px',
        right: 'auto',
        bottom: 'auto',
        zIndex: 1080,
      };

      await nextTick();
    }

    menu.value
      ?.querySelector<HTMLButtonElement>('[role="menuitem"]')
      ?.focus({ preventScroll: true });
  }
}

function closeOutside(event: PointerEvent) {
  if (
    event.target instanceof Node &&
    !root.value?.contains(event.target) &&
    !menu.value?.contains(event.target)
  )
    open.value = false;
}

function select(value: T) {
  if (props.disabled) return;

  open.value = false;
  trigger.value?.focus();
  emit('select', value);
}

function keydown(event: KeyboardEvent) {
  if (event.key === 'Escape') {
    event.preventDefault();
    open.value = false;
    trigger.value?.focus();

    return;
  }

  if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;

  event.preventDefault();

  if (!open.value) {
    void toggle();

    return;
  }

  const items = [...(menu.value?.querySelectorAll<HTMLButtonElement>('[role="menuitem"]') ?? [])];
  const current = items.indexOf(document.activeElement as HTMLButtonElement);
  const index =
    event.key === 'Home'
      ? 0
      : event.key === 'End'
        ? items.length - 1
        : (current + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;

  items[index]?.focus();
}

function closeOnMove(event: Event) {
  if (props.floating && !(event.target instanceof Node && menu.value?.contains(event.target)))
    open.value = false;
}

onMounted(() => {
  document.addEventListener('pointerdown', closeOutside);
  window.addEventListener('scroll', closeOnMove, true);
  window.addEventListener('resize', closeOnMove);
});
onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', closeOutside);
  window.removeEventListener('scroll', closeOnMove, true);
  window.removeEventListener('resize', closeOnMove);
});
</script>

<template>
  <div
    ref="root"
    class="dropdown"
    @keydown="keydown"
  >
    <button
      ref="trigger"
      type="button"
      class="btn btn-primary dropdown-toggle"
      :disabled="disabled"
      :aria-expanded="open"
      aria-haspopup="menu"
      @click="toggle"
    >
      {{ label }}
    </button>
    <Teleport
      to="body"
      :disabled="!floating"
    >
      <div
        ref="menu"
        v-if="open"
        class="dropdown-menu dropdown-menu-end show"
        :style="menuStyle"
        role="menu"
        :aria-label="label"
        @keydown.stop="keydown"
      >
        <button
          v-for="item in items"
          :key="item.value"
          type="button"
          class="dropdown-item"
          role="menuitem"
          @click="select(item.value)"
        >
          {{ item.label }}
        </button>
      </div>
    </Teleport>
  </div>
</template>
