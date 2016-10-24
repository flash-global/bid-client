<?php

namespace Fei\Service\Bid\Client\Exception;

use Fei\ApiClient\ApiClientException;
use Guzzle\Http\Exception\BadResponseException;

/**
 * Class BidderException
 *
 * @package Fei\Service\Bid\Client\Exception
 */
class BidderException extends ApiClientException
{
    /**
     * BidderException constructor.
     *
     * @param string          $message
     * @param int             $code
     * @param \Exception|null $previous
     */
    public function __construct($message = '', $code = 0, \Exception $previous = null)
    {
        $previous = !is_null($previous) && $previous->getPrevious() ? $previous->getPrevious() : $previous;

        if ($previous instanceof BadResponseException) {
            $message = $previous->getMessage() .
                sprintf(PHP_EOL . '[body] %s', $previous->getResponse()->getBody(true));
        }

        parent::__construct($message, $code, $previous);
    }
}
