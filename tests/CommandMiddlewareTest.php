<?php
/**
 * This file is part of the prooph/psr7-middleware.
 * (c) 2014-2016 prooph software GmbH <contact@prooph.de>
 * (c) 2015-2016 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\Psr7Middleware;

use PHPUnit\Framework\TestCase;
use Prooph\Common\Messaging\Message;
use Prooph\Common\Messaging\MessageFactory;
use Prooph\Psr7Middleware\CommandMiddleware;
use Prooph\Psr7Middleware\MetadataGatherer;
use Prooph\Psr7Middleware\Middleware;
use Prooph\ServiceBus\CommandBus;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Test integrity of \Prooph\Psr7Middleware\CommandMiddleware
 */
class CommandMiddlewareTest extends TestCase
{
    /**
     * @test
     */
    public function it_calls_next_with_exception_if_command_name_attribute_is_not_set(): void
    {
        $commandBus = $this->prophesize(CommandBus::class);
        $commandBus->dispatch(Argument::type(Message::class))->shouldNotBeCalled();

        $messageFactory = $this->prophesize(MessageFactory::class);

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getAttribute(CommandMiddleware::NAME_ATTRIBUTE)->willReturn(null)->shouldBeCalled();

        $response = $this->prophesize(ResponseInterface::class);
        $response->withStatus(Middleware::STATUS_CODE_BAD_REQUEST)->shouldBeCalled();

        $gatherer = $this->prophesize(MetadataGatherer::class);
        $gatherer->getFromRequest($request)->shouldNotBeCalled();

        $middleware = new CommandMiddleware($commandBus->reveal(), $messageFactory->reveal(), $gatherer->reveal());

        $middleware($request->reveal(), $response->reveal(), Helper::callableWithExceptionResponse());
    }

    /**
     * @test
     */
    public function it_calls_next_with_exception_if_dispatch_failed(): void
    {
        $commandName = 'stdClass';
        $payload = ['user_id' => 123];

        $commandBus = $this->prophesize(CommandBus::class);
        $commandBus->dispatch(Argument::type(Message::class))->shouldBeCalled()->willThrow(
            new \Exception('Error')
        );

        $message = $this->prophesize(Message::class);

        $messageFactory = $this->prophesize(MessageFactory::class);
        $messageFactory
            ->createMessageFromArray(
                $commandName,
                ['payload' => $payload, 'metadata' => []]
            )
            ->willReturn($message->reveal())
            ->shouldBeCalled();

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getParsedBody()->willReturn($payload)->shouldBeCalled();
        $request->getAttribute(CommandMiddleware::NAME_ATTRIBUTE)->willReturn($commandName);

        $response = $this->prophesize(ResponseInterface::class);
        $response->withStatus(Middleware::STATUS_CODE_INTERNAL_SERVER_ERROR)->shouldBeCalled();

        $gatherer = $this->prophesize(MetadataGatherer::class);
        $gatherer->getFromRequest($request->reveal())->willReturn([])->shouldBeCalled();

        $middleware = new CommandMiddleware($commandBus->reveal(), $messageFactory->reveal(), $gatherer->reveal());

        $middleware($request->reveal(), $response->reveal(), Helper::callableWithExceptionResponse());
    }

    /**
     * @test
     */
    public function it_dispatches_the_command(): void
    {
        $commandName = 'stdClass';
        $payload = ['user_id' => 123];

        $commandBus = $this->prophesize(CommandBus::class);
        $commandBus->dispatch(Argument::type(Message::class))->shouldBeCalled();

        $message = $this->prophesize(Message::class);

        $messageFactory = $this->prophesize(MessageFactory::class);
        $messageFactory
            ->createMessageFromArray(
                $commandName,
                ['payload' => $payload, 'metadata' => []]
            )
            ->willReturn($message->reveal())
            ->shouldBeCalled();

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getParsedBody()->willReturn($payload)->shouldBeCalled();
        $request->getAttribute(CommandMiddleware::NAME_ATTRIBUTE)->willReturn($commandName)->shouldBeCalled();

        $response = $this->prophesize(ResponseInterface::class);
        $response->withStatus(Middleware::STATUS_CODE_ACCEPTED)->shouldBeCalled();

        $gatherer = $this->prophesize(MetadataGatherer::class);
        $gatherer->getFromRequest($request->reveal())->willReturn([])->shouldBeCalled();

        $middleware = new CommandMiddleware($commandBus->reveal(), $messageFactory->reveal(), $gatherer->reveal());
        $middleware($request->reveal(), $response->reveal(), Helper::callableShouldNotBeCalledWithException($this));
    }
}
