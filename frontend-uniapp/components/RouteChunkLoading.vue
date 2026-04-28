<template>
  <LoadingSpinnerBlock />
</template>

<script>
import LoadingSpinnerBlock from './loading/LoadingSpinnerBlock.vue'

export default {
  name: 'RouteChunkLoading',
  components: {
    LoadingSpinnerBlock
  },
  props: {
    scene: { type: String, default: '' }
  }
}
</script>
