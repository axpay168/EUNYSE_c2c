module.exports = {
  configureWebpack: {
    performance: {
      hints: 'warning',
      maxAssetSize: 950 * 1024,
      maxEntrypointSize: 1200 * 1024
    }
  },
  devServer: {
    host: '0.0.0.0',
    allowedHosts: 'all'
  },
  chainWebpack: config => {
    if (config.plugins.has('prefetch')) {
      config.plugins.delete('prefetch')
    }
    if (config.plugins.has('preload')) {
      config.plugins.delete('preload')
    }
    config.module
      .rule('js')
      .use('babel-loader')
      .tap(options => ({
        ...options,
        compact: true
      }))
    config.module
      .rule('vue')
      .use('vue-loader')
      .loader('vue-loader')
      .tap(options => {
        options.optimizeSSR = false
        options.compilerOptions = { preserveWhitespace: false }
        return options
      })
  }
}
