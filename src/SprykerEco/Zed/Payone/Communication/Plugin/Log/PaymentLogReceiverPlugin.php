<?php

/**
 * MIT License
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerEco\Zed\Payone\Communication\Plugin\Log;

use Generated\Shared\Transfer\OrderCollectionTransfer;
use Propel\Runtime\Collection\ObjectCollection;
use Spryker\Zed\Kernel\Communication\AbstractPlugin;

/**
 * @method \SprykerEco\Zed\Payone\Business\PayoneFacadeInterface getFacade()
 * @method \SprykerEco\Zed\Payone\Communication\PayoneCommunicationFactory getFactory()
 * @method \SprykerEco\Zed\Payone\Persistence\PayoneQueryContainerInterface getQueryContainer()
 * @method \SprykerEco\Zed\Payone\PayoneConfig getConfig()
 */
class PaymentLogReceiverPlugin extends AbstractPlugin implements PaymentLogReceiverPluginInterface
{
    /**
     * {@inheritDoc}
     *
     * @api
     *
     * @param \Propel\Runtime\Collection\ObjectCollection $orders
     *
     * @return \Generated\Shared\Transfer\PayonePaymentLogCollectionTransfer
     */
    public function getPaymentLogs(ObjectCollection $orders)
    {
        $orderCollectionTransfer = new OrderCollectionTransfer();
        /** @var \Generated\Shared\Transfer\OrderTransfer[] $orderTransfers */
        $orderTransfers = $orders->getData();
        $orderCollectionTransfer->setOrders($orderTransfers);

        return $this->getFacade()->getPaymentLogs($orderCollectionTransfer);
    }
}
