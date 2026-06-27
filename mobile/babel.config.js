module.exports = function (api) {
  api.cache(true);
  return {
    presets: [
      ['babel-preset-expo', { jsxImportSource: 'nativewind' }],
      'nativewind/babel',
    ],
    // Reanimated 4 worklets (used by the launch splash animation). MUST be the
    // last plugin in the list.
    plugins: ['react-native-worklets/plugin'],
  };
};
