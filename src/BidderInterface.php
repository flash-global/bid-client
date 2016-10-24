<?php

namespace Fei\Service\Bid\Client;

use Fei\Service\Bid\Entity\Auction;
use Fei\Service\Bid\Entity\Bid;

/**
 * Interface BidderInterface
 *
 * @package Fei\Service\Bid\Client
 */
interface BidderInterface
{
    /**
     * Create a auction
     *
     * @param Auction $auction
     *
     * @return Auction
     */
    public function createAuction(Auction $auction);

    /**
     * Update a auction
     *
     * @param Auction $auction
     *
     * @return Auction
     */
    public function updateAuction(Auction $auction);

    /**
     * Drop a auction
     *
     * @param Auction $auction
     */
    public function dropAuction(Auction $auction);

    /**
     * Search a Auction instance by his key
     *
     * @param string $key
     *
     * @return Auction
     */
    public function getAuction($key);

    /**
     * Get auction's bid
     *
     * @param Auction $auction
     * @param array   $criteria
     *
     * @return \Fei\Entity\PaginatedEntitySet
     */
    public function getAuctionBids(Auction $auction, array $criteria = []);

    /**
     * Add a bid
     *
     * @param Auction $auction
     * @param Bid     $Bid
     *
     * @return Bid
     */
    public function bid(Auction $auction, Bid $Bid);

    /**
     * Drop a bid
     *
     * @param Bid $bid
     */
    public function dropBid(Bid $bid);
}
