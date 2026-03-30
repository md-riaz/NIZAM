# UI/UX Quick Reference

Quick copy-paste patterns for common UI components following the VoIP Admin Platform design system.

## Status Indicators

### With Icon + Badge
```tsx
<div className="flex items-center gap-2">
  <CheckCircle className="size-4 text-green-600" aria-hidden="true" />
  <Badge variant="success">Running</Badge>
</div>
```

### Status Helper Function
```tsx
const getStatusBadge = (status: string) => {
  const statusLower = status.toLowerCase();
  
  if (statusLower === 'running' || statusLower.includes('reged')) {
    return <Badge variant="success">{status}</Badge>;
  }
  if (statusLower.includes('fail') || statusLower.includes('error')) {
    return <Badge variant="destructive">{status}</Badge>;
  }
  if (statusLower === 'degraded' || statusLower.includes('warn')) {
    return <Badge variant="warning">{status}</Badge>;
  }
  return <Badge variant="secondary">{status}</Badge>;
};
```

## Buttons

### Icon-Only Button
```tsx
<Button 
  variant="ghost" 
  size="sm"
  onClick={handleClick}
  aria-label="Refresh data"
  className="cursor-pointer"
>
  <RefreshCw className="size-4" />
</Button>
```

### Button with Icon + Text
```tsx
<Button 
  variant="outline" 
  onClick={handleDownload}
  className="cursor-pointer"
>
  <Download className="mr-2 size-4" />
  Download
</Button>
```

### Loading Button
```tsx
<Button 
  onClick={handleSubmit}
  disabled={isLoading}
  className="cursor-pointer"
>
  {isLoading && (
    <div className="mr-2 size-4 motion-safe:animate-spin rounded-full border-2 border-current border-t-transparent" />
  )}
  {isLoading ? 'Saving...' : 'Save'}
</Button>
```

## Loading States

### Spinner
```tsx
<div className="flex h-32 items-center justify-center">
  <div 
    className="size-6 motion-safe:animate-spin rounded-full border-2 border-primary border-t-transparent" 
    role="status"
    aria-label="Loading data"
  />
</div>
```

### Inline Spinner
```tsx
<div className="size-4 motion-safe:animate-spin rounded-full border-2 border-current border-t-transparent" />
```

## Cards

### Basic Card
```tsx
<Card>
  <CardHeader>
    <CardTitle>Title</CardTitle>
    <CardDescription>Description text</CardDescription>
  </CardHeader>
  <CardContent>
    {/* Content */}
  </CardContent>
</Card>
```

### Card with Header Actions
```tsx
<Card>
  <CardHeader>
    <div className="flex items-center justify-between">
      <div>
        <CardTitle>Title</CardTitle>
        <CardDescription>Description</CardDescription>
      </div>
      <Button 
        variant="outline" 
        size="sm"
        onClick={handleRefresh}
        aria-label="Refresh"
        className="cursor-pointer"
      >
        <RefreshCw className="size-4" />
      </Button>
    </div>
  </CardHeader>
  <CardContent>
    {/* Content */}
  </CardContent>
</Card>
```

## Tables

### Basic Table
```tsx
<Table>
  <TableHeader>
    <TableRow>
      <TableHead>Name</TableHead>
      <TableHead>Status</TableHead>
      <TableHead>Actions</TableHead>
    </TableRow>
  </TableHeader>
  <TableBody>
    {items.map((item) => (
      <TableRow key={item.id} className="hover:bg-muted/50">
        <TableCell className="font-medium">{item.name}</TableCell>
        <TableCell>{getStatusBadge(item.status)}</TableCell>
        <TableCell>
          <Button 
            variant="ghost" 
            size="sm"
            onClick={() => handleDelete(item.id)}
            aria-label={`Delete ${item.name}`}
            className="cursor-pointer"
          >
            <Trash2 className="size-4" />
          </Button>
        </TableCell>
      </TableRow>
    ))}
  </TableBody>
</Table>
```

### Empty State
```tsx
<TableRow>
  <TableCell 
    colSpan={3} 
    className="h-24 text-center text-muted-foreground"
  >
    No items found
  </TableCell>
</TableRow>
```

## Forms

### Text Input
```tsx
<div className="space-y-2">
  <Label htmlFor="name">Name</Label>
  <Input 
    id="name"
    type="text"
    placeholder="Enter name"
    aria-describedby={errors.name ? "name-error" : undefined}
    aria-invalid={!!errors.name}
  />
  {errors.name && (
    <p id="name-error" role="alert" className="text-sm text-destructive">
      {errors.name.message}
    </p>
  )}
</div>
```

### Select
```tsx
<div className="space-y-2">
  <Label htmlFor="status">Status</Label>
  <Select value={status} onValueChange={setStatus}>
    <SelectTrigger id="status">
      <SelectValue placeholder="Select status" />
    </SelectTrigger>
    <SelectContent>
      <SelectItem value="active">Active</SelectItem>
      <SelectItem value="inactive">Inactive</SelectItem>
    </SelectContent>
  </Select>
</div>
```

## Dialogs

