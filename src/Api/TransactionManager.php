<?php declare(strict_types=1);
/**
 * Copyright © 2020 MultiSafepay, Inc. All rights reserved.
 * See DISCLAIMER.md for disclaimer details.
 */

namespace MultiSafepay\Api;

use Money\Money;
use MultiSafepay\Api\Base\RequestBody;
use MultiSafepay\Api\Transactions\RequestOrder;
use MultiSafepay\Api\Transactions\Transaction;
use MultiSafepay\Exception\ApiException;
use Psr\Http\Client\ClientExceptionInterface;

/**
 * Class TransactionManager
 * @package MultiSafepay\Api
 */
class TransactionManager extends AbstractManager
{
    /**
     * @param RequestOrder $requestOrder
     * @return Transaction
     * @throws ClientExceptionInterface
     */
    public function create(RequestOrder $requestOrder): Transaction
    {
        $response = $this->client->createPostRequest('orders', $requestOrder->getData());
        return new Transaction($response->getResponseData());
    }

    /**
     * Get all data from a transaction.
     * @param string $orderId
     * @return Transaction
     * @throws ClientExceptionInterface
     * @throws ApiException
     */
    public function get(string $orderId): Transaction
    {
        $endpoint = 'orders/' . $orderId;
        $response = $this->client->createGetRequest($endpoint);
        return new Transaction($response->getResponseData());
    }

    /**
     * @param Transaction $transaction
     * @param Money $amount
     * @param string $description
     * @return array
     * @throws ClientExceptionInterface
     */
    public function refund(Transaction $transaction, Money $amount, string $description = ''): array
    {
        $requestBody = new RequestBody([
            'amount' => $amount->getAmount(),
            'currency' => $amount->getCurrency(),
            'description' => $description,
        ]);

        $response = $this->client->createPostRequest(
            'orders/' . $transaction->getOrderId() . '/refunds',
            $requestBody->getData()
        );

        return $response->getResponseData();
    }
}
