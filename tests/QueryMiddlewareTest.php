<?php
/**
 * prooph (http://getprooph.org/)
 *
 * @see       https://github.com/prooph/psr7-middleware for the canonical source repository
 * @copyright Copyright (c) 2016 prooph software GmbH (http://prooph-software.com/)
 * @license   https://github.com/prooph/psr7-middleware/blob/master/LICENSE New BSD License
 */

namespace ProophTest\Psr7Middleware;

use PHPUnit_Framework_TestCase as TestCase;
use Prooph\Common\Messaging\Message;
use Prooph\Common\Messaging\MessageFactory;
use Prooph\Psr7Middleware\QueryMiddleware;
use Prooph\Psr7Middleware\Middleware;
use Prooph\Psr7Middleware\Response\ResponseStrategy;
use Prooph\ServiceBus\QueryBus;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\Promise;

/**
 * Test integrity of \Prooph\Psr7Middleware\QueryMiddleware
 */
class QueryMiddlewareTest extends TestCase
{
    /**
     * @test
     */
    public function it_calls_next_with_exception_if_query_name_attribute_is_not_set()
    {
        $queryBus = $this->prophesize(QueryBus::class);
        $queryBus->dispatch(Argument::type(Message::class))->shouldNotBeCalled();

        $messageFactory = $this->prophesize(MessageFactory::class);

        $responseStrategy = $this->prophesize(ResponseStrategy::class);
        $responseStrategy->fromPromise(Argument::type(Promise::class))->shouldNotBeCalled();

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getAttribute(QueryMiddleware::NAME_ATTRIBUTE)->willReturn(null)->shouldBeCalled();

        $response = $this->prophesize(ResponseInterface::class);
        $response->withStatus(Middleware::STATUS_CODE_BAD_REQUEST)->shouldBeCalled();

        $middleware = new QueryMiddleware($queryBus->reveal(), $messageFactory->reveal(), $responseStrategy->reveal());

        $middleware($request->reveal(), $response->reveal(), Helper::callableWithExceptionResponse());
    }

    /**
     * @test
     */
    public function it_calls_next_with_exception_if_dispatch_failed()
    {
        $queryName = 'stdClass';
        $payload = ['user_id' => 123];

        $queryBus = $this->prophesize(QueryBus::class);
        $queryBus->dispatch(Argument::type(Message::class))->shouldBeCalled()->willThrow(
            new \Exception('Error')
        );

        $message = $this->prophesize(Message::class);

        $messageFactory = $this->prophesize(MessageFactory::class);
        $messageFactory
            ->createMessageFromArray(
                $queryName,
                ['payload' => $payload]
            )
            ->willReturn($message->reveal())
            ->shouldBeCalled();

        $responseStrategy = $this->prophesize(ResponseStrategy::class);
        $responseStrategy->fromPromise(Argument::type(Promise::class))->shouldNotBeCalled();

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getParsedBody()->willReturn($payload)->shouldBeCalled();
        $request->getAttribute(QueryMiddleware::NAME_ATTRIBUTE)->willReturn($queryName)->shouldBeCalled();

        $response = $this->prophesize(ResponseInterface::class);
        $response->withStatus(Middleware::STATUS_CODE_INTERNAL_SERVER_ERROR)->shouldBeCalled();

        $middleware = new QueryMiddleware($queryBus->reveal(), $messageFactory->reveal(), $responseStrategy->reveal());

        $middleware($request->reveal(), $response->reveal(), Helper::callableWithExceptionResponse());
    }

    /**
     * @test
     */
    public function it_dispatches_the_query()
    {
        $queryName = 'stdClass';
        $payload = ['user_id' => 123];

        $queryBus = $this->prophesize(QueryBus::class);
        $queryBus->dispatch(Argument::type(Message::class))->shouldBeCalled()->willReturn(
            $this->prophesize(Promise::class)->reveal()
        );

        $message = $this->prophesize(Message::class);

        $messageFactory = $this->prophesize(MessageFactory::class);
        $messageFactory
            ->createMessageFromArray(
                $queryName,
                ['payload' => $payload]
            )
            ->willReturn($message->reveal())
            ->shouldBeCalled();

        $responseStrategy = $this->prophesize(ResponseStrategy::class);
        $responseStrategy->fromPromise(Argument::type(Promise::class))->shouldBeCalled();

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getParsedBody()->willReturn($payload)->shouldBeCalled();
        $request->getAttribute(QueryMiddleware::NAME_ATTRIBUTE)->willReturn($queryName)->shouldBeCalled();

        $response = $this->prophesize(ResponseInterface::class);
        $response->withStatus(Argument::type('integer'))->shouldNotBeCalled();

        $middleware = new QueryMiddleware($queryBus->reveal(), $messageFactory->reveal(), $responseStrategy->reveal());
        $middleware($request->reveal(), $response->reveal(), Helper::callableShouldNotBeCalledWithException($this));
    }
}
