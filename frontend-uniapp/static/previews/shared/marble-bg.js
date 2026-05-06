/**
 * 通用冰蓝大理石全屏背景：插入到 body 最前。
 * 依赖同级 marble-bg.css（由页面 <link> 引入）。
 */
(function () {
  if (document.getElementById("eurforex-marble-bg-mount")) return;

  var root = document.createElement("div");
  root.id = "eurforex-marble-bg-mount";
  root.className = "eurforex-marble-bg";
  root.setAttribute("aria-hidden", "true");

  root.innerHTML =
    '<svg class="eurforex-marble-bg__svgdefs" aria-hidden="true" focusable="false">' +
    "<defs>" +
    '<filter id="eurforexIceMarbleFilter" x="-18%" y="-18%" width="136%" height="136%" color-interpolation-filters="sRGB">' +
    '<feTurbulence type="fractalNoise" baseFrequency="0.0068 0.0128" numOctaves="6" seed="37" stitchTiles="stitch" result="eurforexNoise"/>' +
    '<feColorMatrix in="eurforexNoise" type="matrix" values="' +
    "0.38 0.14 0.78 0 0.74 " +
    "0.28 0.22 0.82 0 0.86 " +
    "0.36 0.26 0.76 0 0.94 " +
    "0 0 0 0.9 0" +
    '" result="eurforexIceTint"/>' +
    '<feDisplacementMap in="SourceGraphic" in2="eurforexNoise" scale="34" xChannelSelector="R" yChannelSelector="G" result="eurforexDisp"/>' +
    '<feColorMatrix in="eurforexDisp" type="matrix" values="' +
    "0.94 0.03 0.05 0 0.05 " +
    "0.04 0.91 0.09 0 0.07 " +
    "0.05 0.1 0.86 0 0.1 " +
    "0 0 0 1 0" +
    '" result="eurforexTinted"/>' +
    '<feBlend in="eurforexTinted" in2="eurforexIceTint" mode="soft-light" result="eurforexFinal"/>' +
    "</filter>" +
    "</defs>" +
    "</svg>" +
    '<div class="eurforex-marble-bg__base"></div>' +
    '<div class="eurforex-marble-bg__marble"></div>' +
    '<div class="eurforex-marble-bg__glow"></div>' +
    '<div class="eurforex-marble-bg__orb eurforex-marble-bg__orb--1"></div>' +
    '<div class="eurforex-marble-bg__orb eurforex-marble-bg__orb--2"></div>' +
    '<div class="eurforex-marble-bg__frost"></div>';

  if (!document.body) {
    document.addEventListener(
      "DOMContentLoaded",
      function () {
        document.body.insertBefore(root, document.body.firstChild);
      },
      { once: true }
    );
    return;
  }
  document.body.insertBefore(root, document.body.firstChild);
})();
