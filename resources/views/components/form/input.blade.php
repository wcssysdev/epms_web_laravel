@props(['label', 'name', 'type' => 'text', 'required' => false, 'value' => '', 'placeholder' => '', 'hint' => null, 'disabled' => false])

<div class="form-control mb-4">
    <label class="block text-sm font-medium mb-1" style="color: var(--epms-text);">
        {{ $label }}
        @if($required)<span class="text-red-500 ml-0.5">*</span>@endif
    </label>
    <input type="{{ $type }}"
           name="{{ $name }}"
           id="{{ $name }}"
           value="{{ old($name, $value) }}"
           placeholder="{{ $placeholder }}"
           {{ $required ? 'required' : '' }}
           {{ $disabled ? 'disabled' : '' }}
           class="w-full rounded-lg border px-3.5 py-2.5 text-sm outline-none transition
                  focus:border-primary focus:ring-1 focus:ring-primary
                  disabled:opacity-60 disabled:cursor-not-allowed
                  {{ $errors->has($name) ? 'border-red-400' : '' }}"
           style="background: var(--epms-header-bg); color: var(--epms-text); border-color: {{ $errors->has($name) ? '' : 'var(--epms-border)' }};">
    @error($name)
        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
    @enderror
    @if($hint)
        <p class="mt-1 text-xs" style="color: var(--epms-text-muted);">{{ $hint }}</p>
    @endif
</div>
