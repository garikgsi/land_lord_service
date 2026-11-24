<script setup lang="ts">
import Badge from './ui/Badge.vue';
import { computed } from 'vue';
import ReferenceSelect from './ui/ReferenceSelect.vue';
import TextInput from './ui/TextInput.vue';
import type { MeterLocationDraft, MeterLocationProps } from '../types/forms/Meter';

const props = defineProps<MeterLocationProps>();
const model = defineModel<MeterLocationDraft>({ required: true });
const providerEndpoint = computed(
  () => '/api/objects/' + model.value.objectId + '/unity-providers?unityId=' + props.utilityId,
);
const initialProvider = computed(() =>
  props.initialProvider?.id === model.value.objectUnityProviderId
    ? props.initialProvider
    : undefined,
);
const spaceEndpoint = computed(() => '/api/objects/' + model.value.objectId + '/spaces');
const initialSpace = computed(() =>
  props.initialObject?.id === model.value.objectId ? props.initialSpace : undefined,
);

function objectChanged() {
  model.value.type = 'object';
  model.value.spaceId = null;
  model.value.objectUnityProviderId = null;
}

function spaceChanged(id: number | null) {
  model.value.type = id ? 'space' : 'object';
  model.value.objectUnityProviderId = null;
}
</script>

<template>
  <div class="row g-3">
    <div
      v-if="fixedLocation"
      class="col-12 d-flex flex-wrap gap-2"
    >
      <Badge wrap>
        {{ initialObject?.name }}
      </Badge>
      <Badge
        v-if="initialSpace"
        wrap
      >
        {{ initialSpace.name }}
      </Badge>
    </div>
    <div
      v-if="!fixedLocation"
      class="col-12 col-md-6 col-xl-4"
    >
      <ReferenceSelect
        id="meter-object"
        v-model="model.objectId"
        label="Объект"
        endpoint="/api/objects"
        :initial="initialObject"
        :disabled="disabled"
        :error="errors?.objectId"
        @update:model-value="objectChanged"
      />
    </div>
    <div
      v-if="!fixedLocation"
      class="col-12 col-md-6 col-xl-4"
    >
      <ReferenceSelect
        :key="spaceEndpoint"
        id="meter-space"
        v-model="model.spaceId"
        label="Помещение"
        :endpoint="spaceEndpoint"
        :initial="initialSpace"
        :required="false"
        placeholder="На объекте, без помещения"
        :disabled="disabled || !model.objectId"
        :error="errors?.spaceId"
        @update:model-value="spaceChanged"
      />
    </div>
    <div
      v-if="model.type === 'object' && model.objectId && utilityId"
      class="col-12 col-md-6 col-xl-4"
    >
      <ReferenceSelect
        :key="providerEndpoint"
        id="meter-provider"
        v-model="model.objectUnityProviderId"
        label="Поставщик"
        :endpoint="providerEndpoint"
        :initial="initialProvider"
        :required="false"
        placeholder="Без поставщика"
        :disabled="disabled"
        :error="errors?.objectUnityProviderId"
      />
      <button
        v-if="model.objectUnityProviderId && !disabled"
        type="button"
        class="btn btn-sm mt-2"
        @click="model.objectUnityProviderId = null"
      >
        Убрать привязку
      </button>
    </div>
    <div class="col-12">
      <TextInput
        id="meter-place-comment"
        v-model="model.comment"
        label="Комментарий к установке"
        :readonly="disabled"
      />
    </div>
  </div>
</template>
