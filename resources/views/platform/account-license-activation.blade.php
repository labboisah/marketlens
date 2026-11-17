<div
    id="kernelbridge-account-activation"
    data-product-name="{{ $productName }}"
    data-business-name="{{ $businessName ?? '' }}"
    data-can-manage-license="{{ $canManageLicense ? 'true' : 'false' }}"
    data-link-id="{{ data_get($initialAccountLink, 'linkId', '') }}"
    data-link-status="{{ data_get($initialAccountLink, 'status', '') }}"
    data-csrf-token="{{ csrf_token() }}"
>
    @if (! $canManageLicense)
        <p role="alert">Your business account could not be found. Contact your administrator before activating a license.</p>
    @endif
</div>
