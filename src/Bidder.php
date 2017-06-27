<?php

namespace Fei\Service\Bid\Client;

use Fei\ApiClient\AbstractApiClient;
use Fei\ApiClient\ApiClientException;
use Fei\ApiClient\RequestDescriptor;
use Fei\ApiClient\ResponseDescriptor;
use Fei\Entity\EntityInterface;
use Fei\Entity\EntitySet;
use Fei\Entity\PaginatedEntitySet;
use Fei\Service\Bid\Client\Exception\BidderException;
use Fei\Service\Bid\Client\Exception\NonPersistedEntityException;
use Fei\Service\Bid\Client\Exception\UniqueConstraintException;
use Fei\Service\Bid\Client\Exception\ValidationException;
use Fei\Service\Bid\Entity\Auction;
use Fei\Service\Bid\Entity\Bid;
use Fei\Service\Bid\Validator\AuctionValidator;
use Fei\Service\Bid\Validator\BidValidator;
use Guzzle\Http\Exception\BadResponseException;

/**
 * Class Bid
 *
 * @package Fei\Service\Bid\Client
 */
class Bidder extends AbstractApiClient implements BidderInterface
{
    const API_AUCTION_PATH_INFO = '/api/auctions';
    const API_BID_PATH_INFO = '/api/bids';

    const CRITERIA_CONTEXT_KEY = 'context_key';
    const CRITERIA_CONTEXT_OPERATOR = 'context_operator';
    const CRITERIA_CONTEXT_VALUE = 'context_value';

    /**
     * {@inheritdoc}
     */
    public function createAuction(Auction $auction)
    {
        $this->validateAuction($auction);

        $request = (new RequestDescriptor())
            ->setUrl($this->buildUrl(self::API_AUCTION_PATH_INFO))
            ->setMethod('POST');

        $request->setBodyParams(['auction' => \json_encode($auction->toArray())]);

        return $this->persist($request, $auction);
    }

    /**
     * {@inheritdoc}
     */
    public function updateAuction(Auction $auction)
    {
        if ($auction->getId() == null) {
            throw new NonPersistedEntityException('Auction entity was not persisted');
        }

        $this->validateAuction($auction);

        $request = (new RequestDescriptor())
            ->setUrl($this->buildUrl(sprintf(self::API_AUCTION_PATH_INFO . '/%d', $auction->getId())))
            ->setMethod('POST');

        $request->setBodyParams(['auction' => \json_encode($auction->toArray())]);

        return $this->persist($request, $auction);
    }

    /**
     * {@inheritdoc}
     */
    public function dropAuction(Auction $auction)
    {
        if ($auction->getId() == null) {
            throw new NonPersistedEntityException('Auction entity was not persisted');
        }

        $request = (new RequestDescriptor())
            ->setUrl($this->buildUrl(sprintf(self::API_AUCTION_PATH_INFO . '/%d', $auction->getId())))
            ->setMethod('DELETE');

        $this->send($request);
    }

