<?php

/**
 * MIT License
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerEco\Zed\Payone\Persistence;

use Propel\Runtime\ActiveQuery\Criteria;
use Spryker\Zed\Kernel\Persistence\AbstractQueryContainer;
use SprykerEco\Shared\Payone\PayoneApiConstants;

/**
 * @method \SprykerEco\Zed\Payone\Persistence\PayonePersistenceFactory getFactory()
 */
class PayoneQueryContainer extends AbstractQueryContainer implements PayoneQueryContainerInterface
{

    /**
     * @api
     *
     * @param int $transactionId
     *
     * @return \Orm\Zed\Payone\Persistence\SpyPaymentPayoneTransactionStatusLogQuery
     */
    public function createCurrentSequenceNumberQuery($transactionId)
    {
        $query = $this->getFactory()->createPaymentPayoneTransactionStatusLogQuery();
        $query->filterByTransactionId($transactionId)
            ->orderBySequenceNumber(Criteria::DESC);

        return $query;
    }

    /**
     * @api
     *
     * @param int $transactionId
     *
     * @return \Orm\Zed\Payone\Persistence\Base\SpyPaymentPayoneQuery
     */
    public function createPaymentByTransactionIdQuery($transactionId)
    {
        $query = $this->getFactory()->createPaymentPayoneQuery();
        $query->filterByTransactionId($transactionId);

        return $query;
    }

    /**
     * @api
     *
     * @param string $invoiceTitle
     * @param int $customerId
     *
     * @return \Orm\Zed\Payone\Persistence\SpyPaymentPayoneQuery
     */
    public function createPaymentByInvoiceTitleAndCustomerIdQuery($invoiceTitle, $customerId)
    {
        $query = $this->getFactory()->createPaymentPayoneQuery();
        $query->useSpySalesOrderQuery()
            ->filterByFkCustomer($customerId)
            ->endUse()
            ->useSpyPaymentPayoneDetailQuery()
            ->filterByInvoiceTitle($invoiceTitle)
            ->endUse()
            ->useSpyPaymentPayoneApiLogQuery()
            ->filterByStatus(PayoneApiConstants::RESPONSE_TYPE_APPROVED)
            ->endUse();

        return $query;
    }

    /**
     * @api
     *
     * @param string $fileReference
     * @param int $customerId
     *
     * @return \Orm\Zed\Payone\Persistence\SpyPaymentPayoneQuery
     */
    public function createPaymentByFileReferenceAndCustomerIdQuery($fileReference, $customerId)
    {
        $query = $this->getFactory()->createPaymentPayoneQuery();
        $query->useSpySalesOrderQuery()
            ->filterByFkCustomer($customerId)
            ->endUse()
            ->useSpyPaymentPayoneDetailQuery()
            ->filterByMandateIdentification($fileReference)
            ->endUse()
            ->useSpyPaymentPayoneApiLogQuery()
            ->filterByStatus(PayoneApiConstants::RESPONSE_TYPE_APPROVED)
            ->endUse();

        return $query;
    }

    /**
     * @api
     *
     * @param int $fkPayment
     * @param string $requestName
     *
     * @return \Orm\Zed\Payone\Persistence\SpyPaymentPayoneApiLogQuery
     */
    public function createApiLogByPaymentAndRequestTypeQuery($fkPayment, $requestName)
    {
        $query = $this->getFactory()->createPaymentPayoneApiLogQuery();
        $query->filterByFkPaymentPayone($fkPayment)
              ->filterByRequest($requestName);

        return $query;
    }

    /**
     * @api
     *
     * @param int $idOrder
     *
     * @return \Orm\Zed\Payone\Persistence\Base\SpyPaymentPayoneQuery
     */
    public function createPaymentByOrderId($idOrder)
    {
        $query = $this->getFactory()->createPaymentPayoneQuery();
        $query->findByFkSalesOrder($idOrder);

        return $query;
    }

    /**
     * @api
     *
     * @param int $idOrder
     * @param string $request
     *
     * @return \Orm\Zed\Payone\Persistence\SpyPaymentPayoneApiLogQuery
     */
    public function createApiLogsByOrderIdAndRequest($idOrder, $request)
    {
        $query = $this->getFactory()->createPaymentPayoneApiLogQuery()
            ->useSpyPaymentPayoneQuery()
            ->filterByFkSalesOrder($idOrder)
            ->endUse()
            ->filterByRequest($request)
            ->orderByCreatedAt(Criteria::DESC) //TODO: Index?
            ->orderByIdPaymentPayoneApiLog(Criteria::DESC);

        return $query;
    }

