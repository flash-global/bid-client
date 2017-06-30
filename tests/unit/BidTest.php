<?php

namespace Tests\Fei\Service\Bid\Client;

use Codeception\Test\Unit;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Fei\ApiClient\ApiClientException;
use Fei\ApiClient\RequestDescriptor;
use Fei\ApiClient\ResponseDescriptor;
use Fei\ApiClient\Transport\SyncTransportInterface;
use Fei\Entity\EntitySet;
use Fei\Service\Bid\Client\Bidder;
use Fei\Service\Bid\Client\Exception\BidderException;
use Fei\Service\Bid\Client\Exception\NonPersistedEntityException;
use Fei\Service\Bid\Client\Exception\UniqueConstraintException;
use Fei\Service\Bid\Client\Exception\ValidationException;
use Fei\Service\Bid\Entity\Auction;
use Fei\Service\Bid\Entity\Bid;
use Guzzle\Http\Exception\BadResponseException;
use Guzzle\Http\Message\Response;

/**
 * Class BidTest
 *
 * @package Tests\Fei\Service\Bid\Client
 */
class BidTest extends Unit
{
    public function testCreateAuction()
    {
        $url = '';
        $method = '';

        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturnCallback(
            function (RequestDescriptor $request) use (&$url, &$method) {
                $url = $request->getUrl();
                $method = $request->getMethod();

                return (new ResponseDescriptor())
                    ->setBody(
                        json_encode([
                            'id' => 1
                        ])
                    );
            }
        );

        $bidder = new Bidder([Bidder::OPTION_BASEURL => 'http://url']);
        $bidder->setTransport($transport);

        $auction = $bidder->createAuction($this->getValidAuctionInstance());

        $this->assertEquals(sprintf('http://url%s', Bidder::API_AUCTION_PATH_INFO), $url);
        $this->assertEquals('POST', $method);

        $this->assertEquals(1, $auction->getId());
    }

    public function testCreateAuctionInvalid()
    {
        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->never())->method('send');

        $bidder = new Bidder([Bidder::OPTION_BASEURL => 'http://url']);
        $bidder->setTransport($transport);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageRegExp('/^Auction entity is not valid: \((.*)\)$/');

