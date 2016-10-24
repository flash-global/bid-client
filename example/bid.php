<?php

use Fei\ApiClient\Transport\BasicTransport;
use Fei\Service\Bid\Client\Bidder;
use Fei\Service\Bid\Client\Exception\BidderException;
use Fei\Service\Bid\Client\Exception\ValidationException;
use Fei\Service\Bid\Entity\Auction;
use Fei\Service\Bid\Entity\Bid;

require __DIR__ . '/../vendor/autoload.php';

$bidder = new Bidder([Bidder::OPTION_BASEURL => 'http://localhost:8080']);
$bidder->setTransport(new BasicTransport());

$auction = (new Auction())
    ->setKey('a key ' . time())
    ->setStartAt(new \DateTime())
    ->setEndAt(new \DateTime('+1 day'))
    ->setMinimalBid('100')
    ->setBidStep('10')
    ->setBidStepStrategy(Auction::BASIC_STRATEGY);

$bidder->createAuction($auction);

printf('Auction id %d was created' . PHP_EOL, $auction->getId());

// Attempts to bid with invalid amounts

try {
    $bidder->bid($auction, (new Bid())->setBidder('a bidder')->setAmount(90));
} catch (ValidationException $e) {
    echo $e->getMessage() . PHP_EOL;
}

$bid = $bidder->bid($auction, (new Bid())->setBidder('a bidder')->setAmount(105));

printf('Bid %d was created with the amount of %.2f' . PHP_EOL, $bid->getId(), $bid->getAmount());

try {
    $bidder->bid($auction, (new Bid())->setBidder('a bidder')->setAmount(110));
} catch (BidderException $e) {
    echo $e->getMessage() . PHP_EOL;
}

$amount = 115.00;

for ($i = 0; $i < 30; $i++) {
    $bid = $bidder->bid(
        $auction,
        (new Bid())
            ->setBidder('a bidder ' . ($i + 1))
            ->setAmount($amount + ($i * $auction->getBidStep()))
            ->setContext(['i' => $i])
    );
    printf('Bid %d was created with the amount of %.2f' . PHP_EOL, $bid->getId(), $bid->getAmount());
}

// Get all Action bid

$bids = $bidder->getAuctionBids($auction);

/** @var Bid $bid */
foreach ($bids as $bid) {
    printf(
        '"%s" bid at %s with an amount of %.2f (i => %d)' . PHP_EOL,
        $bid->getBidder(),
        $bid->getCreatedAt()->format(\DateTime::ISO8601),
        $bid->getAmount(),
        $bid->getContext()['i']
    );
}

// Drop example action
$bidder->dropAuction($auction);

printf('Auction id %d was dropped' . PHP_EOL, $auction->getId());
