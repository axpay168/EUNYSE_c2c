<template>
  <LoadingBaseFrame>
    <div class="loading-spinner-block__spinner" />
  </LoadingBaseFrame>
</template>

<script>
import LoadingBaseFrame from './LoadingBaseFrame.vue'

export default {
  name: 'LoadingSpinnerBlock',
  components: { LoadingBaseFrame }
}
</script>

<style scoped>
.loading-spinner-block__spinner {
  width: 56px;
  height: 56px;
  border-radius: 50%;
  background:
    conic-gradient(
      from 20deg,
      rgba(35, 136, 255, 0) 0deg,
      rgba(35, 136, 255, 0.14) 85deg,
      #2388ff 160deg,
      #0f63dd 215deg,
      rgba(35, 136, 255, 0) 310deg
    ),
    rgba(255, 255, 255, 0.42);
  box-shadow:
    inset 0 1px 0 rgba(255, 255, 255, 0.86),
    0 12px 26px rgba(65, 124, 190, 0.14);
  animation: loading-spin 1.25s linear infinite;
  position: relative;
}

.loading-spinner-block__spinner::before {
  content: '';
  position: absolute;
  inset: 6px;
  border-radius: 50%;
  background: linear-gradient(145deg, rgba(255, 255, 255, 0.74), rgba(255, 255, 255, 0.36));
  border: 1px solid rgba(255, 255, 255, 0.75);
}

@keyframes loading-spin {
  to { transform: rotate(360deg); }
}
</style>

