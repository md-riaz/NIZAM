# Accessibility Checklist

This checklist ensures all UI components meet WCAG 2.1 AA standards for the VoIP Admin Platform.

## Pre-Deployment Checklist

Use this checklist before shipping any new UI component or page.

### ✓ Visual Quality

- [ ] No emojis used as icons (use Lucide React SVG icons)
- [ ] All icons from consistent icon set (Lucide React)
- [ ] Hover states don't cause layout shift
- [ ] Colors from design system (no hardcoded hex values)
- [ ] Text has sufficient contrast (4.5:1 minimum)

### ✓ Interaction

- [ ] All clickable elements have `cursor-pointer` class
- [ ] Hover states provide clear visual feedback
- [ ] Transitions are smooth (150-300ms)
- [ ] Focus states visible with ring
- [ ] Disabled states clearly indicated

### ✓ Accessibility (CRITICAL)

#### Keyboard Navigation
- [ ] All interactive elements reachable via Tab
- [ ] Tab order matches visual order
- [ ] Enter/Space activates buttons
- [ ] Escape closes dialogs/modals
- [ ] No keyboard traps

#### ARIA & Semantics
- [ ] Icon-only buttons have `aria-label`
- [ ] Loading spinners have `aria-label`
- [ ] Form inputs have associated labels
- [ ] Error messages have `role="alert"`
- [ ] Status updates use `aria-live="polite"`

#### Color & Contrast
- [ ] Color contrast meets 4.5:1 for normal text
- [ ] Color contrast meets 3:1 for large text (18px+)
- [ ] Color is not the only indicator (use icons + text)
- [ ] Links distinguishable from regular text

#### Motion & Animation
- [ ] All animations use `motion-safe:` prefix
- [ ] Animations respect `prefers-reduced-motion`
- [ ] No auto-playing videos or carousels
- [ ] Animations can be paused/stopped

#### Touch & Mobile
- [ ] Minimum touch target size: 44×44px
- [ ] Adequate spacing between touch targets (8px minimum)
- [ ] No hover-only interactions
- [ ] Gestures have keyboard alternatives

### ✓ Responsive Design

- [ ] Works at 375px width (iPhone SE)
- [ ] Works at 768px width (iPad)
- [ ] Works at 1024px width (Desktop)
- [ ] Works at 1440px+ width (Large desktop)
- [ ] No horizontal scroll at any breakpoint
- [ ] Text remains readable at all sizes
- [ ] Images scale appropriately

### ✓ Performance

- [ ] No layout shift during loading (CLS < 0.1)
- [ ] Animations use `transform` and `opacity` only
- [ ] Images optimized and lazy loaded
- [ ] Loading states appear immediately
- [ ] No blocking JavaScript

---

## Component-Specific Checklists

### Buttons

```tsx
// ✓ Accessible Button
<Button 
  variant="ghost" 
  size="sm" 
  onClick={handleClick}
  disabled={isLoading}
  aria-label="Refresh data"
  className="cursor-pointer min-h-touch min-w-touch"
>
  <RefreshCw className="size-4" />
</Button>
```

**Checklist:**
- [ ] Has `aria-label` if icon-only
- [ ] Has `cursor-pointer` class
- [ ] Minimum 44×44px touch target
- [ ] Disabled state clearly visible
- [ ] Focus ring visible

### Loading States

```tsx
// ✓ Accessible Loading Spinner
<div 
  className="size-6 motion-safe:animate-spin rounded-full border-2 border-primary border-t-transparent" 
  role="status"
  aria-label="Loading profiles"
/>
```

**Checklist:**
- [ ] Has `aria-label` describing what's loading
- [ ] Uses `motion-safe:` prefix
- [ ] Has `role="status"` for screen readers
- [ ] Appears immediately (no delay)

### Forms

```tsx
// ✓ Accessible Form Field
<div>
  <Label htmlFor="email">Email Address</Label>
  <Input 
    id="email"
    type="email"
    aria-describedby="email-error"
    aria-invalid={!!errors.email}
  />
  {errors.email && (
    <p id="email-error" role="alert" className="text-destructive text-sm">
      {errors.email.message}
    </p>
  )}
</div>
```

**Checklist:**
- [ ] Label associated with input via `htmlFor`
- [ ] Error messages have `role="alert"`
- [ ] Input has `aria-describedby` pointing to error
- [ ] Input has `aria-invalid` when error exists
- [ ] Required fields marked with `required` attribute

### Tables

```tsx
// ✓ Accessible Table
<Table>
  <TableHeader>
    <TableRow>
      <TableHead scope="col">Status</TableHead>
      <TableHead scope="col">Name</TableHead>
    </TableRow>
  </TableHeader>
  <TableBody>
    <TableRow className="hover:bg-muted/50">
      <TableCell>...</TableCell>
    </TableRow>
  </TableBody>
</Table>
```

