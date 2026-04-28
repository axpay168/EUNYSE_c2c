<template>
  <div>
    <p><a href="#/pages/setting/securityCenter">正在前往 security-center.html</a></p>
  </div>
</template>

<script>
/**
 * EURNYSE - 驗證中心 (純 H5 Vue2 Options API)
 * 以 docs/previews/nnn/verification-center.html 為唯一基準逐字遷移
 * 原稿僅是一個 <meta http-equiv="refresh" content="0; url=./security-center.html"/> 的重定向跳板頁，
 * 這裡改以 mounted 內 window.location.href 立刻跳轉，行為一致。
 */
var BODY_CLASSES = [
  "nc2c-page",
  "nc2c-page--verification-center"
];

export default {
  name: "VerificationCenterH5",
  mounted: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.add(BODY_CLASSES[i]);
      }
      document.body.setAttribute("data-nc2c-page", "verification-center");
      document.body.setAttribute("data-nc2c-locked", "true");
    } catch (e) {}
    try { window.location.href = "#/pages/setting/securityCenter"; } catch (e) {}
  },
  beforeDestroy: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.remove(BODY_CLASSES[i]);
      }
      document.body.removeAttribute("data-nc2c-page");
      document.body.removeAttribute("data-nc2c-locked");
    } catch (e) {}
  }
};
</script>

<style>
@import url("../../static/previews/nc2c/css/preview-entry.css");
</style>
