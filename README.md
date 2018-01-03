# Service Bid - Client

[![GitHub release](https://img.shields.io/github/release/flash-global/bid-client.svg?style=for-the-badge)](README.md) 


## Table of contents
- [Purpose](#purpose)
- [Requirements](#requirements)
    - [Runtime](#runtime)
- [Step by step installation](#step-by-step-installation)
    - [Initialization](#initialization)
    - [Settings](#settings)
    - [Known issues](#known-issues)
- [Contribution](#contribution)
- [Link to documentation](#link-to-documentation)
    - [Examples](#examples)

## Purpose
This client permit to use the `Bid Api`. Thanks to it, you could request the API to :
* Fetch data
* Create data
* Update data
* Delete data

easily

## Requirements 

### Runtime
- PHP 5.5

## Step by step Installation
> for all purposes (devlopment, contribution and production)

### Initialization
- Cloning repository 
```git clone https://github.com/flash-global/bid-client.git```
- Run Composer depedencies installation
```composer install```

### Settings

Don't forget to set the right `baseUrl` :

```php
<?php 
$bidder = new Bidder([Bidder::OPTION_BASEURL => 'http://bid.test.flash-global.net']);
$bidder->setTransport(new BasicTransport());
```

### Known issues
No known issue at this time.

## Contribution
As FEI Service, designed and made by OpCoding. The contribution workflow will involve both technical teams. Feel free to contribute, to improve features and apply patches, but keep in mind to carefully deal with pull request. Merging must be the product of complete discussions between Flash and OpCoding teams :) 

## Link to documentation 

### Examples
You can test this client easily thanks to the folder [example](example)

Here, an example on how to use example : `php /my/bid-client/folder/example/bid.php` 


A auction is container for bid. So if you want to bid, you must in first instance create a Auction session:

```php
<?php

use Fei\ApiClient\Transport\BasicTransport;
use Fei\Service\Bid\Client\Bidder;
use Fei\Service\Bid\Client\Exception\UniqueConstraintException;
use Fei\Service\Bid\Entity\Auction;

$bidder = new Bidder([Bidder::OPTION_BASEURL => 'http://localhost:8080']);
$bidder->setTransport(new BasicTransport());

$auction = (new Auction())
    ->setKey('a key ' . time())
    ->setStartAt(new \DateTime())
    ->setEndAt(new \DateTime('+1 day'))
    ->setMinimalBid('100')
    ->setBidStep('10')
    ->setBidStepStrategy(Auction::BASIC_STRATEGY);

try {
    $bidder->createAuction($auction);
} catch (UniqueConstraintException $e) {
    echo 'Auction entity is not unique (key already exists)' . PHP_EOL;
    exit();
}

// Do what you want is you new Auction instance...
```

When you create a Auction, you have to chose a _step strategy_ which is used to validate the requested bid amount
compared to the value of _bid step_ and the amount of the last authorized bid of the current auction session.

* `Auction::BASIC_STATEGY`: validate bid amounts greater than the last bid amount of the current Auction session plus
  the _bid step_ ;
* `Auction::PERCENT_STRATEGY`: validate bid amounts greater than the last bid amount of the current Auction session plus
  a piece of its percentage defined by _bid step_. In this strategy the unit of _bid step_ is percent.

Has you can see in the example above, the method `Bidder::createAuction` returns an identified Auction instance which
will let you send `Bid` instances:

```php
<?php

use Fei\Service\Bid\Client\Bidder;
use Fei\ApiClient\Transport\BasicTransport;
use Fei\Service\Bid\Entity\Bid;

$bidder = new Bidder([Bidder::OPTION_BASEURL => 'http://localhost:8080']);
$bidder->setTransport(new BasicTransport());

// Create a new Auction session...

$bid = $bidder->bid(
    $auction,
    (new Bid())
        ->setBidder('a bidder id')
        ->setAmount(120)
        ->setContext([
            'key1' => 'value1',
            'key2' => 'value2'
        ])
);

```

On the same behaviour of `Bidder::createAuction`, `Bidder::bid` returns a identified `Bid` instance.

Note that `Auction` and `Bid` are _contextable_. So you can attach various (or free) context (key/value pair) to your
entities and later retrieve bids with free filters.

### Retrieves Auction and Bids


```php
<?php

use Fei\ApiClient\Transport\BasicTransport;
use Fei\Service\Bid\Client\Bidder;

$bidder = new Bidder([Bidder::OPTION_BASEURL => 'http://localhost:8080']);
$bidder->setTransport(new BasicTransport());

$auction = $bidder->getAuction('a key');

printf('Auction id=%d, key=`%s`, created_at=`%s`' . PHP_EOL,
    $auction->getId(),
    $auction->getKey(),
    $auction->getCreatedAt()->format(\DateTime::ISO8601)
);
```

### Get auction's bids

Lets see how to retrieve bids from an auction session:

* `Bidder::getAuctionBids(Auction $auction, array $criteria = [])` returns a collection of `Bid` instance (more
precisely a `EntitySet` instance) attached to a `Auction` instance responding to criteria filters (see below)
  
#### Bids search criteria

| Criteria                            | Description                                                                                                                                                                                                                                                                            | Type   | Possible Values                                        |
|-------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|--------|--------------------------------------------------------|
| `Bidder::CRITERIA_CONTEXT_KEY`      | The context key to seach for (work with `Bidder::CRITERIA_CONTEXT_OPERATOR` and `Bidder::CRITERIA_CONTEXT_VALUE`)                                                                                                                                                                      | string | Any string                                             |
| `Bidder::CRITERIA_CONTEXT_OPERATOR` | The `Bidder::CRITERIA_CONTEXT_VALUE` search operator (work with `Bidder::CRITERIA_CONTEXT_KEY` and `Bidder::CRITERIA_CONTEXT_VALUE`) Example: `['Bidder::CRITERIA_CONTEXT_KEY' => 'a key', 'Bidder::CRITERIA_CONTEXT_OPERATOR' => '=', 'Bidder::CRITERIA_CONTEXT_VALUE' => 'a value']` | string | Could be `like`, `=`, `<`, `>`, `<>`, `!=`, `&` or `|` |
| `Bidder::CRITERIA_CONTEXT_VALUE`    | The context value to search for (work with `Bidder::CRITERIA_CONTEXT_KEY` and `Bidder::CRITERIA_CONTEXT_OPERATOR`)                                                                                                                                                                     | string | Any string                                             |

#### Example

```php
<?php

use Fei\ApiClient\Transport\BasicTransport;
use Fei\Service\Bid\Client\Bidder;

$bidder = new Bidder([Bidder::OPTION_BASEURL => 'http://localhost:8080']);
$bidder->setTransport(new BasicTransport());

$auction = $bidder->getAuction('a key');

// Retrieves all bids
$bids = $bidder->getAuctionBids($auction);

foreach ($bids as $bid) {
    print_r($bid->toArray());
}
```

### Other tools

There is other methods which will be helpful for auctions and bids management:

* `Bidder::updateAuction(Auction $auction)` update a `Auction` instance
* `Bidder::dropAuction(Auction $auction)` drop a `Auction` instance (aka erase definitively)
* `Bidder::dropBid(Bid $bi)` drop a `Bid` instance

### Client option

Below options are available which can be passed to the `__construct()` or `setOptions()` methods:

| Option         | Description                                    | Type   | Possible Values                                | Default |
|----------------|------------------------------------------------|--------|------------------------------------------------|---------|
| OPTION_BASEURL | This is the server to which send the requests. | string | Any URL, including protocol but excluding path | -       |
