<?php
namespace Maviance\Enkap\Controller\Payment;
class Response extends \Magento\Framework\App\Action\Action {
	protected $_checkoutSession;
	protected $_resultPageFactory;
	protected $layoutFactory;
	 /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;
	/**
     * Order object
     *
     * @var \Magento\Sales\Model\Order
     */
    protected $_order;
	 /**
     * @var \Psr\Log\LoggerInterface
     */
	  protected $_transportBuilder;
    protected $_layout;
    protected $_logger;
	private $redirectFactory;
	protected $messageManager;
	protected $liveUrl = 'https://api.enkap.cm/purchase/v1';
    protected $testUrl = 'https://api.enkap.maviance.info/payment/1.0.0';
    /**
     * Constructor
     * 
     * @param \Magento\Framework\App\Action\Context  $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     */
	 protected $resultRawFactory;
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
		 \Magento\Framework\Controller\Result\RawFactory $resultRawFactory,
		\Magento\Framework\View\Result\PageFactory $resultPageFactory,
		\Magento\Framework\Controller\Result\RedirectFactory $redirectFactory,
		 \Magento\Framework\View\LayoutFactory $layoutFactory,
		\Magento\Framework\Message\ManagerInterface $messageManager,
		 \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder,
        \Psr\Log\LoggerInterface $logger,
		 \Magento\Framework\View\LayoutInterface $layout
    )
    {
$this->_resultPageFactory = $resultPageFactory;
    	 $this->_checkoutSession = $checkoutSession;
        $this->_orderFactory = $orderFactory;
		  $this->redirectFactory = $redirectFactory;
		  $this->messageManager = $messageManager;
		    $this->layoutFactory = $layoutFactory;
			 $this->_transportBuilder= $transportBuilder;
			 $this->_storeManager = $storeManager;
	   $this->_layout = $layout;
	    $this->resultRawFactory = $resultRawFactory; 
        $this->_logger = $logger;
        parent::__construct($context);
    }
   protected function _getOrder($incrementId= null)
    {
        if (!$this->_order) {
            $incrementId = $incrementId ? $incrementId : $this->_getCheckout()->getLastRealOrderId();
            $this->_order = $this->_orderFactory->create()->loadByIncrementId($incrementId);
        }
        return $this->_order;
    }
	/**
     * Get frontend checkout session object
     *
     * @return \Magento\Checkout\Model\Session
     */
    protected function _getCheckout()
    {
        return $this->_checkoutSession;
    }
    /**
     * Execute view action
     * 
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
		$configHelper = $this->_objectManager->get('Maviance\Enkap\Helper\Data');
        if ($this->getRequest()->get("Response") == "Approved" && $this->getRequest()->get("ReturnOid")) {
            /**
             * Order succeeded
             */
			$orderId = $this->getRequest()->get("oid");
            $order =  $this->_getOrder($orderId);
            if ($order->isEmpty()) {
                $this->messageManager->addError( __("No order for processing found."));
				return $this->redirectFactory->create()->setPath('checkout/cart/index');
            }
            /**
             * Save transaction info
             */
            $transactionID = $this->getRequest()->get("TransId");
            $comment = $this->getRequest()->get("ErrMsg");
            $payment = $order->getPayment();
            $payment->setTransactionId($transactionID);
            switch ($this->getRequest()->get("trantype")) {
                case 'Auth':
                    $type = 'authorization';
                    break;
                case 'PreAuth':
                    $type = 'capture';
                    break;
                default:
                    $type = 'order';
                    break;
            }
            $transaction = $payment->addTransaction($type, null, false, $comment);
            $transaction->setParentTxnId($transactionID);
            $transaction->setIsClosed(true);
            $transaction->save();
            $order->save();
            /**
             * Change state
             */
            $order->setState(Mage_Sales_Model_Order::STATE_NEW, true, 'Payment Success.');
            $order->save();
            /**
             * Send email
             */
            $data['order'] = $order;
            $data['payment_html'] = $configHelper->getConfig('payment/enkap/title');
            $data['orderID'] = $this->getRequest()->get("oid");
            $data['AuthCode'] = $this->getRequest()->get("AuthCode");
            $data['xid'] = $this->getRequest()->get("xid");
            $data['Response'] = $this->getRequest()->get("Response");
            $data['ProcReturnCode'] = $this->getRequest()->get("ProcReturnCode");
            $data['TransId'] = $this->getRequest()->get("TransId");
            $data['EXTRA_TRXDATE'] = $this->getRequest()->get("EXTRA_TRXDATE");
			$template = $this->_transportBuilder->setTemplateIdentifier('enkap_payment_received')
				->setTemplateOptions([
					'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
					'store' => $this->_storeManager->getStore()->getId(),
				])->setTemplateVars($data);
            //$processedTemplate = utf8_decode($emailTemplate->getProcessedTemplate($data));
            $processedTemplate = mb_convert_encoding($template, 'ISO-8859-1', 'UTF-8');
            $mail = new \Zend_Mail('utf-8');
            $mail->setBodyHtml($processedTemplate);
            $mail->setFrom($configHelper->getConfig('trans_email/ident_sales/email'), $configHelper->getConfig('trans_email/ident_sales/name'))
                ->addTo($order->getBillingAddress()->getEmail(), $order->getBillingAddress()->getName())
                ->setSubject($this->__('Payment successful #') . $orderId);
            $mail->send();
            //Mage_Core_Controller_Varien_Action::_redirect('enkap/payment/success', array('_secure' => true));
            $this->_forward('success', NULL, NULL, $data);
            //Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/success', array('_secure' => true));
        } else {
            /**
             * Get current session
             */
            $session = $this->_getCheckout();
            /**
             * Order failed ...
             */
            $orderId = $this->getRequest()->get("oid");
            $order =  $this->_getOrder($orderId);
            if ($order->isEmpty()) {
               # $this->messageManager->addError( __("No order for processing found."));
				return $this->redirectFactory->create()->setPath('enkap/payment/error');
            }
            /**
             * Order failed, set status accordingly
             */
            if ($order->canCancel()) {
                $order->cancel();
            }
            $order->setState('canceled', true, "Payment failed: " . $this->getRequest()->get('mdErrorMsg') . ". - " . $this->getRequest()->get('Response'), false)->save();
			$order->setStatus('canceled');
            /**
             * Save transaction info
             */
            $transactionID = $this->getRequest()->get("TransId");
            $comment = $this->getRequest()->get("ErrMsg");
            $payment = $order->getPayment();
            $payment->setTransactionId($transactionID);
            $transaction = $payment->addTransaction('void', null, false, $comment);
            if ($transaction != null) {
                $transaction->setParentTxnId($transactionID);
                $transaction->setIsClosed(true);
                $transaction->save();
            }
            $order->save();
            /**
             * Reuse last quote
             */
            $quote = $this->_objectManager->create('Magento\Quote\Model\Quote')->load($order->getQuoteId());
            $quote->setIsActive(true)->setReservedOrderId(NULL)->save();
            $session->replaceQuote($quote);
            /**
             * Send email
             */
            $data['order'] = $order;
            $data['payment_html'] = $configHelper->getConfig('payment/enkap/title');
            $data['orderID'] = $this->getRequest()->get("oid");
            $data['AuthCode'] = $this->getRequest()->get("AuthCode");
            $data['xid'] = $this->getRequest()->get("xid");
            $data['Response'] = $this->getRequest()->get("Response");
            $data['ProcReturnCode'] = $this->getRequest()->get("ProcReturnCode");
            $data['TransId'] = $this->getRequest()->get("TransId");
            $data['EXTRA_TRXDATE'] = $this->getRequest()->get("EXTRA_TRXDATE");
			 $sender = [
			 'name' => $configHelper->getConfig('trans_email/ident_sales/name'),
			 'email' => $configHelper->getConfig('trans_email/ident_sales/email'),
			 ];
			$recipient = $order->getBillingAddress()->getEmail();
            $template = $this->_transportBuilder->setTemplateIdentifier('enkap_payment_failed')
				->setTemplateOptions([
					'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
					'store' => $this->_storeManager->getStore()->getId(),
				])->setTemplateVars($data)
			 	 ->setFrom($sender)
				 ->addTo($recipient)
				  ->setReplyTo($configHelper->getConfig('trans_email/ident_sales/email'))
				 ->getTransport();
			 $template->sendMessage();	 
            /**
             * Redirect to error page
             */
            $this->_forward('error', NULL, NULL, $data);
        }
    }
}
