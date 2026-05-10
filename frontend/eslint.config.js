import { createWebHatcheryEslintConfig } from '../../tools/shared/frontend/eslint.config.js';

export default createWebHatcheryEslintConfig({
  tsconfigRootDir: import.meta.dirname,
});
