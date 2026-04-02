/**
 * Communications platform React theme configuration.
 */

export const CommunicationsTheme = {
  colors: {
    primary: {
      DEFAULT: 'var(--communications-primary)',
      container: 'var(--communications-primary-container)',
      foreground: 'var(--communications-on-primary)',
    },
    background: {
      DEFAULT: 'var(--communications-background)',
      foreground: 'var(--communications-on-background)',
    },
    surface: {
      DEFAULT: 'var(--communications-surface)',
      dim: 'var(--communications-surface-dim)',
      bright: 'var(--communications-surface-bright)',
      lowest: 'var(--communications-surface-container-lowest)',
      low: 'var(--communications-surface-container-low)',
      container: 'var(--communications-surface-container)',
      high: 'var(--communications-surface-container-high)',
      highest: 'var(--communications-surface-container-highest)',
    },
    accent: {
      teal: 'var(--communications-accent-teal)',
      purple: 'var(--communications-accent-purple)',
      amber: 'var(--communications-accent-amber)',
    },
    outline: {
      DEFAULT: 'var(--communications-outline)',
      variant: 'var(--communications-outline-variant)',
    },
    error: 'var(--communications-error)',
  },
  fontFamily: {
    sans: 'var(--communications-font-sans)',
    mono: 'var(--communications-font-mono)',
  },
  borderRadius: {
    md: 'var(--communications-radius-md)',
    xl: 'var(--communications-radius-xl)',
  },
  boxShadow: {
    platform: 'var(--communications-shadow-ambient)',
  },
};

export default CommunicationsTheme;
