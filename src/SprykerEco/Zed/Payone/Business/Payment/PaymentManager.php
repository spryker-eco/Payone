<?php

/**
 * MIT License
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerEco\Zed\Payone\Business\Payment;

use Generated\Shared\Transfer\CaptureResponseTransfer;
use Generated\Shared\Transfer\CheckoutErrorTransfer;
use Generated\Shared\Transfer\CheckoutResponseTransfer;
use Generated\Shared\Transfer\OrderTransfer;
use Generated\Shared\Transfer\PaymentDetailTransfer;
use Generated\Shared\Transfer\PayoneBankAccountCheckTransfer;
use Generated\Shared\Transfer\PayoneCaptureTransfer;
use Generated\Shared\Transfer\PayoneCreditCardCheckRequestDataTransfer;
use Generated\Shared\Transfer\PayoneCreditCardTransfer;
use Generated\Shared\Transfer\PayoneGetFileTransfer;
use Generated\Shared\Transfer\PayoneGetInvoiceTransfer;
use Generated\Shared\Transfer\PayoneGetSecurityInvoiceTransfer;
use Generated\Shared\Transfer\PayoneInitPaypalExpressCheckoutRequestTransfer;
use Generated\Shared\Transfer\PayoneManageMandateTransfer;
use Generated\Shared\Transfer\PayoneOrderItemFilterTransfer;
use Generated\Shared\Transfer\PayonePartialOperationRequestTransfer;
use Generated\Shared\Transfer\PayonePaymentLogCollectionTransfer;
use Generated\Shared\Transfer\PayonePaymentLogTransfer;
use Generated\Shared\Transfer\PayonePaymentTransfer;
use Generated\Shared\Transfer\PayonePaypalExpressCheckoutGenericPaymentResponseTransfer;
use Generated\Shared\Transfer\PayoneRefundTransfer;
use Generated\Shared\Transfer\PayoneStandardParameterTransfer;
use Generated\Shared\Transfer\QuoteTransfer;
use Generated\Shared\Transfer\RefundResponseTransfer;
use Generated\Shared\Transfer\TotalsTransfer;
use Orm\Zed\Payone\Persistence\SpyPaymentPayone;
use Orm\Zed\Payone\Persistence\SpyPaymentPayoneApiLog;
use Spryker\Shared\Shipment\ShipmentConfig;
use SprykerEco\Shared\Payone\Dependency\ModeDetectorInterface;
use SprykerEco\Shared\Payone\PayoneApiConstants;
use SprykerEco\Shared\Payone\PayoneTransactionStatusConstants;
use SprykerEco\Zed\Payone\Business\Api\Adapter\AdapterInterface;
use SprykerEco\Zed\Payone\Business\Api\Call\CreditCardCheck;
use SprykerEco\Zed\Payone\Business\Api\Request\Container\AbstractRequestContainer;
use SprykerEco\Zed\Payone\Business\Api\Request\Container\AuthorizationContainerInterface;
use SprykerEco\Zed\Payone\Business\Api\Request\Container\Capture\BusinessContainer;
use SprykerEco\Zed\Payone\Business\Api\Request\Container\CaptureContainer;
use SprykerEco\Zed\Payone\Business\Api\Request\Container\DebitContainer;
use SprykerEco\Zed\Payone\Business\Api\Request\Container\GenericPaymentContainer;
use SprykerEco\Zed\Payone\Business\Api\Request\Container\RefundContainer;
use SprykerEco\Zed\Payone\Business\Api\Response\Container\AbstractResponseContainer;
use SprykerEco\Zed\Payone\Business\Api\Response\Container\AuthorizationResponseContainer;
use SprykerEco\Zed\Payone\Business\Api\Response\Container\BankAccountCheckResponseContainer;
use SprykerEco\Zed\Payone\Business\Api\Response\Container\CaptureResponseContainer;
use SprykerEco\Zed\Payone\Business\Api\Response\Container\CreditCardCheckResponseContainer;
use SprykerEco\Zed\Payone\Business\Api\Response\Container\DebitResponseContainer;
use SprykerEco\Zed\Payone\Business\Api\Response\Container\GenericPaymentResponseContainer;
use SprykerEco\Zed\Payone\Business\Api\Response\Container\GetFileResponseContainer;
use SprykerEco\Zed\Payone\Business\Api\Response\Container\GetInvoiceResponseContainer;
use SprykerEco\Zed\Payone\Business\Api\Response\Container\GetSecurityInvoiceResponseContainer;
use SprykerEco\Zed\Payone\Business\Api\Response\Container\ManageMandateResponseContainer;
use SprykerEco\Zed\Payone\Business\Api\Response\Container\RefundResponseContainer;
use SprykerEco\Zed\Payone\Business\Api\Response\Mapper\AuthorizationResponseMapper;
use SprykerEco\Zed\Payone\Business\Api\Response\Mapper\CaptureResponseMapper;
use SprykerEco\Zed\Payone\Business\Api\Response\Mapper\CreditCardCheckResponseMapper;
use SprykerEco\Zed\Payone\Business\Api\Response\Mapper\DebitResponseMapper;
use SprykerEco\Zed\Payone\Business\Api\Response\Mapper\RefundResponseMapper;
use SprykerEco\Zed\Payone\Business\Distributor\OrderPriceDistributorInterface;
use SprykerEco\Zed\Payone\Business\Exception\InvalidPaymentMethodException;
use SprykerEco\Zed\Payone\Business\Key\HashGenerator;
use SprykerEco\Zed\Payone\Business\Key\HmacGeneratorInterface;
use SprykerEco\Zed\Payone\Business\SequenceNumber\SequenceNumberProviderInterface;
use SprykerEco\Zed\Payone\PayoneConfig;
use SprykerEco\Zed\Payone\Persistence\PayoneEntityManagerInterface;
use SprykerEco\Zed\Payone\Persistence\PayoneQueryContainerInterface;
use SprykerEco\Zed\Payone\Persistence\PayoneRepositoryInterface;

class PaymentManager implements PaymentManagerInterface
{
    public const LOG_TYPE_API_LOG = 'SpyPaymentPayoneApiLog';
    public const LOG_TYPE_TRANSACTION_STATUS_LOG = 'SpyPaymentPayoneTransactionStatusLog';
    public const ERROR_ACCESS_DENIED_MESSAGE = 'Access denied';

    protected const PARAMETER_KEY_CAPTURE_MODE = 'capturemode';
    protected const PARAMETER_VALUE_CAPTURE_COMPLETED = 'completed';

    /**
     * @see \Spryker\Shared\Shipment\ShipmentConstants::SHIPMENT_EXPENSE_TYPE
     * @see \Spryker\Shared\Shipment\ShipmentConfig::SHIPMENT_EXPENSE_TYPE
     *
     * @deprecated Necessary in order to save compatibility with spryker/shipping version less than "^8.0.0".
     * use \Spryker\Shared\Shipment\ShipmentConfig::SHIPMENT_EXPENSE_TYPE instead if shipping version is higher
     */
    protected const SHIPMENT_EXPENSE_TYPE = 'SHIPMENT_EXPENSE_TYPE';

    /**
     * @var \SprykerEco\Zed\Payone\Business\Api\Adapter\AdapterInterface
     */
    protected $executionAdapter;

    /**
     * @var \SprykerEco\Zed\Payone\Persistence\PayoneQueryContainerInterface
     */
    protected $queryContainer;

    /**
     * @var \Generated\Shared\Transfer\PayoneStandardParameterTransfer
     */
    protected $standardParameter;

    /**
     * @var \SprykerEco\Zed\Payone\Business\SequenceNumber\SequenceNumberProviderInterface
     */
    protected $sequenceNumberProvider;

    /**
     * @var \SprykerEco\Shared\Payone\Dependency\ModeDetectorInterface
     */
    protected $modeDetector;

    /**
     * @var \SprykerEco\Zed\Payone\Business\Key\HmacGeneratorInterface|\SprykerEco\Zed\Payone\Business\Key\HashGenerator
     */
    protected $hashGenerator;

    /**
     * @var \SprykerEco\Zed\Payone\Business\Key\UrlHmacGenerator
     */
    protected $urlHmacGenerator;

    /**
     * @var \SprykerEco\Zed\Payone\Business\Payment\PaymentMethodMapperInterface[]
     */
    protected $registeredMethodMappers;

    /**
     * @var \SprykerEco\Zed\Payone\Persistence\PayoneRepositoryInterface
     */
    protected $payoneRepository;

    /**
     * @var \SprykerEco\Zed\Payone\Persistence\PayoneEntityManagerInterface
     */
    protected $payoneEntityManager;

    /**
     * @var \SprykerEco\Zed\Payone\Business\Distributor\OrderPriceDistributorInterface
     */
    protected $orderPriceDistributor;

    /**
     * @param \SprykerEco\Zed\Payone\Business\Api\Adapter\AdapterInterface $executionAdapter
     * @param \SprykerEco\Zed\Payone\Persistence\PayoneQueryContainerInterface $queryContainer
     * @param \Generated\Shared\Transfer\PayoneStandardParameterTransfer $standardParameter
     * @param \SprykerEco\Zed\Payone\Business\Key\HashGenerator $hashGenerator
     * @param \SprykerEco\Zed\Payone\Business\SequenceNumber\SequenceNumberProviderInterface $sequenceNumberProvider
     * @param \SprykerEco\Shared\Payone\Dependency\ModeDetectorInterface $modeDetector
     * @param \SprykerEco\Zed\Payone\Business\Key\HmacGeneratorInterface $urlHmacGenerator
     * @param \SprykerEco\Zed\Payone\Persistence\PayoneRepositoryInterface $payoneRepository
     * @param \SprykerEco\Zed\Payone\Persistence\PayoneEntityManagerInterface $payoneEntityManager
     * @param \SprykerEco\Zed\Payone\Business\Distributor\OrderPriceDistributorInterface $orderPriceDistributor
     */
    public function __construct(
        AdapterInterface $executionAdapter,
        PayoneQueryContainerInterface $queryContainer,
        PayoneStandardParameterTransfer $standardParameter,
        HashGenerator $hashGenerator,
        SequenceNumberProviderInterface $sequenceNumberProvider,
        ModeDetectorInterface $modeDetector,
        HmacGeneratorInterface $urlHmacGenerator,
        PayoneRepositoryInterface $payoneRepository,
        PayoneEntityManagerInterface $payoneEntityManager,
        OrderPriceDistributorInterface $orderPriceDistributor
    ) {
        $this->executionAdapter = $executionAdapter;
        $this->queryContainer = $queryContainer;
        $this->standardParameter = $standardParameter;
        $this->hashGenerator = $hashGenerator;
        $this->sequenceNumberProvider = $sequenceNumberProvider;
        $this->modeDetector = $modeDetector;
        $this->urlHmacGenerator = $urlHmacGenerator;
        $this->payoneRepository = $payoneRepository;
        $this->payoneEntityManager = $payoneEntityManager;
        $this->orderPriceDistributor = $orderPriceDistributor;
    }

    /**
     * @param \SprykerEco\Zed\Payone\Business\Payment\PaymentMethodMapperInterface $paymentMethodMapper
     *
     * @return void
     */
    public function registerPaymentMethodMapper(PaymentMethodMapperInterface $paymentMethodMapper)
    {
        $paymentMethodMapper->setStandardParameter($this->standardParameter);
        $paymentMethodMapper->setSequenceNumberProvider($this->sequenceNumberProvider);
        $paymentMethodMapper->setUrlHmacGenerator($this->urlHmacGenerator);
        $this->registeredMethodMappers[$paymentMethodMapper->getName()] = $paymentMethodMapper;
    }

    /**
     * @param string $name
     *
     * @return \SprykerEco\Zed\Payone\Business\Payment\PaymentMethodMapperInterface|null
     */
    protected function findPaymentMethodMapperByName($name)
    {
        if (array_key_exists($name, $this->registeredMethodMappers)) {
            return $this->registeredMethodMappers[$name];
        }

        return null;
    }

    /**
     * @param string $paymentMethodName
     *
     * @throws \SprykerEco\Zed\Payone\Business\Exception\InvalidPaymentMethodException
     *
     * @return \SprykerEco\Zed\Payone\Business\Payment\PaymentMethodMapperInterface
     */
    protected function getRegisteredPaymentMethodMapper($paymentMethodName)
    {
        $paymentMethodMapper = $this->findPaymentMethodMapperByName($paymentMethodName);
        if ($paymentMethodMapper === null) {
            throw new InvalidPaymentMethodException(
                sprintf('No registered payment method mapper found for given method name %s', $paymentMethodName)
            );
        }

        return $paymentMethodMapper;
    }

    /**
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return \Generated\Shared\Transfer\AuthorizationResponseTransfer
     */
    public function authorizePayment(OrderTransfer $orderTransfer)
    {
        $paymentEntity = $this->getPaymentEntity($orderTransfer->getIdSalesOrder());
        $paymentMethodMapper = $this->getPaymentMethodMapper($paymentEntity);
        $requestContainer = $paymentMethodMapper->mapPaymentToAuthorization($paymentEntity, $orderTransfer);
        $requestContainer = $this->prepareOrderItems($orderTransfer, $requestContainer);
        $requestContainer = $this->prepareOrderShipment($orderTransfer, $requestContainer);
        $requestContainer = $this->prepareOrderDiscount($orderTransfer, $requestContainer);
        $responseContainer = $this->performAuthorizationRequest($paymentEntity, $requestContainer);

        $responseMapper = new AuthorizationResponseMapper();
        $responseTransfer = $responseMapper->getAuthorizationResponseTransfer($responseContainer);

        return $responseTransfer;
    }

    /**
     * @param int $idSalesOrder
     *
     * @return \Generated\Shared\Transfer\AuthorizationResponseTransfer
     */
    public function preAuthorizePayment($idSalesOrder)
    {
        $paymentEntity = $this->getPaymentEntity($idSalesOrder);
        $paymentMethodMapper = $this->getPaymentMethodMapper($paymentEntity);
        $requestContainer = $paymentMethodMapper->mapPaymentToPreAuthorization($paymentEntity);
        $responseContainer = $this->performAuthorizationRequest($paymentEntity, $requestContainer);

        $responseMapper = new AuthorizationResponseMapper();
        $responseTransfer = $responseMapper->getAuthorizationResponseTransfer($responseContainer);

        return $responseTransfer;
    }

    /**
     * @param \Orm\Zed\Payone\Persistence\SpyPaymentPayone $paymentEntity
     * @param \SprykerEco\Zed\Payone\Business\Api\Request\Container\AuthorizationContainerInterface $requestContainer
     *
     * @return \SprykerEco\Zed\Payone\Business\Api\Response\Container\AuthorizationResponseContainer
     */
    protected function performAuthorizationRequest(SpyPaymentPayone $paymentEntity, AuthorizationContainerInterface $requestContainer)
    {
        $this->setStandardParameter($requestContainer);

        $apiLogEntity = $this->initializeApiLog($paymentEntity, $requestContainer);
        $rawResponse = $this->executionAdapter->sendRequest($requestContainer);
        $responseContainer = new AuthorizationResponseContainer($rawResponse);
        $this->updatePaymentAfterAuthorization($paymentEntity, $responseContainer);
        $this->updateApiLogAfterAuthorization($apiLogEntity, $responseContainer);
        $this->updatePaymentDetailAfterAuthorization($paymentEntity, $responseContainer);

        return $responseContainer;
    }

    /**
     * @param \Orm\Zed\Payone\Persistence\SpyPaymentPayone $paymentEntity
     *
     * @return \SprykerEco\Zed\Payone\Business\Payment\PaymentMethodMapperInterface
     */
    protected function getPaymentMethodMapper(SpyPaymentPayone $paymentEntity)
    {
        return $this->getRegisteredPaymentMethodMapper($paymentEntity->getPaymentMethod());
    }

    /**
     * @param int $orderId
     *
     * @return \Orm\Zed\Payone\Persistence\SpyPaymentPayone
     */
    protected function getPaymentEntity($orderId)
    {
        return $this->queryContainer->createPaymentById($orderId)->findOne();
    }

    /**
     * @param \Generated\Shared\Transfer\PayoneCaptureTransfer $captureTransfer
     *
     * @return \Generated\Shared\Transfer\CaptureResponseTransfer
     */
    public function capturePayment(PayoneCaptureTransfer $captureTransfer): CaptureResponseTransfer
    {
        $distributedPriceOrderTransfer = $this->orderPriceDistributor->distributeOrderPrice(
            $captureTransfer->getOrder()
        );
        $captureTransfer->setOrder($distributedPriceOrderTransfer);
        $captureTransfer->setAmount($distributedPriceOrderTransfer->getTotals()->getGrandTotal());

        $paymentEntity = $this->getPaymentEntity($captureTransfer->getPayment()->getFkSalesOrder());
        $paymentMethodMapper = $this->getPaymentMethodMapper($paymentEntity);

        $requestContainer = $paymentMethodMapper->mapPaymentToCapture($paymentEntity);

        if ($captureTransfer->getAmount()) {
            $requestContainer = $this->prepareOrderItems($captureTransfer->getOrder(), $requestContainer);
            $requestContainer = $this->prepareOrderShipment($captureTransfer->getOrder(), $requestContainer);
            $requestContainer = $this->prepareOrderDiscount($captureTransfer->getOrder(), $requestContainer);
            $requestContainer = $this->prepareOrderHandling($captureTransfer->getOrder(), $requestContainer);
        }

        /** @var \SprykerEco\Zed\Payone\Business\Api\Request\Container\CaptureContainer $requestContainer */
        if (!empty($captureTransfer->getSettleaccount())) {
            $businnessContainer = new BusinessContainer();
            $businnessContainer->setSettleAccount($captureTransfer->getSettleaccount());
            $requestContainer->setBusiness($businnessContainer);
        }

        if ($captureTransfer->getAmount() !== null) {
            $requestContainer->setAmount($captureTransfer->getAmount());
        }

        $this->setStandardParameter($requestContainer);

        $rawResponse = $this->executionAdapter->sendRequest($requestContainer);

        $apiLogEntity = $this->initializeApiLog($paymentEntity, $requestContainer);
        $responseContainer = new CaptureResponseContainer($rawResponse);
        $this->updateApiLogAfterCapture($apiLogEntity, $responseContainer);

        $responseMapper = new CaptureResponseMapper();

        return $responseMapper->getCaptureResponseTransfer($responseContainer);
    }

    /**
     * @param \Generated\Shared\Transfer\PayonePartialOperationRequestTransfer $payonePartialOperationRequestTransfer
     *
     * @return \Generated\Shared\Transfer\CaptureResponseTransfer
     */
    public function executePartialCapture(
        PayonePartialOperationRequestTransfer $payonePartialOperationRequestTransfer
    ): CaptureResponseTransfer {
        $distributedPriceOrderTransfer = $this->orderPriceDistributor->distributeOrderPrice(
            $payonePartialOperationRequestTransfer->getOrder()
        );
        $payonePartialOperationRequestTransfer->setOrder($distributedPriceOrderTransfer);

        $paymentEntity = $this->getPaymentEntity($payonePartialOperationRequestTransfer->getOrder()->getIdSalesOrder());
        $paymentMethodMapper = $this->getPaymentMethodMapper($paymentEntity);

        $requestContainer = $paymentMethodMapper->mapPaymentToCapture($paymentEntity);
        $requestContainer = $this->preparePartialCaptureOrderItems($payonePartialOperationRequestTransfer, $requestContainer);
        $captureAmount = $this->calculatePartialCaptureItemsAmount($payonePartialOperationRequestTransfer);

        $captureAmount += $this->getDeliveryCosts($payonePartialOperationRequestTransfer->getOrder());
        $requestContainer = $this->prepareOrderShipment($payonePartialOperationRequestTransfer->getOrder(), $requestContainer);

        $captureAmount += $this->calculateExpensesCost($payonePartialOperationRequestTransfer->getOrder());
        /** @var \SprykerEco\Zed\Payone\Business\Api\Request\Container\CaptureContainer $requestContainer */
        $requestContainer = $this->prepareOrderHandling($payonePartialOperationRequestTransfer->getOrder(), $requestContainer);

        $businessContainer = new BusinessContainer();
        $businessContainer->setSettleAccount(PayoneApiConstants::SETTLE_ACCOUNT_YES);
        $requestContainer->setBusiness($businessContainer);

        $requestContainer->setAmount($captureAmount);
        $this->setStandardParameter($requestContainer);

        $apiLogEntity = $this->initializeApiLog($paymentEntity, $requestContainer);

        $rawResponse = $this->executionAdapter->sendRequest($requestContainer);
        $responseContainer = new CaptureResponseContainer($rawResponse);

        $this->updateApiLogAfterCapture($apiLogEntity, $responseContainer);
        $this->updatePaymentPayoneOrderItemsWithStatus(
            $payonePartialOperationRequestTransfer,
            $this->getPartialCaptureStatus($responseContainer)
        );

        $responseMapper = new CaptureResponseMapper();

        return $responseMapper->getCaptureResponseTransfer($responseContainer);
    }

    /**
     * @param int $idPayment
     *
     * @return \Generated\Shared\Transfer\DebitResponseTransfer
     */
    public function debitPayment($idPayment)
    {
        $paymentEntity = $this->getPaymentEntity($idPayment);
        $paymentMethodMapper = $this->getPaymentMethodMapper($paymentEntity);

        $requestContainer = $paymentMethodMapper->mapPaymentToDebit($paymentEntity);
        $this->setStandardParameter($requestContainer);

        $paymentEntity = $this->findPaymentByTransactionId($paymentEntity->getTransactionId());
        $apiLogEntity = $this->initializeApiLog($paymentEntity, $requestContainer);

        $rawResponse = $this->executionAdapter->sendRequest($requestContainer);
        $responseContainer = new DebitResponseContainer($rawResponse);

        $this->updateApiLogAfterDebit($apiLogEntity, $responseContainer);

        $responseMapper = new DebitResponseMapper();
        $responseTransfer = $responseMapper->getDebitResponseTransfer($responseContainer);

        return $responseTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\PayoneCreditCardTransfer $creditCardData
     *
     * @return \Generated\Shared\Transfer\CreditCardCheckResponseTransfer
     */
    public function creditCardCheck(PayoneCreditCardTransfer $creditCardData)
    {
        /** @var \SprykerEco\Zed\Payone\Business\Payment\MethodMapper\CreditCardPseudo $paymentMethodMapper */
        $paymentMethodMapper = $this->getRegisteredPaymentMethodMapper($creditCardData->getPayment()->getPaymentMethod());
        $requestContainer = $paymentMethodMapper->mapCreditCardCheck($creditCardData);

        $this->setStandardParameter($requestContainer);

        $rawResponse = $this->executionAdapter->sendRequest($requestContainer);
        $responseContainer = new CreditCardCheckResponseContainer($rawResponse);

        $responseMapper = new CreditCardCheckResponseMapper();
        $responseTransfer = $responseMapper->getCreditCardCheckResponseTransfer($responseContainer);

        return $responseTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\PayoneBankAccountCheckTransfer $bankAccountCheckTransfer
     *
     * @return \Generated\Shared\Transfer\PayoneBankAccountCheckTransfer
     */
    public function bankAccountCheck(PayoneBankAccountCheckTransfer $bankAccountCheckTransfer)
    {
        /** @var \SprykerEco\Zed\Payone\Business\Payment\MethodMapper\DirectDebit $paymentMethodMapper */
        $paymentMethodMapper = $this->getRegisteredPaymentMethodMapper(PayoneApiConstants::PAYMENT_METHOD_ONLINE_BANK_TRANSFER);
        $requestContainer = $paymentMethodMapper->mapBankAccountCheck($bankAccountCheckTransfer);
        $this->setStandardParameter($requestContainer);

        $rawResponse = $this->executionAdapter->sendRequest($requestContainer);
        $responseContainer = new BankAccountCheckResponseContainer($rawResponse);

        $bankAccountCheckTransfer->setErrorCode($responseContainer->getErrorcode());
        $bankAccountCheckTransfer->setCustomerErrorMessage($responseContainer->getCustomermessage());
        $bankAccountCheckTransfer->setStatus($responseContainer->getStatus());
        $bankAccountCheckTransfer->setInternalErrorMessage($responseContainer->getErrormessage());

        return $bankAccountCheckTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\PayoneManageMandateTransfer $manageMandateTransfer
     *
     * @return \Generated\Shared\Transfer\PayoneManageMandateTransfer
     */
    public function manageMandate(PayoneManageMandateTransfer $manageMandateTransfer)
    {
        /** @var \SprykerEco\Zed\Payone\Business\Payment\MethodMapper\DirectDebit $paymentMethodMapper */
        $paymentMethodMapper = $this->getRegisteredPaymentMethodMapper(PayoneApiConstants::PAYMENT_METHOD_DIRECT_DEBIT);
        $requestContainer = $paymentMethodMapper->mapManageMandate($manageMandateTransfer);
        $this->setStandardParameter($requestContainer);

        $rawResponse = $this->executionAdapter->sendRequest($requestContainer);
        $responseContainer = new ManageMandateResponseContainer($rawResponse);

        $manageMandateTransfer->setErrorCode($responseContainer->getErrorcode());
        $manageMandateTransfer->setCustomerErrorMessage($responseContainer->getCustomermessage());
        $manageMandateTransfer->setStatus($responseContainer->getStatus());
        $manageMandateTransfer->setInternalErrorMessage($responseContainer->getErrormessage());
        $manageMandateTransfer->setMandateIdentification($responseContainer->getMandateIdentification());
        $manageMandateTransfer->setMandateText($responseContainer->getMandateText());
        $manageMandateTransfer->setIban($responseContainer->getIban());
        $manageMandateTransfer->setBic($responseContainer->getBic());

        return $manageMandateTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\PayoneGetFileTransfer $getFileTransfer
     *
     * @return \Generated\Shared\Transfer\PayoneGetFileTransfer
     */
    public function getFile(PayoneGetFileTransfer $getFileTransfer)
    {
        $responseContainer = new GetFileResponseContainer();
        $paymentEntity = $this->findPaymentByFileReferenceAndCustomerId(
            $getFileTransfer->getReference(),
            $getFileTransfer->getCustomerId()
        );

        if ($paymentEntity) {
            /** @var \SprykerEco\Zed\Payone\Business\Payment\MethodMapper\DirectDebit $paymentMethodMapper */
            $paymentMethodMapper = $this->getRegisteredPaymentMethodMapper(PayoneApiConstants::PAYMENT_METHOD_DIRECT_DEBIT);
            $requestContainer = $paymentMethodMapper->mapGetFile($getFileTransfer);
            $this->setStandardParameter($requestContainer);
            $rawResponse = $this->executionAdapter->sendRequest($requestContainer);
            $responseContainer->init($rawResponse);
        } else {
            $this->setAccessDeniedError($responseContainer);
        }

        $getFileTransfer->setRawResponse($responseContainer->getRawResponse());
        $getFileTransfer->setStatus($responseContainer->getStatus());
        $getFileTransfer->setErrorCode($responseContainer->getErrorcode());
        $getFileTransfer->setCustomerErrorMessage($responseContainer->getCustomermessage());
        $getFileTransfer->setInternalErrorMessage($responseContainer->getErrormessage());

        return $getFileTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\PayoneGetInvoiceTransfer $getInvoiceTransfer
     *
     * @return \Generated\Shared\Transfer\PayoneGetInvoiceTransfer
     */
    public function getInvoice(PayoneGetInvoiceTransfer $getInvoiceTransfer)
    {
        $responseContainer = new GetInvoiceResponseContainer();
        $paymentEntity = $this->findPaymentByInvoiceTitleAndCustomerId(
            $getInvoiceTransfer->getReference(),
            $getInvoiceTransfer->getCustomerId()
        );

        if ($paymentEntity) {
            /** @var \SprykerEco\Zed\Payone\Business\Payment\MethodMapper\Invoice $paymentMethodMapper */
            $paymentMethodMapper = $this->getRegisteredPaymentMethodMapper(PayoneApiConstants::PAYMENT_METHOD_INVOICE);
            $requestContainer = $paymentMethodMapper->mapGetInvoice($getInvoiceTransfer);
            $this->setStandardParameter($requestContainer);
            $rawResponse = $this->executionAdapter->sendRequest($requestContainer);
            $responseContainer->init($rawResponse);
        } else {
            $this->setAccessDeniedError($responseContainer);
        }

        $getInvoiceTransfer->setRawResponse($responseContainer->getRawResponse());
        $getInvoiceTransfer->setStatus($responseContainer->getStatus());
        $getInvoiceTransfer->setErrorCode($responseContainer->getErrorcode());
        $getInvoiceTransfer->setInternalErrorMessage($responseContainer->getErrormessage());

        return $getInvoiceTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\PayoneGetSecurityInvoiceTransfer $getSecurityInvoiceTransfer
     *
     * @return \Generated\Shared\Transfer\PayoneGetSecurityInvoiceTransfer
     */
    public function getSecurityInvoice(PayoneGetSecurityInvoiceTransfer $getSecurityInvoiceTransfer): PayoneGetSecurityInvoiceTransfer
    {
        $responseContainer = new GetSecurityInvoiceResponseContainer();
        $paymentEntity = $this->findPaymentByInvoiceTitleAndCustomerId(
            $getSecurityInvoiceTransfer->getReference(),
            $getSecurityInvoiceTransfer->getCustomerId()
        );

        if (!$paymentEntity) {
            $this->setAccessDeniedError($responseContainer);

            return $getSecurityInvoiceTransfer;
        }

        /** @var \SprykerEco\Zed\Payone\Business\Payment\MethodMapper\SecurityInvoice $paymentMethodMapper */
        $paymentMethodMapper = $this->getRegisteredPaymentMethodMapper(PayoneApiConstants::PAYMENT_METHOD_SECURITY_INVOICE);
        /** @var \SprykerEco\Zed\Payone\Business\Api\Request\Container\AbstractRequestContainer $requestContainer */
        $requestContainer = $paymentMethodMapper->mapGetSecurityInvoice($getSecurityInvoiceTransfer);
        $this->setStandardParameter($requestContainer);
        $rawResponse = $this->executionAdapter->sendRequest($requestContainer);
        $responseContainer->init($rawResponse);

        $getSecurityInvoiceTransfer->setRawResponse($responseContainer->getRawResponse());
        $getSecurityInvoiceTransfer->setStatus($responseContainer->getStatus());
        $getSecurityInvoiceTransfer->setErrorCode($responseContainer->getErrorcode());
        $getSecurityInvoiceTransfer->setInternalErrorMessage($responseContainer->getErrormessage());

        return $getSecurityInvoiceTransfer;
    }

    /**
     * @param int $transactionId
     *
     * @return string
     */
    public function getInvoiceTitle($transactionId)
    {
        return implode('-', [
            PayoneApiConstants::INVOICE_TITLE_PREFIX_INVOICE,
            $transactionId,
            0,
        ]);
    }

    /**
     * @param \Generated\Shared\Transfer\PayoneRefundTransfer $refundTransfer
     *
     * @return \Generated\Shared\Transfer\RefundResponseTransfer
     */
    public function refundPayment(PayoneRefundTransfer $refundTransfer)
    {
        $distributedPriceOrderTransfer = $this->orderPriceDistributor->distributeOrderPrice(
            $refundTransfer->getOrder()
        );
        $refundTransfer->setOrder($distributedPriceOrderTransfer);

        $payonePaymentTransfer = $refundTransfer->getPayment();

        $paymentEntity = $this->getPaymentEntity($payonePaymentTransfer->getFkSalesOrder());
        $paymentMethodMapper = $this->getPaymentMethodMapper($paymentEntity);
        $requestContainer = $paymentMethodMapper->mapPaymentToRefund($paymentEntity);
        $requestContainer->setAmount(0 - $paymentEntity->getSpyPaymentPayoneDetail()->getAmount());
        $requestContainer = $this->prepareOrderItems($refundTransfer->getOrder(), $requestContainer);
        $requestContainer = $this->prepareOrderShipment($refundTransfer->getOrder(), $requestContainer);
        $requestContainer = $this->prepareOrderDiscount($refundTransfer->getOrder(), $requestContainer);

        $this->setStandardParameter($requestContainer);

        $apiLogEntity = $this->initializeApiLog($paymentEntity, $requestContainer);

        $rawResponse = $this->executionAdapter->sendRequest($requestContainer);
        $responseContainer = new RefundResponseContainer($rawResponse);

        $this->updateApiLogAfterRefund($apiLogEntity, $responseContainer);

        $responseMapper = new RefundResponseMapper();
        $responseTransfer = $responseMapper->getRefundResponseTransfer($responseContainer);

        return $responseTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\PayonePartialOperationRequestTransfer $payonePartialOperationRequestTransfer
     *
     * @return \Generated\Shared\Transfer\RefundResponseTransfer
     */
    public function executePartialRefund(PayonePartialOperationRequestTransfer $payonePartialOperationRequestTransfer): RefundResponseTransfer
    {
        $paymentEntity = $this->getPaymentEntity($payonePartialOperationRequestTransfer->getOrder()->getIdSalesOrder());
        $paymentMethodMapper = $this->getPaymentMethodMapper($paymentEntity);
        $requestContainer = $paymentMethodMapper->mapPaymentToRefund($paymentEntity);

        $requestContainer->setAmount($payonePartialOperationRequestTransfer->getRefund()->getAmount() * -1);
        $requestContainer = $this->preparePartialRefundOrderItems($payonePartialOperationRequestTransfer, $requestContainer);
        $requestContainer = $this->preparePartialRefundExpenses($payonePartialOperationRequestTransfer, $requestContainer);
        $this->setStandardParameter($requestContainer);
        $apiLogEntity = $this->initializeApiLog($paymentEntity, $requestContainer);

        $rawResponse = $this->executionAdapter->sendRequest($requestContainer);
        $responseContainer = new RefundResponseContainer($rawResponse);

        $this->updateApiLogAfterRefund($apiLogEntity, $responseContainer);
        $this->updatePaymentPayoneOrderItemsWithStatus(
            $payonePartialOperationRequestTransfer,
            $this->getPartialRefundStatus($responseContainer)
        );

        $responseMapper = new RefundResponseMapper();

        return $responseMapper->getRefundResponseTransfer($responseContainer);
    }

    /**
     * @param \Generated\Shared\Transfer\PayonePartialOperationRequestTransfer $payonePartialOperationRequestTransfer
     * @param string $refundStatus
     *
     * @return void
     */
    protected function updatePaymentPayoneOrderItemsWithStatus(
        PayonePartialOperationRequestTransfer $payonePartialOperationRequestTransfer,
        string $refundStatus
    ): void {
        $payoneOrderItemFilterTransfer = (new PayoneOrderItemFilterTransfer())
            ->setIdSalesOrder($payonePartialOperationRequestTransfer->getOrder()->getIdSalesOrder())
            ->setSalesOrderItemIds($payonePartialOperationRequestTransfer->getSalesOrderItemIds());

        $payoneOrderItemTransfers = $this->payoneRepository->findPaymentPayoneOrderItemByFilter($payoneOrderItemFilterTransfer);

        foreach ($payoneOrderItemTransfers as $payoneOrderItemTransfer) {
            $payoneOrderItemTransfer->setStatus($refundStatus);
            $this->payoneEntityManager->updatePaymentPayoneOrderItem($payoneOrderItemTransfer);
        }
    }

    /**
     * @param \SprykerEco\Zed\Payone\Business\Api\Response\Container\RefundResponseContainer $responseContainer
     *
     * @return string
     */
    protected function getPartialRefundStatus(RefundResponseContainer $responseContainer): string
    {
        if ($responseContainer->getStatus() === PayoneApiConstants::RESPONSE_TYPE_APPROVED) {
            return PayoneTransactionStatusConstants::STATUS_REFUND_APPROVED;
        }

        return PayoneTransactionStatusConstants::STATUS_REFUND_FAILED;
    }

    /**
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return \Generated\Shared\Transfer\PayonePaymentTransfer
     */
    protected function getPayment(OrderTransfer $orderTransfer)
    {
        $payment = $this->queryContainer->createPaymentByOrderId($orderTransfer->getIdSalesOrder())->findOne();
        $paymentDetail = $payment->getSpyPaymentPayoneDetail();

        $paymentDetailTransfer = new PaymentDetailTransfer();
        $paymentDetailTransfer->fromArray($paymentDetail->toArray(), true);

        $paymentTransfer = new PayonePaymentTransfer();
        $paymentTransfer->fromArray($payment->toArray(), true);
        $paymentTransfer->setPaymentDetail($paymentDetailTransfer);

        return $paymentTransfer;
    }

    /**
     * @param \Orm\Zed\Payone\Persistence\SpyPaymentPayone $paymentEntity
     * @param \SprykerEco\Zed\Payone\Business\Api\Response\Container\AuthorizationResponseContainer $responseContainer
     *
     * @return void
     */
    protected function updatePaymentAfterAuthorization(SpyPaymentPayone $paymentEntity, AuthorizationResponseContainer $responseContainer)
    {
        $paymentEntity->setTransactionId($responseContainer->getTxid());
        $paymentEntity->save();
    }

    /**
     * @param string $transactionId
     *
     * @return \Orm\Zed\Payone\Persistence\SpyPaymentPayone
     */
    protected function findPaymentByTransactionId($transactionId)
    {
        return $this->queryContainer->createPaymentByTransactionIdQuery($transactionId)->findOne();
    }

    /**
     * @param string $invoiceTitle
     * @param int $customerId
     *
     * @return \Orm\Zed\Payone\Persistence\SpyPaymentPayone
     */
    protected function findPaymentByInvoiceTitleAndCustomerId($invoiceTitle, $customerId)
    {
        return $this->queryContainer->createPaymentByInvoiceTitleAndCustomerIdQuery($invoiceTitle, $customerId)->findOne();
    }

    /**
     * @param string $fileReference
     * @param int $customerId
     *
     * @return \Orm\Zed\Payone\Persistence\SpyPaymentPayone
     */
    protected function findPaymentByFileReferenceAndCustomerId($fileReference, $customerId)
    {
        return $this->queryContainer->createPaymentByFileReferenceAndCustomerIdQuery($fileReference, $customerId)->findOne();
    }

    /**
     * @param \Orm\Zed\Payone\Persistence\SpyPaymentPayone $paymentEntity
     * @param \SprykerEco\Zed\Payone\Business\Api\Request\Container\AbstractRequestContainer $container
     *
     * @return \Orm\Zed\Payone\Persistence\SpyPaymentPayoneApiLog
     */
    protected function initializeApiLog(SpyPaymentPayone $paymentEntity, AbstractRequestContainer $container)
    {
        $entity = new SpyPaymentPayoneApiLog();
        $entity->setSpyPaymentPayone($paymentEntity);
        $entity->setRequest($container->getRequest());
        $entity->setMode($container->getMode());
        $entity->setMerchantId($container->getMid());
        $entity->setPortalId($container->getPortalid());
        if ($container instanceof CaptureContainer || $container instanceof RefundContainer || $container instanceof DebitContainer) {
            $entity->setSequenceNumber($container->getSequenceNumber());
        }
        // Logging request data for debug
        $entity->setRawRequest(json_encode($container->toArray()));
        $entity->save();

        return $entity;
    }

    /**
     * @param \Orm\Zed\Payone\Persistence\SpyPaymentPayoneApiLog $apiLogEntity
     * @param \SprykerEco\Zed\Payone\Business\Api\Response\Container\AuthorizationResponseContainer $responseContainer
     *
     * @return void
     */
    protected function updateApiLogAfterAuthorization(SpyPaymentPayoneApiLog $apiLogEntity, AuthorizationResponseContainer $responseContainer)
    {
        $apiLogEntity->setStatus($responseContainer->getStatus());
        $apiLogEntity->setUserId($responseContainer->getUserid());
        $apiLogEntity->setTransactionId($responseContainer->getTxid());
        $apiLogEntity->setErrorMessageInternal($responseContainer->getErrormessage());
        $apiLogEntity->setErrorMessageUser($responseContainer->getCustomermessage());
        $apiLogEntity->setErrorCode($responseContainer->getErrorcode());
        $apiLogEntity->setRedirectUrl($responseContainer->getRedirecturl());
        $apiLogEntity->setSequenceNumber(0);

        $apiLogEntity->setRawResponse(json_encode($responseContainer->toArray()));
        $apiLogEntity->save();
    }

    /**
     * @param \Orm\Zed\Payone\Persistence\SpyPaymentPayoneApiLog $apiLogEntity
     * @param \SprykerEco\Zed\Payone\Business\Api\Response\Container\CaptureResponseContainer $responseContainer
     *
     * @return void
     */
    protected function updateApiLogAfterCapture(SpyPaymentPayoneApiLog $apiLogEntity, CaptureResponseContainer $responseContainer)
    {
        $apiLogEntity->setStatus($responseContainer->getStatus());
        $apiLogEntity->setTransactionId($responseContainer->getTxid());
        $apiLogEntity->setErrorMessageInternal($responseContainer->getErrormessage());
        $apiLogEntity->setErrorMessageUser($responseContainer->getCustomermessage());
        $apiLogEntity->setErrorCode($responseContainer->getErrorcode());

        $apiLogEntity->setRawResponse(json_encode($responseContainer->toArray()));
        $apiLogEntity->save();
    }

    /**
     * @param \Orm\Zed\Payone\Persistence\SpyPaymentPayoneApiLog $apiLogEntity
     * @param \SprykerEco\Zed\Payone\Business\Api\Response\Container\DebitResponseContainer $responseContainer
     *
     * @return void
     */
    protected function updateApiLogAfterDebit(SpyPaymentPayoneApiLog $apiLogEntity, DebitResponseContainer $responseContainer)
    {
        $apiLogEntity->setStatus($responseContainer->getStatus());
        $apiLogEntity->setTransactionId($responseContainer->getTxid());
        $apiLogEntity->setErrorMessageInternal($responseContainer->getErrormessage());
        $apiLogEntity->setErrorMessageUser($responseContainer->getCustomermessage());
        $apiLogEntity->setErrorCode($responseContainer->getErrorcode());

        $apiLogEntity->setRawResponse(json_encode($responseContainer->toArray()));
        $apiLogEntity->save();
    }

    /**
     * @param \Orm\Zed\Payone\Persistence\SpyPaymentPayoneApiLog $apiLogEntity
     * @param \SprykerEco\Zed\Payone\Business\Api\Response\Container\RefundResponseContainer $responseContainer
     *
     * @return void
     */
    protected function updateApiLogAfterRefund(SpyPaymentPayoneApiLog $apiLogEntity, RefundResponseContainer $responseContainer)
    {
        $apiLogEntity->setTransactionId($responseContainer->getTxid());
        $apiLogEntity->setStatus($responseContainer->getStatus());
        $apiLogEntity->setErrorMessageInternal($responseContainer->getErrormessage());
        $apiLogEntity->setErrorMessageUser($responseContainer->getCustomermessage());
        $apiLogEntity->setErrorCode($responseContainer->getErrorcode());

        $apiLogEntity->setRawResponse(json_encode($responseContainer->toArray()));
        $apiLogEntity->save();
    }

    /**
     * @param \SprykerEco\Zed\Payone\Business\Api\Request\Container\AbstractRequestContainer $container
     *
     * @return void
     */
    protected function setStandardParameter(AbstractRequestContainer $container)
    {
        $container->setApiVersion(PayoneApiConstants::API_VERSION_3_9);
        $container->setEncoding($this->standardParameter->getEncoding());
        $container->setKey($this->hashGenerator->hash($this->standardParameter->getKey()));
        $container->setMid($this->standardParameter->getMid());
        $container->setPortalid($this->standardParameter->getPortalId());
        $container->setMode($this->modeDetector->getMode());
        $container->setIntegratorName(PayoneApiConstants::INTEGRATOR_NAME_SPRYKER);
        $container->setIntegratorVersion(PayoneApiConstants::INTEGRATOR_VERSION_3_0_0);
        $container->setSolutionName(PayoneApiConstants::SOLUTION_NAME_SPRYKER);
        $container->setSolutionVersion(PayoneApiConstants::SOLUTION_VERSION_3_0_0);
    }

    /**
     * @param \SprykerEco\Zed\Payone\Business\Api\Response\Container\AbstractResponseContainer $container
     *
     * @return void
     */
    protected function setAccessDeniedError(AbstractResponseContainer $container)
    {
        $container->setStatus(PayoneApiConstants::RESPONSE_TYPE_ERROR);
        $container->setErrormessage(static::ERROR_ACCESS_DENIED_MESSAGE);
        $container->setCustomermessage(static::ERROR_ACCESS_DENIED_MESSAGE);
    }

    /**
     * @param int $idOrder
     *
     * @return \Generated\Shared\Transfer\PaymentDetailTransfer
     */
    public function getPaymentDetail($idOrder)
    {
        $paymentEntity = $this->queryContainer->createPaymentByOrderId($idOrder)->findOne();
        $paymentDetailEntity = $paymentEntity->getSpyPaymentPayoneDetail();
        $paymentDetailTransfer = new PaymentDetailTransfer();
        $paymentDetailTransfer->fromArray($paymentDetailEntity->toArray(), true);

        return $paymentDetailTransfer;
    }

    /**
     * Gets payment logs (both api and transaction status) for specific orders in chronological order.
     *
     * @param \Propel\Runtime\Collection\ObjectCollection|\ArrayObject $orders
     *
     * @return \Generated\Shared\Transfer\PayonePaymentLogCollectionTransfer
     */
    public function getPaymentLogs($orders)
    {
        $apiLogs = $this->queryContainer->createApiLogsByOrderIds($orders)->find()->getData();

        $transactionStatusLogs = $this->queryContainer->createTransactionStatusLogsByOrderIds($orders)->find()->getData();

        $logs = [];
        /** @var \Orm\Zed\Payone\Persistence\SpyPaymentPayoneApiLog $apiLog */
        foreach ($apiLogs as $apiLog) {
            $key = $apiLog->getCreatedAt()->format('Y-m-d\TH:i:s\Z') . 'a' . $apiLog->getIdPaymentPayoneApiLog();
            $payonePaymentLogTransfer = new PayonePaymentLogTransfer();
            $payonePaymentLogTransfer->fromArray($apiLog->toArray(), true);
            $payonePaymentLogTransfer->setLogType(self::LOG_TYPE_API_LOG);
            $logs[$key] = $payonePaymentLogTransfer;
        }
        /** @var \Orm\Zed\Payone\Persistence\SpyPaymentPayoneTransactionStatusLog $transactionStatusLog */
        foreach ($transactionStatusLogs as $transactionStatusLog) {
            $key = $transactionStatusLog->getCreatedAt()->format('Y-m-d\TH:i:s\Z') . 't' . $transactionStatusLog->getIdPaymentPayoneTransactionStatusLog();
            $payonePaymentLogTransfer = new PayonePaymentLogTransfer();
            $payonePaymentLogTransfer->fromArray($transactionStatusLog->toArray(), true);
            $payonePaymentLogTransfer->setLogType(self::LOG_TYPE_TRANSACTION_STATUS_LOG);
            $logs[$key] = $payonePaymentLogTransfer;
        }

        ksort($logs);

        $payonePaymentLogCollectionTransfer = new PayonePaymentLogCollectionTransfer();

        foreach ($logs as $log) {
            $payonePaymentLogCollectionTransfer->addPaymentLog($log);
        }

        return $payonePaymentLogCollectionTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\PayoneCreditCardCheckRequestDataTransfer $creditCardCheckRequestDataTransfer
     *
     * @return array
     */
    public function getCreditCardCheckRequestData(PayoneCreditCardCheckRequestDataTransfer $creditCardCheckRequestDataTransfer)
    {
        $this->standardParameter->fromArray($creditCardCheckRequestDataTransfer->toArray(), true);

        $creditCardCheck = new CreditCardCheck($this->standardParameter, $this->hashGenerator, $this->modeDetector);

        $data = $creditCardCheck->mapCreditCardCheckData();

        return $data->toArray();
    }

    /**
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return bool
     */
    public function isRefundPossible(OrderTransfer $orderTransfer)
    {
        $paymentTransfer = $this->getPayment($orderTransfer);

        if (!$this->isPaymentDataRequired($orderTransfer)) {
            return true;
        }

        $paymentDetailTransfer = $paymentTransfer->getPaymentDetail();

        return $paymentDetailTransfer->getBic() && $paymentDetailTransfer->getIban();
    }

    /**
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return bool
     */
    public function isPaymentDataRequired(OrderTransfer $orderTransfer)
    {
        $paymentTransfer = $this->getPayment($orderTransfer);

        // Return early if we don't need the iban or bic data
        $paymentMethod = $paymentTransfer->getPaymentMethod();
        $whiteList = [
            PayoneApiConstants::PAYMENT_METHOD_E_WALLET,
            PayoneApiConstants::PAYMENT_METHOD_CREDITCARD_PSEUDO,
            PayoneApiConstants::PAYMENT_METHOD_ONLINE_BANK_TRANSFER,
        ];

        if (in_array($paymentMethod, $whiteList)) {
            return false;
        }

        return true;
    }

    /**
     * @param \Generated\Shared\Transfer\QuoteTransfer $quoteTransfer
     * @param \Generated\Shared\Transfer\CheckoutResponseTransfer $checkoutResponse
     *
     * @return \Generated\Shared\Transfer\CheckoutResponseTransfer
     */
    public function executePostSaveHook(QuoteTransfer $quoteTransfer, CheckoutResponseTransfer $checkoutResponse): CheckoutResponseTransfer
    {
        if ($quoteTransfer->getPayment()->getPaymentProvider() !== PayoneConfig::PROVIDER_NAME) {
            return $checkoutResponse;
        }

        $checkoutResponse = $this->checkApiLogRedirectAndError($quoteTransfer, $checkoutResponse);

        return $this->checkApiAuthorizationRedirectAndError($quoteTransfer, $checkoutResponse);
    }

    /**
     * @deprecated Use {@link \SprykerEco\Zed\Payone\Business\Payment\PaymentManager::executePostSaveHook()} instead.
     *
     * @param \Generated\Shared\Transfer\QuoteTransfer $quoteTransfer
     * @param \Generated\Shared\Transfer\CheckoutResponseTransfer $checkoutResponse
     *
     * @return \Generated\Shared\Transfer\CheckoutResponseTransfer
     */
    public function postSaveHook(QuoteTransfer $quoteTransfer, CheckoutResponseTransfer $checkoutResponse)
    {
        if ($quoteTransfer->getPayment()->getPaymentProvider() !== PayoneConfig::PROVIDER_NAME) {
            return $checkoutResponse;
        }

        return $this->checkApiLogRedirectAndError($quoteTransfer, $checkoutResponse);
    }

    /**
     * @deprecated Use {@link \SprykerEco\Zed\Payone\Business\Payment\PaymentManager::executePostSaveHook()} instead.
     *
     * @param \Generated\Shared\Transfer\QuoteTransfer $quoteTransfer
     * @param \Generated\Shared\Transfer\CheckoutResponseTransfer $checkoutResponse
     *
     * @return \Generated\Shared\Transfer\CheckoutResponseTransfer
     */
    public function executeCheckoutPostSaveHook(
        QuoteTransfer $quoteTransfer,
        CheckoutResponseTransfer $checkoutResponse
    ): CheckoutResponseTransfer {
        if ($quoteTransfer->getPayment()->getPaymentProvider() !== PayoneConfig::PROVIDER_NAME) {
            return $checkoutResponse;
        }

        return $this->checkApiAuthorizationRedirectAndError($quoteTransfer, $checkoutResponse);
    }

    /**
     * @param \Generated\Shared\Transfer\QuoteTransfer $quoteTransfer
     * @param \Generated\Shared\Transfer\CheckoutResponseTransfer $checkoutResponse
     *
     * @return \Generated\Shared\Transfer\CheckoutResponseTransfer
     */
    protected function checkApiLogRedirectAndError(QuoteTransfer $quoteTransfer, CheckoutResponseTransfer $checkoutResponse): CheckoutResponseTransfer
    {
        $apiLogsQuery = $this->queryContainer->createLastApiLogsByOrderId($quoteTransfer->getPayment()->getPayone()->getFkSalesOrder());
        $apiLog = $apiLogsQuery->findOne();

        if ($apiLog) {
            $redirectUrl = $apiLog->getRedirectUrl();

            if ($redirectUrl !== null) {
                $checkoutResponse->setIsExternalRedirect(true);
                $checkoutResponse->setRedirectUrl($redirectUrl);
            }

            $errorCode = $apiLog->getErrorCode();

            if ($errorCode) {
                $error = new CheckoutErrorTransfer();
                $error->setMessage($apiLog->getErrorMessageUser());
                $error->setErrorCode($errorCode);
                $checkoutResponse->addError($error);
                $checkoutResponse->setIsSuccess(false);
            }
        }

        return $checkoutResponse;
    }

    /**
     * @param \Generated\Shared\Transfer\QuoteTransfer $quoteTransfer
     * @param \Generated\Shared\Transfer\CheckoutResponseTransfer $checkoutResponse
     *
     * @return \Generated\Shared\Transfer\CheckoutResponseTransfer
     */
    protected function checkApiAuthorizationRedirectAndError(QuoteTransfer $quoteTransfer, CheckoutResponseTransfer $checkoutResponse): CheckoutResponseTransfer
    {
        $paymentEntity = $this->getPaymentEntity($checkoutResponse->getSaveOrder()->getIdSalesOrder());
        $paymentMethodMapper = $this->getPaymentMethodMapper($paymentEntity);
        $requestContainer = $this->getPostSaveHookRequestContainer($paymentMethodMapper, $paymentEntity, $quoteTransfer);
        $responseContainer = $this->performAuthorizationRequest($paymentEntity, $requestContainer);

        if ($responseContainer->getErrorcode()) {
            $checkoutErrorTransfer = new CheckoutErrorTransfer();
            $checkoutErrorTransfer->setMessage($responseContainer->getCustomermessage());
            $checkoutErrorTransfer->setErrorCode($responseContainer->getErrorcode());
            $checkoutResponse->addError($checkoutErrorTransfer);
            $checkoutResponse->setIsSuccess(false);

            return $checkoutResponse;
        }

        if (!$responseContainer->getRedirecturl()) {
            return $checkoutResponse;
        }

        $checkoutResponse->setIsExternalRedirect(true);
        $checkoutResponse->setRedirectUrl($responseContainer->getRedirecturl());

        return $checkoutResponse;
    }

    /**
     * @param \Generated\Shared\Transfer\PaymentDetailTransfer $paymentDataTransfer
     * @param int $idOrder
     *
     * @return void
     */
    public function updatePaymentDetail(PaymentDetailTransfer $paymentDataTransfer, $idOrder)
    {
        $paymentEntity = $this->queryContainer->createPaymentByOrderId($idOrder)->findOne();
        $paymentDetailEntity = $paymentEntity->getSpyPaymentPayoneDetail();

        $paymentDetailEntity->fromArray($paymentDataTransfer->toArray());

        $paymentDetailEntity->save();
    }

    /**
     * @param \Orm\Zed\Payone\Persistence\SpyPaymentPayone $paymentEntity
     * @param \SprykerEco\Zed\Payone\Business\Api\Response\Container\AuthorizationResponseContainer $responseContainer
     *
     * @return void
     */
    protected function updatePaymentDetailAfterAuthorization(SpyPaymentPayone $paymentEntity, AuthorizationResponseContainer $responseContainer)
    {
        $paymentDetailEntity = $paymentEntity->getSpyPaymentPayoneDetail();

        $paymentDetailEntity->setClearingBankAccountHolder($responseContainer->getClearingBankaccountholder());
        $paymentDetailEntity->setClearingBankCountry($responseContainer->getClearingBankcountry());
        $paymentDetailEntity->setClearingBankAccount($responseContainer->getClearingBankaccount());
        $paymentDetailEntity->setClearingBankCode($responseContainer->getClearingBankcode());
        $paymentDetailEntity->setClearingBankIban($responseContainer->getClearingBankiban());
        $paymentDetailEntity->setClearingBankBic($responseContainer->getClearingBankbic());
        $paymentDetailEntity->setClearingBankCity($responseContainer->getClearingBankcity());
        $paymentDetailEntity->setClearingBankName($responseContainer->getClearingBankname());

        if ($responseContainer->getMandateIdentification()) {
            $paymentDetailEntity->setMandateIdentification($responseContainer->getMandateIdentification());
        }

        if ($paymentEntity->getPaymentMethod() == PayoneApiConstants::PAYMENT_METHOD_INVOICE) {
            $paymentDetailEntity->setInvoiceTitle($this->getInvoiceTitle($paymentEntity->getTransactionId()));
        }

        $paymentDetailEntity->save();
    }

    /**
     * @param \Generated\Shared\Transfer\PayoneInitPaypalExpressCheckoutRequestTransfer $requestTransfer
     *
     * @return \Generated\Shared\Transfer\PayonePaypalExpressCheckoutGenericPaymentResponseTransfer
     */
    public function initPaypalExpressCheckout(PayoneInitPaypalExpressCheckoutRequestTransfer $requestTransfer)
    {
        /** @var \SprykerEco\Zed\Payone\Business\Payment\GenericPaymentMethodMapperInterface $paymentMethodMapper */
        $paymentMethodMapper = $this->getRegisteredPaymentMethodMapper(
            PayoneApiConstants::PAYMENT_METHOD_PAYPAL_EXPRESS_CHECKOUT
        );
        $baseGenericPaymentContainer = $paymentMethodMapper->createBaseGenericPaymentContainer();
        $baseGenericPaymentContainer->getPaydata()->setAction(PayoneApiConstants::PAYONE_EXPRESS_CHECKOUT_SET_ACTION);
        $requestContainer = $paymentMethodMapper->mapRequestTransferToGenericPayment(
            $baseGenericPaymentContainer,
            $requestTransfer
        );
        $responseTransfer = $this->performGenericRequest($requestContainer);

        return $responseTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\QuoteTransfer $quoteTransfer
     *
     * @return \Generated\Shared\Transfer\PayonePaypalExpressCheckoutGenericPaymentResponseTransfer
     */
    public function getPaypalExpressCheckoutDetails(QuoteTransfer $quoteTransfer)
    {
        /** @var \SprykerEco\Zed\Payone\Business\Payment\GenericPaymentMethodMapperInterface $paymentMethodMapper */
        $paymentMethodMapper = $this->getRegisteredPaymentMethodMapper(
            PayoneApiConstants::PAYMENT_METHOD_PAYPAL_EXPRESS_CHECKOUT
        );

        $baseGenericPaymentContainer = $paymentMethodMapper->createBaseGenericPaymentContainer();
        $baseGenericPaymentContainer->getPaydata()->setAction(
            PayoneApiConstants::PAYONE_EXPRESS_CHECKOUT_GET_DETAILS_ACTION
        );
        $requestContainer = $paymentMethodMapper->mapQuoteTransferToGenericPayment(
            $baseGenericPaymentContainer,
            $quoteTransfer
        );
        $responseTransfer = $this->performGenericRequest($requestContainer);

        return $responseTransfer;
    }

    /**
     * @param \SprykerEco\Zed\Payone\Business\Api\Request\Container\GenericPaymentContainer $requestContainer
     *
     * @return \Generated\Shared\Transfer\PayonePaypalExpressCheckoutGenericPaymentResponseTransfer
     */
    protected function performGenericRequest(GenericPaymentContainer $requestContainer)
    {
        $this->setStandardParameter($requestContainer);

        $rawResponse = $this->executionAdapter->sendRequest($requestContainer);
        $responseContainer = new GenericPaymentResponseContainer($rawResponse);
        $responseTransfer = new PayonePaypalExpressCheckoutGenericPaymentResponseTransfer();
        $responseTransfer->setRedirectUrl($responseContainer->getRedirectUrl());
        $responseTransfer->setWorkOrderId($responseContainer->getWorkOrderId());
        $responseTransfer->setRawResponse(json_encode($rawResponse));
        $responseTransfer->setStatus($responseContainer->getStatus());
        $responseTransfer->setCustomerMessage($responseContainer->getCustomermessage());
        $responseTransfer->setErrorMessage($responseContainer->getErrormessage());
        $responseTransfer->setErrorCode($responseContainer->getErrorcode());
        $responseTransfer->setEmail($responseContainer->getEmail());
        $responseTransfer->setShippingFirstName($responseContainer->getShippingFirstname());
        $responseTransfer->setShippingLastName($responseContainer->getShippingLastname());
        $responseTransfer->setShippingCompany($responseContainer->getShippingCompany());
        $responseTransfer->setShippingCountry($responseContainer->getShippingCountry());
        $responseTransfer->setShippingState($responseContainer->getShippingState());
        $responseTransfer->setShippingStreet($responseContainer->getShippingStreet());
        $responseTransfer->setShippingAddressAdition($responseContainer->getShippingAddressaddition());
        $responseTransfer->setShippingCity($responseContainer->getShippingCity());
        $responseTransfer->setShippingZip($responseContainer->getShippingZip());

        return $responseTransfer;
    }

    /**
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     * @param \SprykerEco\Zed\Payone\Business\Api\Request\Container\AbstractRequestContainer $container
     *
     * @return \SprykerEco\Zed\Payone\Business\Api\Request\Container\AbstractRequestContainer
     */
    protected function prepareOrderItems(OrderTransfer $orderTransfer, AbstractRequestContainer $container): AbstractRequestContainer
    {
        $arrayIt = [];
        $arrayId = [];
        $arrayPr = [];
        $arrayNo = [];
        $arrayDe = [];
        $arrayVa = [];

        $key = 1;

        foreach ($orderTransfer->getItems() as $itemTransfer) {
            $arrayIt[$key] = PayoneApiConstants::INVOICING_ITEM_TYPE_GOODS;
            $arrayId[$key] = $itemTransfer->getSku();
            $arrayPr[$key] = $itemTransfer->getUnitGrossPrice();
            $arrayNo[$key] = $itemTransfer->getQuantity();
            $arrayDe[$key] = $itemTransfer->getName();
            $arrayVa[$key] = (int)$itemTransfer->getTaxRate();
            $key++;
        }

        $container->setIt($arrayIt);
        $container->setId($arrayId);
        $container->setPr($arrayPr);
        $container->setNo($arrayNo);
        $container->setDe($arrayDe);
        $container->setVa($arrayVa);

        return $container;
    }

    /**
     * @param \Generated\Shared\Transfer\PayonePartialOperationRequestTransfer $payonePartialOperationRequestTransfer
     * @param \SprykerEco\Zed\Payone\Business\Api\Request\Container\AbstractRequestContainer $container
     *
     * @return \SprykerEco\Zed\Payone\Business\Api\Request\Container\AbstractRequestContainer
     */
    protected function preparePartialRefundOrderItems(
        PayonePartialOperationRequestTransfer $payonePartialOperationRequestTransfer,
        AbstractRequestContainer $container
    ): AbstractRequestContainer {
        $arrayIt = [];
        $arrayId = [];
        $arrayPr = [];
        $arrayNo = [];
        $arrayDe = [];
        $arrayVa = [];

        $key = 1;

        foreach ($payonePartialOperationRequestTransfer->getOrder()->getItems() as $itemTransfer) {
            if (!in_array($itemTransfer->getIdSalesOrderItem(), $payonePartialOperationRequestTransfer->getSalesOrderItemIds())) {
                continue;
            }

            $arrayIt[$key] = PayoneApiConstants::INVOICING_ITEM_TYPE_GOODS;
            $arrayId[$key] = $itemTransfer->getSku();
            $arrayPr[$key] = $itemTransfer->getRefundableAmount();
            $arrayNo[$key] = $itemTransfer->getQuantity();
            $arrayDe[$key] = $itemTransfer->getName();
            $arrayVa[$key] = (int)$itemTransfer->getTaxRate();
            $key++;
        }

        $container->setIt($arrayIt);
        $container->setId($arrayId);
        $container->setPr($arrayPr);
        $container->setNo($arrayNo);
        $container->setDe($arrayDe);
        $container->setVa($arrayVa);

        return $container;
    }

    /**
     * @param \Generated\Shared\Transfer\PayonePartialOperationRequestTransfer $payonePartialOperationRequestTransfer
     * @param \SprykerEco\Zed\Payone\Business\Api\Request\Container\AbstractRequestContainer $container
     *
     * @return \SprykerEco\Zed\Payone\Business\Api\Request\Container\AbstractRequestContainer
     */
    protected function preparePartialRefundExpenses(
        PayonePartialOperationRequestTransfer $payonePartialOperationRequestTransfer,
        AbstractRequestContainer $container
    ): AbstractRequestContainer {
        $arrayIt = $container->getIt() ?? [];
        $arrayId = $container->getId() ?? [];
        $arrayPr = $container->getPr() ?? [];
        $arrayNo = $container->getNo() ?? [];
        $arrayDe = $container->getDe() ?? [];
        $arrayVa = $container->getVa() ?? [];

        $key = count($arrayId) + 1;

        foreach ($payonePartialOperationRequestTransfer->getRefund()->getExpenses() as $expenseTransfer) {
            $expenseType = PayoneApiConstants::INVOICING_ITEM_TYPE_HANDLING;
            if ($expenseTransfer->getType() === ShipmentConfig::SHIPMENT_EXPENSE_TYPE) {
                $expenseType = PayoneApiConstants::INVOICING_ITEM_TYPE_SHIPMENT;
            }

            $arrayIt[$key] = $expenseType;
            $arrayId[$key] = $expenseType;
            $arrayPr[$key] = $expenseTransfer->getRefundableAmount();
            $arrayNo[$key] = $expenseTransfer->getQuantity();
            $arrayDe[$key] = $expenseTransfer->getName();
            $arrayVa[$key] = (int)$expenseTransfer->getTaxRate();
            $key++;
        }

        $container->setIt($arrayIt);
        $container->setId($arrayId);
        $container->setPr($arrayPr);
        $container->setNo($arrayNo);
        $container->setDe($arrayDe);
        $container->setVa($arrayVa);

        return $container;
    }

    /**
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     * @param \SprykerEco\Zed\Payone\Business\Api\Request\Container\AbstractRequestContainer $container
     *
     * @return \SprykerEco\Zed\Payone\Business\Api\Request\Container\AbstractRequestContainer
     */
    protected function prepareOrderShipment(OrderTransfer $orderTransfer, AbstractRequestContainer $container): AbstractRequestContainer
    {
        $arrayIt = $container->getIt() ?? [];
        $arrayId = $container->getId() ?? [];
        $arrayPr = $container->getPr() ?? [];
        $arrayNo = $container->getNo() ?? [];
        $arrayDe = $container->getDe() ?? [];
        $arrayVa = $container->getVa() ?? [];

        $key = count($arrayId) + 1;

        $arrayIt[$key] = PayoneApiConstants::INVOICING_ITEM_TYPE_SHIPMENT;
        $arrayId[$key] = PayoneApiConstants::INVOICING_ITEM_TYPE_SHIPMENT;
        $arrayPr[$key] = $this->getDeliveryCosts($orderTransfer);
        $arrayNo[$key] = 1;
        $arrayDe[$key] = 'Shipment';
        $arrayVa[$key] = 0;

        $container->setIt($arrayIt);
        $container->setId($arrayId);
        $container->setPr($arrayPr);
        $container->setNo($arrayNo);
        $container->setDe($arrayDe);
        $container->setVa($arrayVa);

        return $container;
    }

    /**
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     * @param \SprykerEco\Zed\Payone\Business\Api\Request\Container\AbstractRequestContainer $container
     *
     * @return \SprykerEco\Zed\Payone\Business\Api\Request\Container\AbstractRequestContainer
     */
    protected function prepareOrderDiscount(OrderTransfer $orderTransfer, AbstractRequestContainer $container): AbstractRequestContainer
    {
        $arrayIt = $container->getIt() ?? [];
        $arrayId = $container->getId() ?? [];
        $arrayPr = $container->getPr() ?? [];
        $arrayNo = $container->getNo() ?? [];
        $arrayDe = $container->getDe() ?? [];
        $arrayVa = $container->getVa() ?? [];

        $key = count($arrayId) + 1;

        $arrayIt[$key] = PayoneApiConstants::INVOICING_ITEM_TYPE_VOUCHER;
        $arrayId[$key] = PayoneApiConstants::INVOICING_ITEM_TYPE_VOUCHER;
        $arrayPr[$key] = - $orderTransfer->getTotals()->getDiscountTotal();
        $arrayNo[$key] = 1;
        $arrayDe[$key] = 'Discount';
        $arrayVa[$key] = 0;

        $container->setIt($arrayIt);
        $container->setId($arrayId);
        $container->setPr($arrayPr);
        $container->setNo($arrayNo);
        $container->setDe($arrayDe);
        $container->setVa($arrayVa);

        return $container;
    }

    /**
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return int
     */
    protected function getDeliveryCosts(OrderTransfer $orderTransfer): int
    {
        foreach ($orderTransfer->getExpenses() as $expense) {
            if ($expense->getType() === static::SHIPMENT_EXPENSE_TYPE) {
                return $expense->getSumGrossPrice();
            }
        }

        return 0;
    }

    /**
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     * @param \SprykerEco\Zed\Payone\Business\Api\Request\Container\AbstractRequestContainer $container
     *
     * @return \SprykerEco\Zed\Payone\Business\Api\Request\Container\AbstractRequestContainer
     */
    protected function prepareOrderHandling(OrderTransfer $orderTransfer, AbstractRequestContainer $container): AbstractRequestContainer
    {
        $arrayIt = $container->getIt() ?? [];
        $arrayId = $container->getId() ?? [];
        $arrayPr = $container->getPr() ?? [];
        $arrayNo = $container->getNo() ?? [];
        $arrayDe = $container->getDe() ?? [];
        $arrayVa = $container->getVa() ?? [];

        $key = count($arrayId) + 1;

        foreach ($orderTransfer->getExpenses() as $expense) {
            if ($expense->getType() !== static::SHIPMENT_EXPENSE_TYPE) {
                $arrayIt[$key] = PayoneApiConstants::INVOICING_ITEM_TYPE_HANDLING;
                $arrayId[$key] = PayoneApiConstants::INVOICING_ITEM_TYPE_HANDLING;
                $arrayPr[$key] = $expense->getSumGrossPrice();
                $arrayNo[$key] = 1;
                $arrayDe[$key] = 'Handling';
                $arrayVa[$key] = 0;
                $key++;
            }
        }

        $container->setIt($arrayIt);
        $container->setId($arrayId);
        $container->setPr($arrayPr);
        $container->setNo($arrayNo);
        $container->setDe($arrayDe);
        $container->setVa($arrayVa);

        return $container;
    }

    /**
     * @param \Generated\Shared\Transfer\PayonePartialOperationRequestTransfer $payonePartialOperationRequestTransfer
     *
     * @return int
     */
    protected function calculatePartialCaptureItemsAmount(PayonePartialOperationRequestTransfer $payonePartialOperationRequestTransfer): int
    {
        $itemsAmount = 0;
        foreach ($payonePartialOperationRequestTransfer->getOrder()->getItems() as $itemTransfer) {
            if (in_array($itemTransfer->getIdSalesOrderItem(), $payonePartialOperationRequestTransfer->getSalesOrderItemIds())) {
                $itemsAmount += $itemTransfer->getSumPriceToPayAggregation();
            }
        }

        return $itemsAmount;
    }

    /**
     * @param \Generated\Shared\Transfer\PayonePartialOperationRequestTransfer $payonePartialOperationRequestTransfer
     * @param \SprykerEco\Zed\Payone\Business\Api\Request\Container\AbstractRequestContainer $container
     *
     * @return \SprykerEco\Zed\Payone\Business\Api\Request\Container\AbstractRequestContainer
     */
    protected function preparePartialCaptureOrderItems(
        PayonePartialOperationRequestTransfer $payonePartialOperationRequestTransfer,
        AbstractRequestContainer $container
    ): AbstractRequestContainer {
        $arrayIt = [];
        $arrayId = [];
        $arrayPr = [];
        $arrayNo = [];
        $arrayDe = [];
        $arrayVa = [];

        $key = 1;

        foreach ($payonePartialOperationRequestTransfer->getOrder()->getItems() as $itemTransfer) {
            if (!in_array($itemTransfer->getIdSalesOrderItem(), $payonePartialOperationRequestTransfer->getSalesOrderItemIds())) {
                continue;
            }

            $arrayIt[$key] = PayoneApiConstants::INVOICING_ITEM_TYPE_GOODS;
            $arrayId[$key] = substr($itemTransfer->getSku(), 0, 32);
            $arrayPr[$key] = $itemTransfer->getUnitPriceToPayAggregation();
            $arrayNo[$key] = $itemTransfer->getQuantity();
            $arrayDe[$key] = $itemTransfer->getName();
            $arrayVa[$key] = (int)$itemTransfer->getTaxRate();
            $key++;
        }

        $container->setIt($arrayIt);
        $container->setId($arrayId);
        $container->setPr($arrayPr);
        $container->setNo($arrayNo);
        $container->setDe($arrayDe);
        $container->setVa($arrayVa);

        return $container;
    }

    /**
     * @param \Generated\Shared\Transfer\OrderTransfer $orderTransfer
     *
     * @return int
     */
    protected function calculateExpensesCost(OrderTransfer $orderTransfer): int
    {
        $expensesCostAmount = 0;

        foreach ($orderTransfer->getExpenses() as $expense) {
            if ($expense->getType() !== static::SHIPMENT_EXPENSE_TYPE) {
                $expensesCostAmount += $expense->getSumGrossPrice();
            }
        }

        return $expensesCostAmount;
    }

    /**
     * @param \SprykerEco\Zed\Payone\Business\Api\Response\Container\CaptureResponseContainer $responseContainer
     *
     * @return string
     */
    protected function getPartialCaptureStatus(CaptureResponseContainer $responseContainer): string
    {
        if ($responseContainer->getStatus() === PayoneApiConstants::RESPONSE_TYPE_APPROVED) {
            return PayoneTransactionStatusConstants::STATUS_CAPTURE_APPROVED;
        }

        return PayoneTransactionStatusConstants::STATUS_CAPTURE_FAILED;
    }

    /**
     * @param \SprykerEco\Zed\Payone\Business\Payment\PaymentMethodMapperInterface $paymentMethodMapper
     * @param \Orm\Zed\Payone\Persistence\SpyPaymentPayone $paymentEntity
     * @param \Generated\Shared\Transfer\QuoteTransfer $quoteTransfer
     *
     * @return \SprykerEco\Zed\Payone\Business\Api\Request\Container\AuthorizationContainerInterface
     */
    protected function getPostSaveHookRequestContainer(
        PaymentMethodMapperInterface $paymentMethodMapper,
        SpyPaymentPayone $paymentEntity,
        QuoteTransfer $quoteTransfer
    ): AuthorizationContainerInterface {
        if (method_exists($paymentMethodMapper, 'mapPaymentToPreAuthorization')) {
            return $paymentMethodMapper->mapPaymentToPreAuthorization($paymentEntity);
        }

        $orderTransfer = (new OrderTransfer())
            ->setTotals(
                (new TotalsTransfer())
                    ->setGrandTotal($quoteTransfer->getTotals()->getGrandTotal())
            );

        return $paymentMethodMapper->mapPaymentToAuthorization($paymentEntity, $orderTransfer);
    }
}
