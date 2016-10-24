<?php

use Fei\ApiClient\Transport\BasicTransport;
use Fei\Service\Bid\Client\Bidder;
use Fei\Service\Bid\Entity\Auction;

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

$auction = $bidder->createAuction($auction);

printf('Auction %d was created' . PHP_EOL, $auction->getId());

// Delay the Auction of 1 hour
$auction->setEndAt($auction->getEndAt()->add(DateInterval::createFromDateString('+ 1 hour')));

$auction = $bidder->updateAuction($auction);

printf('Auction was delayed to %s' . PHP_EOL, $auction->getEndAt()->format(\DateTime::ISO8601));
