<div class="overflow-x-auto rounded-md border border-border">
    <table {{ $attributes->merge(['class' => 'min-w-full divide-y divide-border text-sm']) }}>
        {{ $slot }}
    </table>
</div>
