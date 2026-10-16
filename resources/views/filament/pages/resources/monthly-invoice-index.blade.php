{{--
  Custom view for MonthlyInvoiceResource list page to add responsive styles for card view.
--}}
{{-- Extend the default list records view --}}
@extends('filament::pages.list-records')

{{-- Push a style tag to the head --}}
@push('headers')
<style>
    /* Responsive table: hide card columns on desktop, hide table columns on mobile */
    @media (min-width: 768px) {
        .mobile-card-column {
            display: none !important;
        }
        .desktop-table-column {
            display: table-cell !important;
        }
    }
    @media (max-width: 767px) {
        .mobile-card-column {
            display: table-cell !important;
        }
        .desktop-table-column {
            display: none !important;
        }
    }
</style>
@endpush