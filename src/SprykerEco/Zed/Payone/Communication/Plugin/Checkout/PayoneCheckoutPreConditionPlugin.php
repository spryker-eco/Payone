<?php

/**
 * MIT License
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerEco\Zed\Payone\Communication\Plugin\Checkout;

use Generated\Shared\Transfer\CheckoutResponseTransfer;
use Generated\Shared\Transfer\QuoteTransfer;
use Spryker\Zed\CheckoutExtension\Dependency\Plugin\CheckoutPreConditionPluginInterface;
use Spryker\Zed\Kernel\Communication\AbstractPlugin;
use SprykerEco\Zed\Payone\PayoneConfig;

/**
 * @method \SprykerEco\Zed\Payone\Business\PayoneFacadeInterface getFacade()
 * @method \SprykerEco\Zed\Payone\Communication\PayoneCommunicationFactory getFactory()
 * @method \SprykerEco\Zed\Payone\Persistence\PayoneQueryContainerInterface getQueryContainer()
 * @method \SprykerEco\Zed\Payone\PayoneConfig getConfig()
 */
class PayoneCheckoutPreConditionPlugin extends AbstractPlugin implements CheckoutPreConditionPluginInterface
{
    /**
     * {@inheritDoc}
     * - Does additional logic only if the payment provider is 'Payone' otherwise does nothing.
     * - Maps Quote to CalculableObject.
     * - Run all calculator plugins.
     * - Maps CalculableObject to Quote.
     * - Executes `QuotePostRecalculatePluginInterface` stack of plugins if `$executeQuotePlugins` is true.
     * - Sets the amount of the payment detail of the 'Payone' payment.
     * - Returns 'true' in any case.
     *
     * @api
     *
     * @param \Generated\Shared\Transfer\QuoteTransfer $quoteTransfer
     * @param \Generated\Shared\Transfer\CheckoutResponseTransfer $checkoutResponseTransfer
     *
     * @return bool
     */
    public function checkCondition(QuoteTransfer $quoteTransfer, CheckoutResponseTransfer $checkoutResponseTransfer)
    {
        if ($quoteTransfer->getPayment()->getPaymentProvider() === PayoneConfig::PROVIDER_NAME) {
            $quoteTransfer = $this->getFactory()->getCalculationFacade()->recalculateQuote($quoteTransfer);
            $quoteTransfer->getPayment()->getPayone()->getPaymentDetail()->setAmount($quoteTransfer->getPayment()->getAmount());
        }

        return true;
    }
}
