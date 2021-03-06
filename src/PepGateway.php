<?php

namespace gdpa\PepGateway;

use gdpa\PepGateway\RSA\RSAProcessor;
use gdpa\PepGateway\RSA\RSAKeyType;
use gdpa\PepGateway\RSA\Parser;

class PepGateway
{
    protected $processor;
    protected $merchantCode;
    protected $terminalCode;
    protected $buyAction = 1003;
    protected $refundAction = 1004;
    protected $gatewayUrl = 'https://pep.shaparak.ir/gateway.aspx';
    protected $checkURL = 'https://pep.shaparak.ir/CheckTransactionResult.aspx';
    protected $verifyUrl = 'https://pep.shaparak.ir/VerifyPayment.aspx';
    protected $refundUrl = 'https://pep.shaparak.ir/doRefund.aspx';
    protected $isValid = 'https://185.60.32.41:16008/services/libsrv/verifyThirdV2';

    public function __construct($merchantCode, $terminalCode, $certificate)
    {
        $this->merchantCode = $merchantCode;
        $this->terminalCode = $terminalCode;
        $this->processor = new RSAProcessor($certificate,RSAKeyType::XMLFile);
    }

    /**
     * @param $invoiceNumber
     * @param $invoiceDate
     * @param $amount
     * @param $redirectAddress
     * @param $timestamp
     * @return array
     */
    public function buy($invoiceNumber, $invoiceDate, $amount, $redirectAddress, $timestamp)
    {
        $sign = base64_encode($this->sign($invoiceNumber, $invoiceDate, $amount, $redirectAddress, $timestamp, $this->buyAction));
        return [
            'formAction' => $this->gatewayUrl,
            'action' => $this->buyAction,
            'sign' => $sign,
            'merchantCode' => $this->merchantCode,
            'terminalCode' => $this->terminalCode,
            'invoiceNumber' => $invoiceNumber,
            'invoiceDate' => $invoiceDate,
            'amount' => $amount,
            'redirectAddress' => $redirectAddress,
            'timestamp' => $timestamp
        ];
    }

    /**
     * @param $invoiceNumber
     * @param $invoiceDate
     * @param $amount
     * @param $timestamp
     * @return array
     */
    public function verify($invoiceNumber, $invoiceDate, $amount, $timestamp)
    {
        $action = $this->gatewayUrl;
        $sign = base64_encode($this->sign($invoiceNumber, $invoiceDate, $amount, null, $timestamp, null));
        $fields = compact($action, $sign, $this->merchantCode, $this->terminalCode, $invoiceNumber, $invoiceDate, $amount, $timestamp);
        $result = Parser::post2https($fields, $this->verifyUrl);
        return Parser::makeXMLTree($result);
    }

    /**
     * @param $invoiceNumber
     * @param $invoiceDate
     * @param $amount
     * @param $timestamp
     * @return array
     */
    public function refund($invoiceNumber, $invoiceDate, $amount, $timestamp)
    {
        $sign = base64_encode($this->sign($invoiceNumber, $invoiceDate, $amount, null, $timestamp, $this->refundAction));
        $fields = compact($sign, $this->merchantCode, $this->terminalCode, $invoiceNumber, $invoiceDate, $amount, $timestamp);
        $result = Parser::post2https($fields, $this->refundUrl);
        return Parser::makeXMLTree($result);
    }

    /**
     * @param $TransactionReferenceID
     * @param $invoiceNumber
     * @param $invoiceDate
     * @return array
     * @internal param $invoiceUID
     */
    public function check($TransactionReferenceID, $invoiceNumber, $invoiceDate)
    {
        $fields = ['TransactionReferenceID' => $TransactionReferenceID, 'invoiceNumber' => $invoiceNumber, 'invoiceDate' => $invoiceDate, 'merchantCode' => $this->merchantCode, 'terminalCode' => $this->terminalCode];
        $result = Parser::post2https($fields, $this->checkURL);
        return Parser::makeXMLTree($result);
    }

    public function mpgIsValid($mobileNumber, $invoiceNumber, $invoiceDate, $amount, $referenceNumber, $transactionDate, $timestamp)
    {
        $sign = base64_encode($this->sign($invoiceNumber, $invoiceDate, $amount, null, $timestamp, null));
        $fields = [
            'userMobile' => $mobileNumber,
            'MerchantCode' => $this->merchantCode,
            'TerminalCode' => $this->terminalCode,
            'InvoiceNumber' => $invoiceNumber,
            'InvoiceDate' => $invoiceDate,
            'Amount' => $amount,
            'ReferenceNumber' => $referenceNumber,
            'transactionDate' => $transactionDate,
            'TimeStamp' => $timestamp,
            'sign' => $sign
        ];

        $result = Parser::post2https($fields, $this->isValid);
        return Parser::makeXMLTree($result);
    }

    /**
     * @param $invoiceNumber
     * @param $invoiceDate
     * @param $amount
     * @param $redirectAddress
     * @param $timestamp
     * @param $action
     * @return string
     */
    protected function sign($invoiceNumber, $invoiceDate, $amount, $redirectAddress = null, $timestamp = null, $action = null)
    {
        $redirectAddress = $redirectAddress ? '#' . $redirectAddress : '';
        $action = $action ? '#' . $action : '';
        $str = "#". $this->merchantCode . "#" . $this->terminalCode . "#" . $invoiceNumber . "#" . $invoiceDate . "#" . $amount . $redirectAddress . $action . "#" . $timestamp . "#";

        return $this->processor->sign(sha1($str, true));
    }
}

