import React from 'react';
import { createRoot } from 'react-dom/client';
import { ActivationScreen } from '@kernelbridge/activation-ui-react';
import '@kernelbridge/activation-ui-react/styles.css';

const mount = document.getElementById('kernelbridge-account-activation');
let csrfToken = '';

if (mount) {
    const { productName, businessName, canManageLicense, linkId, linkStatus } = mount.dataset;
    csrfToken = mount.dataset.csrfToken ?? '';

    if (canManageLicense === 'true') {
        const platformAccount = {
            async beginLink(activationCode) {
                return request('/account/license-activation/link', 'POST', {
                    activation_code: activationCode ?? '',
                });
            },
            async getLinkStatus(currentLinkId) {
                const result = await request(
                    `/account/license-activation/link/${encodeURIComponent(currentLinkId)}`,
                    'GET',
                );

                return result.status;
            },
            async completeActivation(currentLinkId) {
                const result = await request(
                    `/account/license-activation/link/${encodeURIComponent(currentLinkId)}/complete`,
                    'POST',
                );
                window.location.assign(result.redirectTo);
            },
            async unlinkAccount(currentLinkId) {
                await request(
                    `/account/license-activation/link/${encodeURIComponent(currentLinkId)}`,
                    'DELETE',
                );
            },
        };

        createRoot(mount).render(
            React.createElement(ActivationScreen, {
                mode: 'platform_account',
                productName: productName || 'MarketLens',
                organizationName: businessName || null,
                activationCodeRequired: true,
                initialAccountLink: linkId && isLinkStatus(linkStatus)
                    ? { linkId, status: linkStatus }
                    : null,
                platformAccount,
            }),
        );
    }
}

async function request(
    path,
    method,
    body,
) {
    const response = await fetch(path, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
        },
        ...(body ? { body: JSON.stringify(body) } : {}),
    });
    let result = {};
    const responseBody = await response.text();

    if (responseBody) {
        try {
            result = JSON.parse(responseBody);
        } catch {
            throw new Error('KernelBridge returned an unreadable activation response.');
        }
    }

    if (!response.ok) {
        throw new Error(result.message ?? 'KernelBridge account activation could not be completed.');
    }

    return result;
}

function isLinkStatus(status) {
    return status === 'pending' || status === 'approved' || status === 'rejected' || status === 'revoked';
}
