<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Maviance\Enkap\Model;



/**
 * Pay In Store payment method model
 */
class Enkap extends \Magento\Payment\Model\Method\AbstractMethod
{

    /**
     * Payment code
     *
     * @var string
     */
    protected $_code = 'enkap';
    const METHOD_CODE = 'enkap';

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isOffline = true;


  

}
