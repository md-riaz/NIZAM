# VoIP Admin Platform - Design System

## Overview

This design system defines the visual language, components, and patterns for the VoIP Admin Platform - an enterprise SaaS dashboard for telecommunications management.

## Product Type

**Enterprise SaaS Dashboard** - Technical/Data-Dense Interface
- Real-time monitoring and management
- High information density
- Professional technical audience
- Mission-critical operations

## Design Principles

1. **Clarity Over Decoration** - Information must be immediately scannable
2. **Consistency** - Predictable patterns reduce cognitive load
3. **Accessibility First** - WCAG 2.1 AA compliance minimum
4. **Performance** - Fast, responsive, real-time updates
5. **Trust** - Professional appearance for enterprise users

---

## Color System

### Primary Palette

```css
/* Primary - Technology & Trust */
--primary: 217 91% 60%;           /* Blue-500 */
--primary-foreground: 0 0% 100%;

/* Success - Active/Registered States */
--success: 142 76% 36%;           /* Green-600 */
--success-foreground: 0 0% 100%;

/* Warning - Degraded/Attention States */
--warning: 38 92% 50%;            /* Amber-500 */
--warning-foreground: 0 0% 0%;

/* Destructive - Error/Failed States */
--destructive: 0 84% 60%;         /* Red-500 */
--destructive-foreground: 0 0% 100%;
```

### Neutral Palette

```css
/* Background & Surface */
--background: 0 0% 100%;          /* White */
--foreground: 222 47% 11%;        /* Slate-900 */

--card: 0 0% 100%;
--card-foreground: 222 47% 11%;

--muted: 210 40% 96%;             /* Slate-100 */
--muted-foreground: 215 16% 47%;  /* Slate-600 */

/* Borders */
--border: 214 32% 91%;            /* Slate-200 */
--input: 214 32% 91%;
```

### Status Colors

| Status | Color | Usage |
|--------|-------|-------|
| Running/Active | Green-600 | SIP profiles running, gateways registered |
| Degraded | Amber-500 | Partial failures, warnings |
| Failed/Error | Red-600 | Connection failures, errors |
| Inactive | Slate-400 | Stopped services, no registration |
| Unknown | Slate-500 | Unknown states |

### Color Contrast Requirements

All text must meet WCAG 2.1 AA standards:
- Normal text (16px): 4.5:1 minimum
- Large text (18px+): 3:1 minimum
- UI components: 3:1 minimum

**Approved Combinations:**
- `text-foreground` on `bg-background` ✓ (15:1)
- `text-muted-foreground` on `bg-background` ✓ (4.6:1)
- `text-primary-foreground` on `bg-primary` ✓ (8:1)

---

## Typography

### Font Family

```css
/* Primary Font */
font-family: 'Inter Variable', system-ui, -apple-system, sans-serif;

/* Monospace - Technical Data */
font-family: 'JetBrains Mono', 'Fira Code', 'Consolas', monospace;
```

### Type Scale

| Element | Size | Weight | Line Height | Usage |
|---------|------|--------|-------------|-------|
| H1 | 30px (text-2xl) | 700 | 1.2 | Page titles |
| H2 | 24px (text-xl) | 600 | 1.3 | Section headers |
| H3 | 20px (text-lg) | 600 | 1.4 | Card titles |
| Body | 16px (text-base) | 400 | 1.5-1.75 | Main content |
| Small | 14px (text-sm) | 400 | 1.5 | Secondary text |
| Tiny | 12px (text-xs) | 400 | 1.5 | Labels, metadata |

### Typography Rules

1. **Body Text**: Always use `leading-relaxed` (1.625) or `leading-loose` (2)
2. **Headings**: Use `tracking-tight` for large headings
3. **Technical Data**: Use monospace font for IPs, UUIDs, logs
4. **Minimum Size**: Never go below 14px for body text
5. **Line Length**: Maximum 75 characters per line for readability

---

## Spacing System

Based on Tailwind's 4px scale:

```
0.5 = 2px   (tight spacing)
1   = 4px   (minimal)
2   = 8px   (compact)
3   = 12px  (default)
4   = 16px  (comfortable)
6   = 24px  (spacious)
8   = 32px  (section spacing)
12  = 48px  (major sections)
```

