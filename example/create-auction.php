<?php

use Fei\ApiClient\Transport\BasicTransport;
use Fei\Service\Bid\Client\Bidder;
use Fei\Service\Bid\Client\Exception\UniqueConstraintException;
use Fei\Service\Bid\Entity\Auction;

require __DIR__ . '/../vendor/autoload.php';

$bidder = new Bidder([Bidder::OPTION_BASEURL => 'http://bid.test.flash-global.net']);
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

try {
    $bidder->createAuction($auction);
} catch (UniqueConstraintException $e) {
    echo $e->getMessage() . PHP_EOL;

    $bidder->dropAuction($auction);

    printf('Auction id %d was dropped' . PHP_EOL, $auction->getId());
}