        $bidder->createAuction(new Auction());
    }

    public function testCreateAuctionNonUnique()
    {
        $badResponseException = new BadResponseException();
        $body = \json_encode([
            'type' => UniqueConstraintViolationException::class
        ]);
        $response = new Response(500, [], $body);

        $badResponseException->setResponse($response);

        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->once())->method('send')->willThrowException(
            new ApiClientException('', 0, $badResponseException)
        );

        $bidder = new Bidder([Bidder::OPTION_BASEURL => 'http://url']);
        $bidder->setTransport($transport);

        $this->expectException(UniqueConstraintException::class);

        $bidder->createAuction($this->getValidAuctionInstance());
    }

    public function testCreateAuctionBidderException()
    {
        $badResponseException = new BadResponseException('bad response');
        $body = \json_encode([
            'type' => \RuntimeException::class
        ]);
        $response = new Response(500, [], $body);

        $badResponseException->setResponse($response);

        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->once())->method('send')->willThrowException(
            new ApiClientException('test', 0, $badResponseException)
        );

        $bidder = new Bidder([Bidder::OPTION_BASEURL => 'http://url']);
        $bidder->setTransport($transport);

        $this->expectException(BidderException::class);
        $this->expectExceptionMessage('bad response' . PHP_EOL . '[body] {"type":"RuntimeException"}');

        $bidder->createAuction($this->getValidAuctionInstance());
    }

    public function testUpdateAuction()
    {
        $url = '';
        $method = '';

        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturnCallback(
            function (RequestDescriptor $request) use (&$url, &$method) {
                $url = $request->getUrl();
                $method = $request->getMethod();
                return (new ResponseDescriptor())
                    ->setBody(
                        json_encode([
                            'id' => 2
                        ])
                    );
            }
        );

        $bidder = new Bidder([Bidder::OPTION_BASEURL => 'http://url']);
        $bidder->setTransport($transport);

        $auction = $bidder->updateAuction($this->getValidAuctionInstance()->setId(1));

        $this->assertEquals(sprintf('http://url%s/%d', Bidder::API_AUCTION_PATH_INFO, 1), $url);
        $this->assertEquals('POST', $method);

        $this->assertEquals(2, $auction->getId());
    }

    public function testUpdateAuctionNonPersistedAuction()
    {
        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->never())->method('send');

        $bidder = new Bidder([Bidder::OPTION_BASEURL => 'http://url']);
        $bidder->setTransport($transport);

        $this->expectException(NonPersistedEntityException::class);
        $this->expectExceptionMessage('Auction entity was not persisted');

        $auction = $bidder->updateAuction($this->getValidAuctionInstance());

        $this->assertEquals(2, $auction->getId());
    }

    public function testDropAuction()
    {
        $url = '';
        $method = '';

        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturnCallback(
            function (RequestDescriptor $request) use (&$url, &$method) {
                $url = $request->getUrl();
                $method = $request->getMethod();
                return null;
            }
        );

        $bidder = new Bidder([Bidder::OPTION_BASEURL => 'http://url']);
        $bidder->setTransport($transport);

        $bidder->dropAuction($this->getValidAuctionInstance()->setId(1));

        $this->assertEquals(sprintf('http://url%s/%d', Bidder::API_AUCTION_PATH_INFO, 1), $url);
        $this->assertEquals('DELETE', $method);
    }

    public function testDropAuctionNonPersisted()
    {
        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->never())->method('send');

        $bidder = new Bidder([Bidder::OPTION_BASEURL => 'http://url']);
        $bidder->setTransport($transport);

        $this->expectException(NonPersistedEntityException::class);
        $this->expectExceptionMessage('Auction entity was not persisted');

        $bidder->dropAuction($this->getValidAuctionInstance());
    }

    public function testGetAuction()
    {
        $url = '';
        $method = '';

        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->exactly(2))->method('send')->willReturnCallback(
            function (RequestDescriptor $request) use (&$url, &$method) {
                $url = $request->getUrl();
                $method = $request->getMethod();
                return (new ResponseDescriptor())
                    ->setBody(
                        json_encode([
                            'data' => [
                                [
                                    'key' => 'hello',
                                    'created_at' => '2016-10-17 16:20:00'
                                ]
                            ],
                            'meta' => [
                                'entity' => Auction::class,
                            ]
                        ])
                    );
            }

        );

        $bidder = new Bidder([Bidder::OPTION_BASEURL => 'http://url']);
        $bidder->setTransport($transport);

        $auction = $bidder->getAuction('a key');

        $this->assertEquals(
            sprintf('http://url%s?%s=a+key', Bidder::API_AUCTION_PATH_INFO, Auction::CRITERIA_KEY),
            $url
        );
        $this->assertEquals('GET', $method);

        $this->assertInstanceOf(Auction::class, $auction);

        // Test call with multiple keys.
        $auctions = $bidder->getAuction(['a key', 'another key']);
        $this->assertInstanceOf(EntitySet::class, $auctions);
    }

    public function testGetAuctionNoEntityClassName()
    {
        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturn(
            (new ResponseDescriptor())
                ->setBody(
                    json_encode([
                        'data' => [
                            [
                                'key' => 'hello',
                                'created_at' => '2016-10-17 16:20:00',
                            ]
                        ]
                    ])
                )
        );

        $bidder = new Bidder([Bidder::OPTION_BASEURL => 'http://url']);
        $bidder->setTransport($transport);

        $this->expectException(BidderException::class);
        $this->expectExceptionMessage('Targeted entity class name directive not found');

        $bidder->getAuction('key');
    }

    public function testGetAuctionNoEntityClassNameNotClassExist()
    {
        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturn(
            (new ResponseDescriptor())
                ->setBody(
                    json_encode([
                        'data' => [
                            [
                                'key' => 'hello',
                                'created_at' => '2016-10-17 16:20:00',
                            ]
                        ],
                        'meta' => [
                            'entity' => 'NotExistAtAll'
                        ]
                    ])
                )
        );

        $bidder = new Bidder([Bidder::OPTION_BASEURL => 'http://url']);
        $bidder->setTransport($transport);

        $this->expectException(BidderException::class);
        $this->expectExceptionMessage('"NotExistAtAll" is not a valid entity class name');

        $bidder->getAuction('key');
    }

    public function testGetAuctionNoEntityClassNameNotInstanceOfEntityInterface()
    {
        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturn(
            (new ResponseDescriptor())
                ->setBody(
                    json_encode([
                        'data' => [
                            [
                                'key' => 'hello',
                                'created_at' => '2016-10-17 16:20:00',
                            ]
                        ],
                        'meta' => [
                            'entity' => 'DateTime'
                        ]
                    ])
                )
        );

        $bidder = new Bidder([Bidder::OPTION_BASEURL => 'http://url']);
        $bidder->setTransport($transport);

        $this->expectException(BidderException::class);
        $this->expectExceptionMessage('Entity class "DateTime" does not implement "Fei\Entity\EntityInterface"');

        $bidder->getAuction('key');
    }

    public function testGetAuctionEmptyData()
    {
        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturn(
            (new ResponseDescriptor())->setBody(json_encode([]))
        );

        $bidder = new Bidder([Bidder::OPTION_BASEURL => 'http://url']);
        $bidder->setTransport($transport);

        $auction = $bidder->getAuction('a key');

        $this->assertNull($auction);
    }

    public function testGetAuctionBid()
    {
        $url = '';

        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturnCallback(
            function (RequestDescriptor $request) use (&$url) {
                $url = $request->getUrl();
                return (new ResponseDescriptor())
                    ->setBody(
                        json_encode([
                            'data' => [
                                [
                                    'created_at' => '2016-10-17 16:20:00',
                                    'bidder'=> 'tester',
                                    'amount' => 100
                                ]
                            ],
                            'meta' => [
                                'entity' => Bid::class,
                                'pagination' => [
                                    'current_page' => 1,
                                    'per_page' => 10,
                                    'total' => 100
                                ]
                            ]
                        ])
                    );
            }

        );

        $bidder = new Bidder([Bidder::OPTION_BASEURL => 'http://url']);
        $bidder->setTransport($transport);

        $bids = $bidder->getAuctionBids((new Auction())->setId(2));

        $this->assertEquals(sprintf('http://url/api/auctions/%d/bids?', 2), $url);

        $this->assertCount(1, $bids);
        $this->assertInstanceOf(Bid::class, $bids[0]);
    }

    public function testGetAuctionBidsNoAuctionPersisted()
    {
        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->never())->method('send');

        $bidder = new Bidder([Bidder::OPTION_BASEURL => 'http://url']);
        $bidder->setTransport($transport);

        $this->expectException(NonPersistedEntityException::class);
        $this->expectExceptionMessage('Auction entity was not persisted');

        $bidder->getAuctionBids(new Auction());
    }

    public function testBid()
    {
        $url = '';
        $method = '';

        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturnCallback(
            function (RequestDescriptor $request) use (&$url, &$method) {
                $url = $request->getUrl();
                $method = $request->getMethod();
                return (new ResponseDescriptor())->setBody(json_encode(['id' => 1]));
            }
        );

        $bidder = new Bidder([Bidder::OPTION_BASEURL => 'http://url']);
        $bidder->setTransport($transport);

        $bid = $bidder->bid(
            (new Auction())
                ->setId(1)
                ->setMinimalBid(100)
                ->setBidStep(10)
                ->setStartAt(new \DateTime())
                ->setEndAt(new \DateTime('+1 day'))
                ->setCreatedAt(new \DateTime())
                ->setKey('key'),
            (new Bid())
                ->setBidder('tester')
                ->setAmount(100)
                ->setCreatedAt(new \DateTime('+1 hour'))
        );

        $this->assertEquals(1, $bid->getId());
        $this->assertEquals('http://url/api/bids', $url);
        $this->assertEquals('POST', $method);
    }

    public function testBidBidInvalid()
    {
        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->never())->method('send');

        $bidder = new Bidder([Bidder::OPTION_BASEURL => 'http://url']);
        $bidder->setTransport($transport);

        try {
            $bidder->bid((new Auction())->setId(1), new Bid());
        } catch (\Exception $e) {
            $this->assertEquals(ValidationException::class, get_class($e));
            $this->assertRegExp('/^Bid entity is not valid: \((.*)\)$/', $e->getMessage());
            $this->assertCount(2, $e->getErrors());
        }
    }

    public function testBidNoAuctionPersisted()
    {
        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->never())->method('send');

        $bidder = new Bidder([Bidder::OPTION_BASEURL => 'http://url']);
        $bidder->setTransport($transport);

        $this->expectException(NonPersistedEntityException::class);
        $this->expectExceptionMessage('Auction entity was not persisted');

        $bidder->bid(new Auction(), new Bid());
    }

    public function testUpdateStatusBid()
    {
        $url = '';
        $method = '';

        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturnCallback(
            function (RequestDescriptor $request) use (&$url, &$method) {
                $url = $request->getUrl();
                $method = $request->getMethod();
                return (new ResponseDescriptor())->setBody(json_encode([]));
            }
        );

        $bidder = new Bidder([Bidder::OPTION_BASEURL => 'http://url']);
        $bidder->setTransport($transport);

        $bidder->updateBidStatus(
            (new Bid())->setId(1)->setStatus(Bid::STATUS_REFUSED)
        );

        $this->assertEquals('http://url/api/bids/1', $url);
        $this->assertEquals('PATCH', $method);
    }

    public function testUpdateStatusBidNotPersisted()
    {
        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->never())->method('send');

        $bidder = new Bidder([Bidder::OPTION_BASEURL => 'http://url']);
        $bidder->setTransport($transport);

        $this->expectException(NonPersistedEntityException::class);
        $this->expectExceptionMessage('Bid entity was not persisted');

        $bidder->updateBidStatus(new Bid());
    }

    public function testDropBid()
    {
        $url = '';
        $method = '';

        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturnCallback(
            function (RequestDescriptor $request) use (&$url, &$method) {
                $url = $request->getUrl();
                $method = $request->getMethod();
                return (new ResponseDescriptor())->setBody(json_encode([]));
            }
        );

        $bidder = new Bidder([Bidder::OPTION_BASEURL => 'http://url']);
        $bidder->setTransport($transport);

        $bidder->dropBid(
            (new Bid())->setId(1)
        );

        $this->assertEquals('http://url/api/bids/1', $url);
        $this->assertEquals('DELETE', $method);
    }

    public function testDropBidNonPersistedBid()
    {
        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->never())->method('send');

        $bidder = new Bidder([Bidder::OPTION_BASEURL => 'http://url']);
        $bidder->setTransport($transport);

        $this->expectException(NonPersistedEntityException::class);
        $this->expectExceptionMessage('Bid entity was not persisted');

        $bidder->dropBid(new Bid());
    }

    /**
     * Returns a valid auction instance
     *
     * @return Auction
     */
    protected function getValidAuctionInstance()
    {
        return (new Auction())
            ->setKey('a key ' . time())
            ->setStartAt(new \DateTime())
            ->setEndAt(new \DateTime('+1 day'))
            ->setMinimalBid('100')
            ->setBidStep('10')
            ->setBidStepStrategy(Auction::BASIC_STRATEGY);
    }
}
