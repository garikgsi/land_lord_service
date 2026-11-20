import type { Component } from 'vue';

export interface Section {
  path: string;
  title: string;
  icon: Component;
  admin?: boolean;
}
