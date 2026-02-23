const path = require('path');
const TerserPlugin = require('terser-webpack-plugin');

module.exports = (env, argv) => {
  const isProduction = argv.mode === 'production';

  return {
    entry: {
      'tracksure-admin': './src/index.tsx',
    },
    output: {
      path: path.resolve(__dirname, 'dist'),
      filename: '[name].js',
      chunkFilename: '[name].js', // Use consistent names for WordPress enqueue (no hash)
      clean: true,
      publicPath: 'auto',
    },
    resolve: {
      extensions: ['.ts', '.tsx', '.js', '.jsx'],
      alias: {
        '@': path.resolve(__dirname, 'src'),
      },
    },
    module: {
      rules: [
        {
          test: /\.tsx?$/,
          use: {
            loader: 'ts-loader',
            options: {
              transpileOnly: true, // Disabled type checking for faster builds (errors shown in IDE)
              configFile: path.resolve(__dirname, 'tsconfig.json'),
            },
          },
          exclude: /node_modules/,
        },
        {
          test: /\.css$/,
          use: ['style-loader', 'css-loader'],
        },
      ],
    },
    externals: {
      react: 'React',
      'react-dom': 'ReactDOM',
      '@wordpress/i18n': 'wp.i18n',
    },
    optimization: {
      minimize: isProduction,
      minimizer: isProduction ? [
        new TerserPlugin({
          terserOptions: {
            compress: {
              drop_console: true,
            },
          },
        }),
      ] : [],
      usedExports: true,
      sideEffects: false,
      moduleIds: 'deterministic',
      runtimeChunk: 'single',
      splitChunks: {
        chunks: 'all',
        maxInitialRequests: 10,
        maxAsyncRequests: 10,
        cacheGroups: {
          // React Router DOM (used immediately)
          reactRouter: {
            test: /[\\/]node_modules[\\/](react-router|react-router-dom)[\\/]/,
            name: 'react-router',
            priority: 30,
            enforce: true,
          },
          // Recharts (large charting library) - lazy load with pages
          recharts: {
            test: /[\\/]node_modules[\\/]recharts[\\/]/,
            name: 'recharts',
            priority: 20,
            enforce: true,
          },
          // Lucide React icons (tree-shaken, keep separate)
          lucideReact: {
            test: /[\\/]node_modules[\\/]lucide-react[\\/]/,
            name: 'lucide',
            priority: 25,
            enforce: true,
          },
          // Core vendors (react-query, axios, etc.)
          vendors: {
            test: /[\\/]node_modules[\\/](?!recharts|lucide-react|react-router)/,
            name: 'vendors',
            priority: 10,
            enforce: true,
          },
          // Common code used across routes
          common: {
            minChunks: 2,
            priority: 5,
            reuseExistingChunk: true,
            name: 'common',
          },
        },
      },
    },
    performance: {
      maxEntrypointSize: 1000000, // 1MB - Allow for split chunks strategy
      maxAssetSize: 500000, // 500KB - Individual chunks can be larger
      hints: isProduction ? 'warning' : false,
    },
    devtool: isProduction ? 'source-map' : 'eval-source-map',
  };
};
