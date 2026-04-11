# Implementation Plan

## 1. Add required indicator to FormLabel
- Update `frontend/src/components/ui/form.tsx`
- We will modify `FormLabel` component to optionally show a red asterisk `*`
- We'll add a `required` prop to `FormLabel` type definition.

```tsx
const FormLabel = React.forwardRef<
  React.ElementRef<typeof LabelPrimitive.Root>,
  React.ComponentPropsWithoutRef<typeof LabelPrimitive.Root> & { required?: boolean }
>(({ className, required, ...props }, ref) => {
  const { error, formItemId } = useFormField()

  return (
    <Label
      ref={ref}
      className={cn(error && "text-destructive", className)}
      htmlFor={formItemId}
      {...props}
    >
        {props.children}
        {required && <span className="text-destructive ml-1">*</span>}
    </Label>
  )
})
```
Alternatively, since the user asks for it across the app, but specifically within `SipProfileFormPage.tsx`, we will update `SipProfileFormPage.tsx` to pass the `required` prop on the fields that are actually required (Name).

## 2. Separate WS and WSS enablement
In `frontend/src/pages/admin/SipProfileFormPage.tsx`:
- The user wants separate WS and WSS enablement. Currently `setWebRtcEnabled` does both `ws-binding` and `wss-binding` together along with all TLS stuff.
- We will split this into two toggles:
  1. **Enable WebRTC (WS)**
  2. **Enable Secure WebRTC (WSS/TLS)**

**Changes to constants:**
- Keep `WEBRTC_HIDDEN_SETTINGS` the same so all these stay hidden from the generic parameter list.
- We might want to split `WEBRTC_SETTING_FIELDS` and `WEBRTC_BOOLEAN_FIELDS` to indicate which are WS vs WSS.
  - WS setting: `ws-binding` (and maybe `enable-ice`, `dtls-srtp` - but `dtls-srtp` implies secure. Standard WebRTC requires secure/WSS for browsers usually, but they are using nginx proxy so they terminate TLS at nginx).
  - WSS/TLS settings: `wss-binding`, `tls-cert-dir`, `tls-sip-port`, `tls-version`, `tls-only`, `tls-verify-date`.
  - Let's add separate UI blocks or toggles.

Let's look at what we need to build for step 2.

### WebRTC Refactoring Details

The current implementation treats "WebRTC" as a single toggle linked to `wss-binding`, which sets everything.
We need:
1. `isWsEnabled` toggle: Controls only `ws-binding`.
2. `isWssEnabled` toggle: Controls `wss-binding` and TLS related settings.

Let's rethink `setWebRtcEnabled` and the UI.
We'll replace `isWebRtcEnabled` with:
- `isWsEnabled = Boolean(getSetting('ws-binding')?.is_enabled)`
- `isWssEnabled = Boolean(getSetting('wss-binding')?.is_enabled)`

We'll define two setter functions:
- `setWsEnabled(enabled: boolean)`
- `setWssEnabled(enabled: boolean)`