**Checklist:**
- [ ] Uses semantic table elements
- [ ] Headers have `scope="col"`
- [ ] Row headers have `scope="row"` if applicable
- [ ] Caption provided if needed
- [ ] Responsive alternative for mobile (cards)

### Dialogs/Modals

```tsx
// ✓ Accessible Dialog
<AlertDialog open={isOpen} onOpenChange={setIsOpen}>
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

**Checklist:**
- [ ] Focus trapped within dialog
- [ ] Escape key closes dialog
- [ ] Focus returns to trigger on close
- [ ] Has accessible title
- [ ] Has accessible description
- [ ] Backdrop prevents interaction with page

### Status Indicators

```tsx
// ✓ Accessible Status Indicator
<div className="flex items-center gap-2">
  <CheckCircle className="size-4 text-green-600" aria-hidden="true" />
  <Badge variant="success">Running</Badge>
  <span className="sr-only">Status: Running</span>
</div>
```

**Checklist:**
- [ ] Icon has `aria-hidden="true"`
- [ ] Text label provided
- [ ] Screen reader text with `sr-only` if needed
- [ ] Color + icon + text (not color alone)

---

## Testing Tools

### Automated Testing

1. **Lighthouse** (Chrome DevTools)
   - Run accessibility audit
   - Target score: 95+

2. **axe DevTools** (Browser Extension)
   - Scan for WCAG violations
   - Fix all critical issues

3. **WAVE** (Browser Extension)
   - Visual feedback on accessibility
   - Check contrast ratios

### Manual Testing

1. **Keyboard Navigation**
   - Unplug mouse
   - Navigate entire page with keyboard
   - Verify all functionality accessible

2. **Screen Reader Testing**
   - **Windows**: NVDA (free)
   - **macOS**: VoiceOver (built-in)
   - Test critical user flows

3. **Zoom Testing**
   - Test at 200% zoom
   - Verify no horizontal scroll
   - Text remains readable

4. **Color Blindness**
   - Use browser extensions to simulate
   - Verify information not color-dependent

5. **Reduced Motion**
   - Enable in OS settings
   - Verify animations disabled/reduced

---

## Common Issues & Fixes

### Issue: Icon-only button without label

```tsx
// ✗ Bad
<Button variant="ghost" size="sm">
  <Trash2 className="size-4" />
</Button>

// ✓ Good
<Button 
  variant="ghost" 
  size="sm"
  aria-label="Delete item"
  className="cursor-pointer"
>
  <Trash2 className="size-4" />
</Button>
```

### Issue: Animation without reduced motion support

```tsx
// ✗ Bad
<div className="animate-spin" />

// ✓ Good
<div className="motion-safe:animate-spin" />
```

### Issue: Low contrast text

```tsx
// ✗ Bad - May not meet 4.5:1
<p className="text-gray-400">Secondary text</p>

// ✓ Good - Meets 4.5:1
<p className="text-muted-foreground">Secondary text</p>
```

### Issue: Missing loading state label

```tsx
// ✗ Bad
<div className="spinner" />

// ✓ Good
<div 
  className="spinner" 
  role="status"
  aria-label="Loading data"
/>
```

### Issue: Form without labels

```tsx
// ✗ Bad
<Input placeholder="Email" />

// ✓ Good
<div>
  <Label htmlFor="email">Email</Label>
  <Input id="email" type="email" />
</div>
```

---

## Resources

### Guidelines
- [WCAG 2.1 Quick Reference](https://www.w3.org/WAI/WCAG21/quickref/)
- [MDN Accessibility](https://developer.mozilla.org/en-US/docs/Web/Accessibility)
- [A11y Project Checklist](https://www.a11yproject.com/checklist/)

### Testing Tools
- [Lighthouse](https://developers.google.com/web/tools/lighthouse)
- [axe DevTools](https://www.deque.com/axe/devtools/)
- [WAVE](https://wave.webaim.org/extension/)
- [Contrast Checker](https://webaim.org/resources/contrastchecker/)

### Screen Readers
- [NVDA](https://www.nvaccess.org/) (Windows, free)
- [JAWS](https://www.freedomscientific.com/products/software/jaws/) (Windows, paid)
- VoiceOver (macOS/iOS, built-in)
- TalkBack (Android, built-in)

---

## Sign-off

Before deploying to production, the following must be verified:

- [ ] All items in Pre-Deployment Checklist completed
- [ ] Lighthouse accessibility score 95+
- [ ] No critical axe DevTools violations
- [ ] Keyboard navigation tested
- [ ] Screen reader tested (at least one)
- [ ] Responsive design verified
- [ ] Reduced motion tested

**Reviewed by:** _________________  
**Date:** _________________  
**Approved for deployment:** [ ] Yes [ ] No
