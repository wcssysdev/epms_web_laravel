{{-- Tom Select theme overrides so the enhanced dropdown matches the EPMS
     form controls (light/dark via the --epms-* CSS variables). Included by
     the BIN / OPH Card / FDN Card forms. --}}
<style>
    .ts-wrapper { margin-bottom: 0; }
    .ts-control {
        background: var(--epms-header-bg) !important;
        color: var(--epms-text) !important;
        border: 1px solid var(--epms-border) !important;
        border-radius: 0.5rem !important;
        padding: 0.5rem 0.875rem !important;
        min-height: 2.75rem;
        font-size: 0.875rem;
        box-shadow: none !important;
    }
    .ts-control input,
    .ts-control .item { color: var(--epms-text) !important; }
    .ts-wrapper.focus .ts-control {
        border-color: var(--epms-primary, #5750f1) !important;
        box-shadow: 0 0 0 1px var(--epms-primary, #5750f1) !important;
    }
    .ts-dropdown {
        background: var(--epms-header-bg) !important;
        color: var(--epms-text) !important;
        border: 1px solid var(--epms-border) !important;
        border-radius: 0.5rem !important;
        font-size: 0.875rem;
        z-index: 60;
    }
    .ts-dropdown .option,
    .ts-dropdown .no-results { color: var(--epms-text) !important; }
    .ts-dropdown .active {
        background: var(--epms-primary, #5750f1) !important;
        color: #fff !important;
    }
    .ts-dropdown .option.selected { font-weight: 600; }
</style>