### Confirmation Dialog
```tsx
<AlertDialog open={isOpen} onOpenChange={setIsOpen}>
  <AlertDialogContent>
    <AlertDialogHeader>
      <AlertDialogTitle>Confirm Action</AlertDialogTitle>
      <AlertDialogDescription>
        Are you sure you want to delete this item? This action cannot be undone.
      </AlertDialogDescription>
    </AlertDialogHeader>
    <AlertDialogFooter>
      <AlertDialogCancel>Cancel</AlertDialogCancel>
      <AlertDialogAction onClick={handleConfirm}>
        Delete
      </AlertDialogAction>
    </AlertDialogFooter>
  </AlertDialogContent>
</AlertDialog>
```

## Tabs

### Basic Tabs
```tsx
<Tabs defaultValue="tab1" className="space-y-4">
  <TabsList>
    <TabsTrigger value="tab1">
      <FileText className="mr-2 size-4" />
      Tab 1
    </TabsTrigger>
    <TabsTrigger value="tab2">
      <Radio className="mr-2 size-4" />
      Tab 2
    </TabsTrigger>
  </TabsList>

  <TabsContent value="tab1">
    <Card>
      <CardHeader>
        <CardTitle>Tab 1 Content</CardTitle>
      </CardHeader>
      <CardContent>
        {/* Content */}
      </CardContent>
    </Card>
  </TabsContent>

  <TabsContent value="tab2">
    <Card>
      <CardHeader>
        <CardTitle>Tab 2 Content</CardTitle>
      </CardHeader>
      <CardContent>
        {/* Content */}
      </CardContent>
    </Card>
  </TabsContent>
</Tabs>
```

## Error States

### Inline Error
```tsx
<div className="flex items-center gap-2 rounded-lg border border-destructive/50 bg-destructive/10 p-4 text-sm text-destructive">
  <AlertCircle className="size-4" />
  <span>Failed to load data. Please try again.</span>
</div>
```

### Info Message
```tsx
<div className="rounded-lg border border-blue-500/50 bg-blue-500/10 p-4 text-sm text-blue-700 dark:text-blue-300">
  <p className="font-medium">Note:</p>
  <p className="mt-1">This is an informational message.</p>
</div>
```

## Page Layout

### Page Header
```tsx
<div className="space-y-6 p-6 lg:p-8">
  {/* Breadcrumb */}
  <div>
    <p className="text-sm text-muted-foreground">
      Platform Admin &rsaquo; System
    </p>
    <h1 className="text-2xl font-bold tracking-tight">Page Title</h1>
    <p className="text-muted-foreground leading-relaxed">
      Page description goes here.
    </p>
  </div>

  {/* Content */}
  <div className="space-y-6">
    {/* Cards, tables, etc. */}
  </div>
</div>
```

## Data Fetching with React Query

### Basic Query
```tsx
const { data, isLoading, error, refetch } = useQuery({
  queryKey: ['items'],
  queryFn: async () => {
    const res = await api.get<{ data: Item[] }>('items');
    return res.data.data;
  },
  refetchInterval: 10000, // Auto-refresh every 10s
});
```

### Mutation
```tsx
const mutation = useMutation({
  mutationFn: async (id: string) => {
    await api.delete(`items/${id}`);
  },
  onSuccess: () => {
    queryClient.invalidateQueries({ queryKey: ['items'] });
  },
});
```

## Responsive Patterns

### Hide on Mobile
```tsx
<div className="hidden md:block">
  {/* Desktop only content */}
</div>
```

### Show on Mobile Only
```tsx
<div className="md:hidden">
  {/* Mobile only content */}
</div>
```

### Responsive Grid
```tsx
<div className="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3">
  {/* Grid items */}
</div>
```

### Responsive Padding
```tsx
<div className="p-4 md:p-6 lg:p-8">
  {/* Content */}
</div>
```

## Technical Data Display

### IP Address / Technical Info
```tsx
<span className="font-mono text-xs text-muted-foreground">
  192.168.1.1:5060
</span>
```

### Log Lines
```tsx
<div className="max-h-[600px] overflow-auto rounded-lg border bg-muted/30 p-4 font-mono text-xs">
  {logs.map((line, idx) => (
    <div 
      key={idx}
      className="whitespace-pre-wrap break-all py-0.5 hover:bg-accent/50"
    >
      {line}
    </div>
  ))}
</div>
```

## Common Utility Classes

```tsx
// Cursor
className="cursor-pointer"

// Transitions
className="transition-colors duration-200"

// Hover states
className="hover:bg-accent/50"

// Focus visible
className="focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"

// Reduced motion
className="motion-safe:animate-spin"

// Touch targets
className="min-h-touch min-w-touch"

// Line height
className="leading-relaxed"  // 1.625
className="leading-loose"    // 2
```

## Color Classes

```tsx
// Status colors
className="text-green-600"      // Success
className="text-red-600"        // Error
className="text-amber-500"      // Warning
className="text-slate-400"      // Inactive

// Background colors
className="bg-success"          // Success background
className="bg-destructive"      // Error background
className="bg-warning"          // Warning background
className="bg-muted"            // Muted background

// Text colors
className="text-foreground"     // Primary text
className="text-muted-foreground" // Secondary text
```
