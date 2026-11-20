import vue from 'eslint-plugin-vue';
import tseslint from 'typescript-eslint';

const spacing = {
  'padding-line-between-statements': [
    'error',
    { blankLine: 'always', prev: '*', next: 'function' },
    { blankLine: 'always', prev: 'function', next: '*' },
    { blankLine: 'always', prev: ['const', 'let'], next: '*' },
    { blankLine: 'any', prev: ['const', 'let'], next: ['const', 'let'] },
    { blankLine: 'always', prev: '*', next: ['return', 'if', 'try', 'switch', 'for', 'while'] },
    { blankLine: 'always', prev: 'if', next: '*' },
    { blankLine: 'always', prev: 'block-like', next: '*' },
  ],
};

export default [
  { ignores: ['node_modules/**', 'dist/**'] },
  {
    files: ['**/*.{js,mjs,ts}'],
    languageOptions: { parser: tseslint.parser },
    rules: spacing,
  },
  ...vue.configs['flat/base'],
  {
    files: ['**/*.vue'],
    languageOptions: { parserOptions: { parser: tseslint.parser } },
    rules: {
      ...spacing,
      'vue/singleline-html-element-content-newline': [
        'error',
        { ignoreWhenNoAttributes: false, ignoreWhenEmpty: false, ignores: [], externalIgnores: [] },
      ],
      'vue/multiline-html-element-content-newline': [
        'error',
        { ignoreWhenEmpty: false, allowEmptyLines: false, ignores: [] },
      ],
      'vue/max-attributes-per-line': ['error', { singleline: 1, multiline: 1 }],
      'vue/html-indent': ['error', 2],
      'vue/padding-line-between-blocks': ['error', 'always'],
      'vue/no-restricted-syntax': [
        'error',
        {
          selector: 'VExpressionContainer ConditionalExpression',
          message: 'Move derived values into computed properties.',
        },
      ],
    },
  },
];