### Component Spacing

- **Card padding**: `p-6` (24px)
- **Button padding**: `px-4 py-2` (16px × 8px)
- **Table cell padding**: `px-4 py-3` (16px × 12px)
- **Section spacing**: `space-y-6` (24px)
- **Page padding**: `p-6 lg:p-8` (24px → 32px)

---

## Components

### Buttons

**Variants:**
- `default` - Primary actions (blue background)
- `outline` - Secondary actions (border only)
- `ghost` - Tertiary actions (no border)
- `destructive` - Dangerous actions (red)

**Sizes:**
- `sm` - Icon buttons, compact UI (h-9)
- `default` - Standard buttons (h-10)
- `lg` - Prominent CTAs (h-11)

**Accessibility:**
- All icon-only buttons MUST have `aria-label`
- Interactive buttons MUST have `cursor-pointer`
- Minimum touch target: 44×44px
- Focus visible with ring

```tsx
// ✓ Good
<Button 
  variant="ghost" 
  size="sm" 
  aria-label="Refresh data"
  className="cursor-pointer"
>
  <RefreshCw className="size-4" />
</Button>

// ✗ Bad - Missing aria-label
<Button variant="ghost" size="sm">
  <RefreshCw className="size-4" />
</Button>
```

### Badges

**Variants:**
- `success` - Green for active/running states
- `destructive` - Red for errors/failures
- `warning` - Amber for degraded states
- `secondary` - Gray for inactive/unknown
- `default` - Blue for informational

```tsx
<Badge variant="success">REGED</Badge>
<Badge variant="destructive">FAILED</Badge>
<Badge variant="warning">DEGRADED</Badge>
```

### Status Indicators

Always combine icon + badge for status:

```tsx
<div className="flex items-center gap-2">
  <CheckCircle className="size-4 text-green-600" />
  <Badge variant="success">Running</Badge>
</div>
```

### Tables

**Structure:**
- Use semantic table elements
- Sticky headers for long tables
- Hover states on rows
- Responsive: Consider card layout on mobile

**Styling:**
```tsx
<Table>
  <TableHeader>
    <TableRow>
      <TableHead>Status</TableHead>
      <TableHead>Name</TableHead>
    </TableRow>
  </TableHeader>
  <TableBody>
    <TableRow className="hover:bg-muted/50">
      <TableCell>...</TableCell>
    </TableRow>
  </TableBody>
</Table>
```

### Cards

**Structure:**
```tsx
<Card>
  <CardHeader>
    <CardTitle>Title</CardTitle>
    <CardDescription>Description</CardDescription>
  </CardHeader>
  <CardContent>
    {/* Content */}
  </CardContent>
</Card>
```

**Spacing:**
- Header padding: `p-6`
- Content padding: `p-6`
- Between sections: `space-y-4`

---

## Interaction Patterns

### Loading States

**Spinner:**
```tsx
<div className="size-6 motion-safe:animate-spin rounded-full border-2 border-primary border-t-transparent" 
     aria-label="Loading data" />
```

**Rules:**
- Always use `motion-safe:` prefix for animations
- Include `aria-label` for screen readers
- Show loading state immediately (no delay)
- Disable actions during loading

### Confirmation Dialogs

For destructive actions:
```tsx
<AlertDialog>
  <AlertDialogContent>
    <AlertDialogHeader>
      <AlertDialogTitle>Confirm Action</AlertDialogTitle>
      <AlertDialogDescription>
        This action cannot be undone.
      </AlertDialogDescription>
    </AlertDialogHeader>
    <AlertDialogFooter>
      <AlertDialogCancel>Cancel</AlertDialogCancel>
      <AlertDialogAction>Confirm</AlertDialogAction>
    </AlertDialogFooter>
  </AlertDialogContent>
</AlertDialog>
```

### Auto-Refresh

For real-time data:
```tsx
useQuery({
  queryKey: ['data'],
  queryFn: fetchData,
  refetchInterval: 10000, // 10 seconds
});
```