    /**
     * {@inheritdoc}
     */
    public function getAuction($key)
    {
        $request = new RequestDescriptor();
        $request->setUrl(
            $this->buildUrl(self::API_AUCTION_PATH_INFO . '?' . http_build_query([Auction::CRITERIA_KEY => $key]))
        )->setMethod('GET');

        $set = $this->buildEntitySet($this->send($request));

        return !empty($set[0]) ? $set[0] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function getAuctionBids(Auction $auction, array $criteria = [])
    {
        if ($auction->getId() == null) {
            throw new NonPersistedEntityException('Auction entity was not persisted');
        }

        $request = new RequestDescriptor();
        $request
            ->setUrl(
                $this->buildUrl(
                    sprintf(self::API_AUCTION_PATH_INFO . '/%d/bids?', $auction->getId()) . http_build_query($criteria)
                )
            )
            ->setMethod('GET');

        $bids = $this->buildEntitySet($this->send($request));

        foreach ($bids as &$bid) {
            $auction->addBid($bid);
        }

        return $bids;
    }

    /**
     * {@inheritdoc}
     */
    public function bid(Auction $auction, Bid $bid)
    {
        if ($auction->getId() == null) {
            throw new NonPersistedEntityException('Auction entity was not persisted');
        }

        $bid->setAuction($auction);

        $this->validateBid($bid);

        $request = (new RequestDescriptor())
            ->setUrl($this->buildUrl(self::API_BID_PATH_INFO))
            ->setMethod('POST');

        $request->setBodyParams(['bid' => \json_encode($bid->toArray())]);

        return $this->persist($request, $bid);
    }

    /**
     * {@inheritdoc}
     */
    public function updateBidStatus(Bid $bid)
    {
        if ($bid->getId() == null) {
            throw new NonPersistedEntityException('Bid entity was not persisted');
        }

        $request = (new RequestDescriptor())
            ->setUrl($this->buildUrl(sprintf(self::API_BID_PATH_INFO .'/%d', $bid->getId())))
            ->setMethod('PATCH');

        $request->setBodyParams([
            'status' => $bid->getStatus(),
        ]);

        return $this->send($request);
    }

    /**
     * {@inheritdoc}
     */
    public function dropBid(Bid $bid)
    {
        if ($bid->getId() == null) {
            throw new NonPersistedEntityException('Bid entity was not persisted');
        }

        $request = (new RequestDescriptor())
            ->setUrl($this->buildUrl(sprintf(self::API_BID_PATH_INFO .'/%d', $bid->getId())))
            ->setMethod('DELETE');

        $this->send($request);
    }

    /**
     * Validate an Auction entity
     *
     * @param Auction $auction
     */
    protected function validateAuction(Auction $auction)
    {
        $validator = new AuctionValidator();

        if (!$validator->validate($auction)) {
            throw (new ValidationException(
                sprintf('Auction entity is not valid: (%s)', $validator->getErrorsAsString())
            ))->setErrors($validator->getErrors());
        }
    }

    /**
     * Validate a Bid entity
     *
     * @param Bid $bid
     */
    protected function validateBid(Bid $bid)
    {
        $validator = new BidValidator();

        if (!$validator->validate($bid)) {
            throw (new ValidationException(
                sprintf('Bid entity is not valid: (%s)', $validator->getErrorsAsString())
            ))->setErrors($validator->getErrors());
        }
    }

    /**
     * Send persist request and return persisted entity
     *
     * @param RequestDescriptor $request
     * @param EntityInterface   $entity
     *
     * @return EntityInterface
     *
     * @throws BidderException
     */
    protected function persist(RequestDescriptor $request, EntityInterface $entity)
    {
        try {
            $response = $this->send($request);

            if ($response instanceof ResponseDescriptor) {
                $body = \json_decode($response->getBody(), true);

                if (!empty($body['id']) && method_exists($entity, 'setId')) {
                    $entity->setId((int) $body['id']);
                }
            }
        } catch (ApiClientException $e) {
            $previous = $e->getPrevious();
            if ($previous instanceof BadResponseException) {
                $data = \json_decode($previous->getResponse()->getBody(true), true);
                if (!empty($data['type'])
                    && $data['type'] == 'Doctrine\DBAL\Exception\UniqueConstraintViolationException'
                ) {
                    throw new UniqueConstraintException(
                        !empty($data['error']) ? $data['error'] :  '',
                        !empty($data['code']) ? $data['code'] :  0,
                        $e
                    );
                }
            }

            throw new BidderException($e->getMessage(), $e->getCode(), $e);
        }

        return $entity;
    }

    /**
     * Return a paginated entity set
     *
     * @param ResponseDescriptor $response
     *
     * @return EntitySet|PaginatedEntitySet
     */
    protected function buildEntitySet(ResponseDescriptor $response)
    {
        $entities = [];

        $data = $response->getData();

        if (!empty($data)) {
            $targetedEntityClass = $response->getMeta('entity');

            $this->validateEntityClass($targetedEntityClass);

            foreach ($data as $row) {
                $entities[] = $this->entityFactory($targetedEntityClass, $row);
            }
        }

        return new EntitySet($entities);
    }

    /**
     * Returns a hydrated entity
     *
     * @param string $targetEntityClass
     * @param array  $data
     *
     * @return \Fei\Entity\EntityInterface
     */
    protected function entityFactory($targetEntityClass, array $data)
    {
        /** @var \Fei\Entity\EntityInterface $entity */
        $entity = new $targetEntityClass;
        $entity->hydrate($data);

        return $entity;
    }

    /**
     * Validate an entity class name
     *
     * @param $targetedEntityClass
     *
     * @throws BidderException
     */
    protected function validateEntityClass($targetedEntityClass)
    {
        if (empty($targetedEntityClass)) {
            throw new BidderException('Targeted entity class name directive not found');
        }

        if (!class_exists($targetedEntityClass)) {
            throw new BidderException(sprintf('"%s" is not a valid entity class name', $targetedEntityClass));
        }

        if (!in_array('Fei\Entity\EntityInterface', class_implements($targetedEntityClass))) {
            throw new BidderException(
                sprintf('Entity class "%s" does not implement "Fei\Entity\EntityInterface"', $targetedEntityClass)
            );
        }
    }
}
