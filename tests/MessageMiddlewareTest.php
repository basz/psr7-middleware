<?php
/**
 * This file is part of prooph/psr7-middleware.
 * (c) 2016-2017 prooph software GmbH <contact@prooph.de>
 * (c) 2016-2017 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\Psr7Middleware;

use Fig\Http\Message\StatusCodeInterface;
use Interop\Http\ServerMiddleware\DelegateInterface;
use PHPUnit\Framework\TestCase;
use Prooph\Common\Messaging\Message;
use Prooph\Common\Messaging\MessageFactory;
use Prooph\Psr7Middleware\Exception\RuntimeException;
use Prooph\Psr7Middleware\MessageMiddleware;
use Prooph\Psr7Middleware\Response\ResponseStrategy;
use Prooph\ServiceBus\CommandBus;
use Prooph\ServiceBus\EventBus;
use Prooph\ServiceBus\QueryBus;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use React\Promise\Promise;

/**
 * Test integrity of \Prooph\Psr7Middleware\MessageMiddleware
 */
class MessageMiddlewareTest extends TestCase
{
    /**
     * @test
     */
    public function it_throws_exception_if_message_is_not_well_formed(): void
    {
        $commandBus = $this->prophesize(CommandBus::class);
        $commandBus->dispatch(Argument::type(Message::class))->shouldNotBeCalled();

        $queryBus = $this->prophesize(QueryBus::class);
        $queryBus->dispatch(Argument::type(Message::class))->shouldNotBeCalled();

        $eventBus = $this->prophesize(EventBus::class);
        $eventBus->dispatch(Argument::type(Message::class))->shouldNotBeCalled();

        $messageFactory = $this->prophesize(MessageFactory::class);
        $messageFactory->createMessageFromArray(Argument::type('string'), Argument::type('array'))->shouldNotBeCalled();

        $responseStrategy = $this->prophesize(ResponseStrategy::class);
        $responseStrategy->fromPromise(Argument::type(Promise::class))->shouldNotBeCalled();
        $responseStrategy->withStatus(Argument::any())->shouldNotBeCalled();

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getParsedBody()->willReturn(['message_name' => 'test'])->shouldBeCalled();

        $delegate = $this->prophesize(DelegateInterface::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MessageData must contain a key uuid');

        $middleware = new MessageMiddleware(
            $commandBus->reveal(),
            $queryBus->reveal(),
            $eventBus->reveal(),
            $messageFactory->reveal(),
            $responseStrategy->reveal()
        );

        $middleware->process($request->reveal(), $delegate->reveal());
    }

    /**
     * @test
     */
    public function it_thows_exception_if_message_type_is_unknown(): void
    {
        $payload = $this->getPayload('unknown');

        $message = $this->prophesize(Message::class);
        $message->messageType()->shouldBeCalled()->willReturn('unkown');

        $commandBus = $this->prophesize(CommandBus::class);
        $commandBus->dispatch(Argument::type(Message::class))->shouldNotBeCalled();

        $queryBus = $this->prophesize(QueryBus::class);
        $queryBus->dispatch(Argument::type(Message::class))->shouldNotBeCalled();

        $eventBus = $this->prophesize(EventBus::class);
        $eventBus->dispatch(Argument::type(Message::class))->shouldNotBeCalled();

        $messageFactory = $this->prophesize(MessageFactory::class);
        $messageFactory
            ->createMessageFromArray(
                $payload['message_name'],
                $payload
            )
            ->willReturn($message->reveal())
            ->shouldBeCalled();

        $responseStrategy = $this->prophesize(ResponseStrategy::class);
        $responseStrategy->fromPromise(Argument::type(Promise::class))->shouldNotBeCalled();
        $responseStrategy->withStatus(Argument::any())->shouldNotBeCalled();

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getParsedBody()->willReturn($payload)->shouldBeCalled();

        $delegate = $this->prophesize(DelegateInterface::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('An error occurred during dispatching of message "unknown"');

        $middleware = new MessageMiddleware(
            $commandBus->reveal(),
            $queryBus->reveal(),
            $eventBus->reveal(),
            $messageFactory->reveal(),
            $responseStrategy->reveal()
        );

        $middleware->process($request->reveal(), $delegate->reveal());
    }

    public function providerMessageTypes(): array
    {
        return [
            [Message::TYPE_COMMAND],
            [Message::TYPE_EVENT],
            [Message::TYPE_QUERY],
        ];
    }

    /**
     * @test
     * @dataProvider providerMessageTypes
     */
    public function it_throws_exception_if_dispatch_failed(string $messageType): void
    {
        $payload = $this->getPayload('name.' . $messageType);

        $message = $this->prophesize(Message::class);
        $message->messageType()->shouldBeCalled()->willReturn($messageType);

        $commandBus = $this->prophesize(CommandBus::class);
        $queryBus = $this->prophesize(QueryBus::class);
        $eventBus = $this->prophesize(EventBus::class);

        switch ($messageType) {
            case Message::TYPE_COMMAND:
                $commandBus->dispatch(Argument::type(Message::class))->shouldBeCalled()->willThrow(
                    new \Exception('Error')
                );
                $queryBus->dispatch(Argument::type(Message::class))->shouldNotBeCalled();
                $eventBus->dispatch(Argument::type(Message::class))->shouldNotBeCalled();
                break;
            case Message::TYPE_QUERY:
                $queryBus->dispatch(Argument::type(Message::class))->shouldBeCalled()->willThrow(
                    new \Exception('Error')
                );
                $commandBus->dispatch(Argument::type(Message::class))->shouldNotBeCalled();
                $eventBus->dispatch(Argument::type(Message::class))->shouldNotBeCalled();
                break;
            case Message::TYPE_EVENT:
                $eventBus->dispatch(Argument::type(Message::class))->shouldBeCalled()->willThrow(
                    new \Exception('Error')
                );
                $commandBus->dispatch(Argument::type(Message::class))->shouldNotBeCalled();
                $queryBus->dispatch(Argument::type(Message::class))->shouldNotBeCalled();
                break;
            default:
                $commandBus->dispatch(Argument::type(Message::class))->shouldNotBeCalled();
                $queryBus->dispatch(Argument::type(Message::class))->shouldNotBeCalled();
                $eventBus->dispatch(Argument::type(Message::class))->shouldNotBeCalled();
                break;
        }

        $messageFactory = $this->prophesize(MessageFactory::class);
        $messageFactory
            ->createMessageFromArray(
                $payload['message_name'],
                $payload
            )
            ->willReturn($message->reveal())
            ->shouldBeCalled();

        $responseStrategy = $this->prophesize(ResponseStrategy::class);
        $responseStrategy->fromPromise(Argument::type(Promise::class))->shouldNotBeCalled();
        $responseStrategy->withStatus(Argument::any())->shouldNotBeCalled();

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getParsedBody()->willReturn($payload)->shouldBeCalled();

        $delegate = $this->prophesize(DelegateInterface::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionCode(StatusCodeInterface::STATUS_INTERNAL_SERVER_ERROR);

        $middleware = new MessageMiddleware(
            $commandBus->reveal(),
            $queryBus->reveal(),
            $eventBus->reveal(),
            $messageFactory->reveal(),
            $responseStrategy->reveal()
        );

        $middleware->process($request->reveal(), $delegate->reveal());
    }

    /**
     * @test
     */
    public function it_dispatches_the_command(): void
    {
        $payload = $this->getPayload('command');

        $commandBus = $this->prophesize(CommandBus::class);
        $commandBus->dispatch(Argument::type(Message::class))->shouldBeCalled();

        $queryBus = $this->prophesize(QueryBus::class);
        $queryBus->dispatch(Argument::type(Message::class))->shouldNotBeCalled();

        $eventBus = $this->prophesize(EventBus::class);
        $eventBus->dispatch(Argument::type(Message::class))->shouldNotBeCalled();

        $message = $this->prophesize(Message::class);
        $message->messageType()->shouldBeCalled()->willReturn(Message::TYPE_COMMAND);

        $messageFactory = $this->prophesize(MessageFactory::class);
        $messageFactory
            ->createMessageFromArray(
                $payload['message_name'],
                $payload
            )
            ->willReturn($message->reveal())
            ->shouldBeCalled();

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getParsedBody()->willReturn($payload)->shouldBeCalled();

        $response = $this->prophesize(ResponseInterface::class);

        $responseStrategy = $this->prophesize(ResponseStrategy::class);
        $responseStrategy->withStatus(StatusCodeInterface::STATUS_ACCEPTED)->willReturn($response);

        $delegate = $this->prophesize(DelegateInterface::class);

        $middleware = new MessageMiddleware(
            $commandBus->reveal(),
            $queryBus->reveal(),
            $eventBus->reveal(),
            $messageFactory->reveal(),
            $responseStrategy->reveal()
        );
        $this->assertSame($response->reveal(), $middleware->process($request->reveal(), $delegate->reveal()));
    }

    /**
     * @test
     */
    public function it_dispatches_the_event(): void
    {
        $payload = $this->getPayload('event');

        $commandBus = $this->prophesize(CommandBus::class);
        $commandBus->dispatch(Argument::type(Message::class))->shouldNotBeCalled();

        $queryBus = $this->prophesize(QueryBus::class);
        $queryBus->dispatch(Argument::type(Message::class))->shouldNotBeCalled();

        $eventBus = $this->prophesize(EventBus::class);
        $eventBus->dispatch(Argument::type(Message::class))->shouldBeCalled();

        $message = $this->prophesize(Message::class);
        $message->messageType()->shouldBeCalled()->willReturn(Message::TYPE_EVENT);

        $messageFactory = $this->prophesize(MessageFactory::class);
        $messageFactory
            ->createMessageFromArray(
                $payload['message_name'],
                $payload
            )
            ->willReturn($message->reveal())
            ->shouldBeCalled();

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getParsedBody()->willReturn($payload)->shouldBeCalled();

        $response = $this->prophesize(ResponseInterface::class);

        $responseStrategy = $this->prophesize(ResponseStrategy::class);
        $responseStrategy->withStatus(StatusCodeInterface::STATUS_ACCEPTED)->willReturn($response);

        $delegate = $this->prophesize(DelegateInterface::class);

        $middleware = new MessageMiddleware(
            $commandBus->reveal(),
            $queryBus->reveal(),
            $eventBus->reveal(),
            $messageFactory->reveal(),
            $responseStrategy->reveal()
        );
        $this->assertSame($response->reveal(), $middleware->process($request->reveal(), $delegate->reveal()));
    }

    /**
     * @test
     */
    public function it_dispatches_the_query(): void
    {
        $payload = $this->getPayload('query');

        $commandBus = $this->prophesize(CommandBus::class);
        $commandBus->dispatch(Argument::type(Message::class))->shouldNotBeCalled();

        $queryBus = $this->prophesize(QueryBus::class);
        $queryBus->dispatch(Argument::type(Message::class))->shouldBeCalled()->willReturn(
            $this->prophesize(Promise::class)->reveal()
        );

        $eventBus = $this->prophesize(EventBus::class);
        $eventBus->dispatch(Argument::type(Message::class))->shouldNotBeCalled();

        $message = $this->prophesize(Message::class);
        $message->messageType()->shouldBeCalled()->willReturn(Message::TYPE_QUERY);

        $messageFactory = $this->prophesize(MessageFactory::class);
        $messageFactory
            ->createMessageFromArray(
                $payload['message_name'],
                $payload
            )
            ->willReturn($message->reveal())
            ->shouldBeCalled();

        $request = $this->prophesize(ServerRequestInterface::class);
        $request->getParsedBody()->willReturn($payload)->shouldBeCalled();

        $response = $this->prophesize(ResponseInterface::class);

        $responseStrategy = $this->prophesize(ResponseStrategy::class);
        $responseStrategy->fromPromise(Argument::type(Promise::class))->willReturn($response);

        $delegate = $this->prophesize(DelegateInterface::class);

        $middleware = new MessageMiddleware(
            $commandBus->reveal(),
            $queryBus->reveal(),
            $eventBus->reveal(),
            $messageFactory->reveal(),
            $responseStrategy->reveal()
        );
        $this->assertSame($response->reveal(), $middleware->process($request->reveal(), $delegate->reveal()));
    }

    /**
     * Returns a full configured payload array
     */
    private function getPayload(string $messageName): array
    {
        return [
            'message_name' => $messageName,
            'uuid' => '08db0554-8e07-49a3-9cf2-d28dd9ec10ab',
            'version' => 1,
            'payload' => ['user' => 'prooph'],
            'metadata' => ['system' => 'production'],
            'created_at' => \DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.u', '2016-02-02T11:45:39.000000'),
        ];
    }
}
