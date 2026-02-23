module.exports = {
  root: true,
  env: {
    browser: true,
    es2021: true,
    node: true,
  },
  extends: [
    'eslint:recommended',
    'plugin:react/recommended',
    'plugin:react-hooks/recommended',
    'plugin:@typescript-eslint/recommended',
    'plugin:security/recommended',
  ],
  parser: '@typescript-eslint/parser',
  parserOptions: {
    ecmaFeatures: {
      jsx: true,
    },
    ecmaVersion: 'latest',
    sourceType: 'module',
  },
  plugins: [
    'react',
    'react-hooks',
    '@typescript-eslint',
    'security',
  ],
  settings: {
    react: {
      version: 'detect',
    },
  },
  rules: {
    // ========================================
    // Security rules
    // ========================================
    // detect-object-injection: OFF — This rule is designed for Node.js server
    // code where user input as object keys could cause prototype pollution.
    // In a browser-only React SPA, all dynamic keys come from controlled
    // sources (API responses, config objects). This rule produces ~80 false
    // positives in client-side code. See: https://github.com/eslint-community/eslint-plugin-security/issues/21
    'security/detect-object-injection': 'off',
    'security/detect-non-literal-regexp': 'warn',
    'security/detect-unsafe-regex': 'error',
    'security/detect-buffer-noassert': 'error',
    'security/detect-eval-with-expression': 'error',
    'security/detect-no-csrf-before-method-override': 'error',
    'security/detect-possible-timing-attacks': 'warn',
    
    // ========================================
    // React rules
    // ========================================
    'react/react-in-jsx-scope': 'off', // Not needed in React 18+
    'react/prop-types': 'off', // Using TypeScript for prop validation
    'react/no-unescaped-entities': 'error',
    'react-hooks/rules-of-hooks': 'error',
    'react-hooks/exhaustive-deps': 'warn',
    
    // ========================================
    // TypeScript rules
    // ========================================
    '@typescript-eslint/no-explicit-any': 'warn',
    '@typescript-eslint/no-unused-vars': ['error', { 
      argsIgnorePattern: '^_',
      varsIgnorePattern: '^_',
    }],
    
    // ========================================
    // General code quality
    // ========================================
    'no-console': ['warn', { allow: ['warn', 'error'] }],
    'no-debugger': 'error',
    // no-alert: OFF — WordPress admin plugins commonly use confirm() for
    // destructive actions (delete, bulk operations). WordPress core itself
    // uses confirm() dialogs. This is standard WP admin UX pattern.
    'no-alert': 'off',
    'prefer-const': 'error',
    'no-var': 'error',
    'eqeqeq': ['error', 'always'],
    'curly': ['error', 'all'],
    'no-eval': 'error',
    'no-implied-eval': 'error',
    'no-new-func': 'error',
    'no-script-url': 'error',
  },
  overrides: [
    {
      files: ['*.js'],
      rules: {
        '@typescript-eslint/no-var-requires': 'off',
      },
    },
    // Type declaration files need flexible typing for 3rd-party globals
    {
      files: ['*.d.ts'],
      rules: {
        '@typescript-eslint/no-explicit-any': 'off',
      },
    },
    // Example files are documentation, not production code
    {
      files: ['**/examples/**'],
      rules: {
        '@typescript-eslint/no-unused-vars': 'off',
        'no-console': 'off',
      },
    },
  ],
};