**Rules:**
- Use 10s intervals for status monitoring
- Show refresh button for manual updates
- Indicate when data was last updated
- Pause refresh when user is interacting

---

## Accessibility Guidelines

### WCAG 2.1 AA Compliance

**Required:**
1. ✓ Color contrast 4.5:1 for text
2. ✓ Focus indicators visible
3. ✓ Keyboard navigation support
4. ✓ ARIA labels on icon buttons
5. ✓ Alt text on images
6. ✓ Form labels associated
7. ✓ Reduced motion support

### Keyboard Navigation

- `Tab` - Navigate forward
- `Shift+Tab` - Navigate backward
- `Enter/Space` - Activate buttons
- `Escape` - Close dialogs
- `Arrow keys` - Navigate lists/tabs

### Screen Reader Support

```tsx
// Loading states
<div aria-label="Loading profiles" />

// Icon buttons
<Button aria-label="Delete item" />

// Status updates
<div role="status" aria-live="polite">
  Data updated
</div>
```

### Motion Preferences

Always respect `prefers-reduced-motion`:

```tsx
// ✓ Good
className="motion-safe:animate-spin"

// ✗ Bad
className="animate-spin"
```

---

## Responsive Design

### Breakpoints

```css
sm: 640px   /* Mobile landscape */
md: 768px   /* Tablet */
lg: 1024px  /* Desktop */
xl: 1280px  /* Large desktop */
2xl: 1536px /* Extra large */
```

### Mobile-First Approach

```tsx
// Base: Mobile
<div className="p-4 lg:p-8">

// Tablet and up
<div className="grid grid-cols-1 md:grid-cols-2">

// Desktop and up
<div className="hidden lg:block">
```

### Responsive Tables

Consider card layout on mobile:
```tsx
<div className="hidden md:block">
  <Table>...</Table>
</div>
<div className="md:hidden space-y-4">
  {items.map(item => (
    <Card key={item.id}>...</Card>
  ))}
</div>
```

---

## Performance Guidelines

### Image Optimization

- Use WebP format
- Implement lazy loading
- Provide srcset for responsive images
- Reserve space to prevent layout shift

### Animation Performance

- Use `transform` and `opacity` only
- Avoid animating `width`, `height`, `top`, `left`
- Keep animations under 300ms
- Use `will-change` sparingly

### Bundle Size

- Code split by route
- Lazy load heavy components
- Tree-shake unused code
- Monitor bundle size in CI

---

## Implementation Checklist

Before shipping any UI component:

### Visual Quality
- [ ] No emojis as icons (use Lucide React)
- [ ] Consistent icon set throughout
- [ ] Hover states don't cause layout shift
- [ ] Colors from design system

### Interaction
- [ ] All clickable elements have `cursor-pointer`
- [ ] Hover states provide visual feedback
- [ ] Transitions are smooth (150-300ms)
- [ ] Focus states visible

### Accessibility
- [ ] Icon-only buttons have `aria-label`
- [ ] Color contrast meets 4.5:1
- [ ] Keyboard navigation works
- [ ] `motion-safe:` prefix on animations
- [ ] Loading states have `aria-label`

### Responsive
- [ ] Works at 375px (mobile)
- [ ] Works at 768px (tablet)
- [ ] Works at 1440px (desktop)
- [ ] No horizontal scroll

### Performance
- [ ] No layout shift during loading
- [ ] Animations use transform/opacity
- [ ] Images optimized and lazy loaded

---

## Resources

### Icons
- **Lucide React** - https://lucide.dev
- Consistent 24×24 viewBox
- Use `size-4` (16px) for inline icons
- Use `size-5` (20px) for emphasis

### Components
- **shadcn/ui** - https://ui.shadcn.com
- Built on Radix UI primitives
- Fully accessible by default
- Customizable with Tailwind

### Tools
- **Contrast Checker** - https://webaim.org/resources/contrastchecker/
- **WAVE** - Browser extension for accessibility testing
- **Lighthouse** - Chrome DevTools for performance

---

## Changelog

### 2025-03-30
- Initial design system documentation
- Added accessibility guidelines
- Defined color system and typography
- Documented component patterns
