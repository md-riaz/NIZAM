/**
 * Nizam Core React Theme Configuration
 * 
 * To be used with Tailwind v4 or a CSS-in-JS library.
 */

export const NizamTheme = {
  colors: {
    primary: {
      DEFAULT: 'var(--nizam-primary)',
      container: 'var(--nizam-primary-container)',
      foreground: 'var(--nizam-on-primary)',
    },
    background: {
      DEFAULT: 'var(--nizam-background)',
      foreground: 'var(--nizam-on-background)',
    },
    surface: {
      DEFAULT: 'var(--nizam-surface)',
      dim: 'var(--nizam-surface-dim)',
      bright: 'var(--nizam-surface-bright)',
      lowest: 'var(--nizam-surface-container-lowest)',
      low: 'var(--nizam-surface-container-low)',
      container: 'var(--nizam-surface-container)',
      high: 'var(--nizam-surface-container-high)',
      highest: 'var(--nizam-surface-container-highest)',
    },
    accent: {
      teal: 'var(--nizam-accent-teal)',
      purple: 'var(--nizam-accent-purple)',
      amber: 'var(--nizam-accent-amber)',
    },
    outline: {
      DEFAULT: 'var(--nizam-outline)',
      variant: 'var(--nizam-outline-variant)',
    },
    error: 'var(--nizam-error)',
  },
  fontFamily: {
    sans: 'var(--nizam-font-sans)',
    mono: 'var(--nizam-font-mono)',
  },
  borderRadius: {
    md: 'var(--nizam-radius-md)',
    xl: 'var(--nizam-radius-xl)',
  },
  boxShadow: {
    precision: 'var(--nizam-shadow-ambient)',
  },
};

export default NizamTheme;
