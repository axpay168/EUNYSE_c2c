<template>
  <div>
    <p><a href="#/pages/setting/mixrecharge">正在前往 deposit.html</a></p>
  </div>
</template>

<script>
/**
 * EURFOREX - 儲值 (純 H5 Vue2 Options API)
 * 以 docs/previews/nnn/recharge.html 為唯一基準逐字遷移
 * 原稿僅是一個 <meta http-equiv="refresh" content="0; url=./deposit.html"/> 的重定向跳板頁，
 * 這裡改以 mounted 內 window.location.href 立刻跳轉，行為一致。
 */
var BODY_CLASSES = [
  "nc2c-page",
  "nc2c-page--recharge"
];

export default {
  name: "RechargeH5",
  mounted: function () {
    try {
      for (var i = 0; i < BODY_CLASSES.length; i++) {
        document.body.classList.add(BODY_CLASSES[i]);
      }
      document.body.setAttribute("data-nc2c-page", "recharge");
      document.body.setAttribute("data-nc2c-locked", "true");
    } catch (e) {}
    try { window.location.href = "#/pages/setting/mixrecharge"; } catch (e) {}
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