For `setWsEnabled`:
Managed settings: `ws-binding`, `enable-ice`.
*(Note: ICE is generally needed for both, so maybe we toggle `enable-ice` if either WS or WSS is enabled? No, wait. ICE is definitely needed for any WebRTC. Let's make `enable-ice` true if EITHER WS or WSS is enabled, and false only if BOTH are false, or just let users toggle it manually since they have a checkbox for it. Actually, previously it was all batched into `setWebRtcEnabled`. We can split the managed settings lists.)*

Wait, previously `setWebRtcEnabled` explicitly set ALL of these:
```typescript
const managedSettings = [
    'ws-binding',
    'wss-binding',
    'tls',
    'tls-bind-params',
    'tls-sip-port',
    'tls-cert-dir',
    'tls-version',
    'tls-verify-date',
    'tls-verify-policy',
    'tls-verify-depth',
    'dtls-srtp',
    'dtls-verify-policy',
    'enable-ice',
];
```

If we split:
**WS Managed Settings:**
- `ws-binding`
- `enable-ice` (shared, enabled if either is enabled)

**WSS Managed Settings:**
- `wss-binding`
- `tls`, `tls-bind-params`, `tls-sip-port`, `tls-cert-dir`, `tls-version`, `tls-verify-date`, `tls-verify-policy`, `tls-verify-depth`
- `dtls-srtp`, `dtls-verify-policy`
- `enable-ice` (shared)

Let's modify `setWebRtcEnabled` to handle both, perhaps renamed to `setTransportEnabled(transport: 'ws' | 'wss', enabled: boolean)`:
```typescript
const setTransportEnabled = (transport: 'ws' | 'wss', enabled: boolean) => {
    // Current states
    const otherTransport = transport === 'ws' ? 'wss' : 'ws';
    const otherEnabled = transport === 'ws' ? isWssEnabled : isWsEnabled;
    const eitherEnabled = enabled || otherEnabled;

    const wsSettings = ['ws-binding'];
    const wssSettings = [
        'wss-binding', 'tls', 'tls-bind-params', 'tls-sip-port', 'tls-cert-dir',
        'tls-version', 'tls-verify-date', 'tls-verify-policy', 'tls-verify-depth',
        'dtls-srtp', 'dtls-verify-policy'
    ];
    const sharedSettings = ['enable-ice'];

    const settingsToUpdate = [
        ...(transport === 'ws' ? wsSettings : wssSettings).map(name => ({ name, is_enabled: enabled })),
        ...sharedSettings.map(name => ({ name, is_enabled: eitherEnabled }))
    ];

    settingsToUpdate.forEach(({ name, is_enabled }) => {
        upsertSettingByName(name, (current) => ({
            id: current?.id,
            name,
            value: current?.value ?? getDefaultValueForSetting(name),
            is_enabled,
            description: current?.description ?? null,
        }));
    });
};
```
Also `getDefaultValueForSetting(name)` will just extract that big ternary operator into a helper.

For the UI, we'll split the checkboxes:
```tsx
<div className="flex items-start justify-between gap-4 rounded-lg border p-4">
    <div className="space-y-1">
        <FormLabel>Enable Unencrypted WebRTC (WS)</FormLabel>
        <p className="text-sm text-muted-foreground">
            Turns on WS transport. Use this if proxying WebRTC via NGINX.
        </p>
    </div>
    <Checkbox
        checked={isWsEnabled}
        onCheckedChange={(checked) => setTransportEnabled('ws', checked === true)}
    />
</div>

<div className="flex items-start justify-between gap-4 rounded-lg border p-4">
    <div className="space-y-1">
        <FormLabel>Enable Secure WebRTC (WSS)</FormLabel>
        <p className="text-sm text-muted-foreground">
            Turns on WSS, DTLS-SRTP, and TLS-related settings.
        </p>
    </div>
    <Checkbox
        checked={isWssEnabled}
        onCheckedChange={(checked) => setTransportEnabled('wss', checked === true)}
    />
</div>
```

Then we update `WEBRTC_SETTING_FIELDS` and `WEBRTC_BOOLEAN_FIELDS` inputs to disable based on which is active.
`ws-binding` is disabled if `!isWsEnabled`.
All other WSS fields are disabled if `!isWssEnabled`.
`enable-ice` is disabled if `!isWsEnabled && !isWssEnabled`.


## Detailed File Changes

### 1. `frontend/src/components/ui/form.tsx`
Add `required` to `FormLabel` prop types and conditionally render the asterisk.

### 2. `frontend/src/pages/admin/SipProfileFormPage.tsx`
- Add the `required` prop to the `Name` FormLabel.
- Refactor the WebRTC enablement logic:
  - Extract the default value logic to a helper `getDefaultWebRtcValue(name: string)`.
  - Replace `isWebRtcEnabled` with `isWsEnabled` and `isWssEnabled`.
  - Replace `setWebRtcEnabled` with `setTransportEnabled(transport: 'ws' | 'wss', enabled: boolean)`.
  - Update `setWebRtcTextSetting` and `setWebRtcBooleanSetting` to keep `is_enabled` as is (instead of blindly falling back to `isWebRtcEnabled`).
  - Update the UI to render two separate enable toggles for WS and WSS.
  - Update `disabled` logic for the individual WebRTC fields so that they are disabled only when their respective transport is disabled (e.g. `ws-binding` depends on `isWsEnabled`, TLS fields depend on `isWssEnabled`, `enable-ice` depends on `isWsEnabled || isWssEnabled`).

### Critical Files for Implementation
- frontend/src/components/ui/form.tsx
- frontend/src/pages/admin/SipProfileFormPage.tsx

