document.addEventListener('DOMContentLoaded', function () {
    const configNode = document.getElementById('sfaSuccessConfig');
    if (!configNode) return;
    let purchasePayload;
    try {
        purchasePayload = JSON.parse(configNode.getAttribute('data-config') || '{}');
    } catch (error) {
        console.error('Invalid purchase tracking configuration.', error);
        return;
    }
    let retryCount = 0;
    const maxRetry = 20;

    function firePurchase() {
        if (!window.SFATracking || typeof window.SFATracking.purchase !== 'function') {
            retryCount++;

            if (retryCount <= maxRetry) {
                setTimeout(firePurchase, 300);
            }

            return;
        }

        window.SFATracking.purchase(purchasePayload);
    }

    firePurchase();
});
