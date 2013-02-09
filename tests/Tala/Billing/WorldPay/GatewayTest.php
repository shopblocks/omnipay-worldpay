<?php

/*
 * This file is part of the Tala Payments package.
 *
 * (c) Adrian Macneil <adrian@adrianmacneil.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tala\Billing\WorldPay;

use Mockery as m;
use Tala\BaseGatewayTest;
use Tala\Request;

class GatewayTest extends BaseGatewayTest
{
    public function setUp()
    {
        $this->httpClient = m::mock('\Tala\HttpClient\HttpClientInterface');
        $this->httpRequest = m::mock('\Symfony\Component\HttpFoundation\Request');

        $this->gateway = new Gateway($this->httpClient, $this->httpRequest);
        $this->gateway->setCallbackPassword('bar123');

        $this->options = array(
            'amount' => 1000,
            'returnUrl' => 'https://www.example.com/return',
        );
    }

    public function testPurchase()
    {
        $response = $this->gateway->purchase($this->options);

        $this->assertInstanceOf('\Tala\RedirectResponse', $response);
        $this->assertContains('https://secure.worldpay.com/wcc/purchase?', $response->getRedirectUrl());
    }

    public function testCompletePurchaseSuccess()
    {
        $this->httpRequest->shouldReceive('get')->with('callbackPW')->once()->andReturn('bar123');
        $this->httpRequest->shouldReceive('get')->with('transStatus')->once()->andReturn('Y');
        $this->httpRequest->shouldReceive('get')->with('transId')->once()->andReturn('abc123');

        $response = $this->gateway->completePurchase($this->options);

        $this->assertInstanceOf('\Tala\Response', $response);
        $this->assertEquals('abc123', $response->getGatewayReference());
    }

    /**
     * @expectedException \Tala\Exception\InvalidResponseException
     */
    public function testCompletePurchaseInvalidCallbackPassword()
    {
        $this->httpRequest->shouldReceive('get')->with('callbackPW')->once()->andReturn('bar321');

        $response = $this->gateway->completePurchase($this->options);
    }

    /**
     * @expectedException \Tala\Exception
     * @expectedExceptionMessage Declined
     */
    public function testCompletePurchaseError()
    {
        $this->httpRequest->shouldReceive('get')->with('callbackPW')->once()->andReturn('bar123');
        $this->httpRequest->shouldReceive('get')->with('transStatus')->once()->andReturn('N');
        $this->httpRequest->shouldReceive('get')->with('rawAuthMessage')->once()->andReturn('Declined');

        $response = $this->gateway->completePurchase($this->options);
    }
}