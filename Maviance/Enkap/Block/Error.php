<?php
namespace Maviance\Enkap\Block;

use Magento\Framework\View\Element\Template;
/**
* Baz block
*/
class Error extends Template

{
	
	protected $_order;
	  protected $_orderFactory;
	
	
  public function __construct(Template\Context $context,
								 \Magento\Sales\Model\OrderFactory $orderFactory,
								 array $data = [])
    {
		$this->_orderFactory = $orderFactory;
        parent::__construct($context, $data);
		$this->setValue();
       
    }
	public function setValue(){
		#$configHelper = $this->_objectManager->get('Maviance\Enkap\Helper\Data');
		
		 $arrayData = array('orderID' =>  $this->getRequest()->getParam('orderID'),
							'AuthCode' => $this->getRequest()->getParam('AuthCode'),
							'xid' => $this->getRequest()->getParam('xid'),
							'Response' => $this->getRequest()->getParam('Response'),
							'ProcReturnCode' => $this->getRequest()->getParam('ProcReturnCode'),
							'TransId' => $this->getRequest()->getParam('TransId'),
							'EXTRA_TRXDATE' => $this->getRequest()->getParam('EXTRA_TRXDATE'),
						);
						
						foreach($arrayData as $datakey=>$Data){
							$this->setData($datakey,$Data);
						}
	}
	
	public function _prepareLayout()
{
   //set page title
   $this->pageConfig->getTitle()->set(__('Payment error'));

   return parent::_prepareLayout();
}  

public function _getOrder($incrementId= null){
	
        if (!$this->_order) {
            $incrementId = $incrementId ? $incrementId : $this->_getCheckout()->getLastRealOrderId();
            $this->_order = $this->_orderFactory->create()->loadByIncrementId($incrementId);
        }
        return $this->_order;
    
}


}