    /**
     * @api
     *
     * @param int $orderId
     *
     * @return \Orm\Zed\Payone\Persistence\Base\SpyPaymentPayoneQuery
     */
    public function createPaymentById($orderId)
    {
        $query = $this->getFactory()->createPaymentPayoneQuery();
        $query->findByFkSalesOrder($orderId);

        return $query;
    }

    /**
     * @api
     *
     * @param int $idIdSalesOrder
     *
     * @return \Orm\Zed\Payone\Persistence\SpyPaymentPayoneTransactionStatusLog[]
     */
    public function createTransactionStatusLogsBySalesOrder($idIdSalesOrder)
    {
        $query = $this->getFactory()->createPaymentPayoneTransactionStatusLogQuery()
            ->useSpyPaymentPayoneQuery()
            ->filterByFkSalesOrder($idIdSalesOrder)
            ->endUse()
            ->orderByCreatedAt();

        return $query;
    }

    /**
     * @api
     *
     * @param int $idSalesOrderItem
     * @param array $ids
     *
     * @return \Orm\Zed\Payone\Persistence\SpyPaymentPayoneTransactionStatusLogOrderItem[]
     */
    public function createTransactionStatusLogOrderItemsByLogIds($idSalesOrderItem, $ids)
    {
        $relations = $this->getFactory()->createPaymentPayoneTransactionStatusLogOrderItemQuery()
            ->filterByIdPaymentPayoneTransactionStatusLog($ids, Criteria::IN)
            ->filterByIdSalesOrderItem($idSalesOrderItem);

        return $relations;
    }

    /**
     * @api
     *
     * @param int $idSalesOrder
     *
     * @return \Orm\Zed\Payone\Persistence\SpyPaymentPayoneApiLogQuery
     */
    public function createLastApiLogsByOrderId($idSalesOrder)
    {
        $query = $this->getFactory()->createPaymentPayoneApiLogQuery()
            ->useSpyPaymentPayoneQuery()
            ->filterByFkSalesOrder($idSalesOrder)
            ->endUse()
            ->orderByCreatedAt(Criteria::DESC)
            ->orderByIdPaymentPayoneApiLog(Criteria::DESC);

        return $query;
    }

    /**
     * @api
     *
     * @param \Propel\Runtime\Collection\ObjectCollection $orders
     *
     * @return \Orm\Zed\Payone\Persistence\SpyPaymentPayoneApiLogQuery
     */
    public function createApiLogsByOrderIds($orders)
    {
        $ids = [];
        /** @var \Orm\Zed\Sales\Persistence\SpySalesOrder $order */
        foreach ($orders as $order) {
            $ids[] = $order->getIdSalesOrder();
        }

        $query = $this->getFactory()->createPaymentPayoneApiLogQuery()
            ->useSpyPaymentPayoneQuery()
            ->filterByFkSalesOrder($ids, Criteria::IN)
            ->endUse()
            ->orderByCreatedAt();

        return $query;
    }

    /**
     * @api
     *
     * @param \Orm\Zed\Sales\Persistence\SpySalesOrder[] $orders
     *
     * @return \Orm\Zed\Payone\Persistence\SpyPaymentPayoneTransactionStatusLogQuery
     */
    public function createTransactionStatusLogsByOrderIds($orders)
    {
        $ids = [];
        foreach ($orders as $order) {
            $ids[] = $order->getIdSalesOrder();
        }

        $query = $this->getFactory()->createPaymentPayoneTransactionStatusLogQuery()
            ->useSpyPaymentPayoneQuery()
            ->filterByFkSalesOrder($ids, Criteria::IN)
            ->endUse()
            ->orderByCreatedAt();

        return $query;
    }

    /**
     * @return \Orm\Zed\Payone\Persistence\SpyPaymentPayoneApiCallLogQuery
     */
    public function createApiCallLog()
    {
        return $this->getFactory()->createPaymentPayoneApiCallLogQuery();
    }

}
