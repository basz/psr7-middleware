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

namespace Prooph\Psr7Middleware\Container;

use Interop\Config\ConfigurationTrait;
use Interop\Config\ProvidesDefaultOptions;
use Interop\Config\RequiresMandatoryOptions;
use Interop\Container\ContainerInterface;
use Prooph\Psr7Middleware\CommandMiddleware;
use Prooph\Psr7Middleware\NoopMetadataGatherer;
use Prooph\ServiceBus\CommandBus;

final class CommandMiddlewareFactory extends AbstractMiddlewareFactory implements ProvidesDefaultOptions, RequiresMandatoryOptions
{
    use ConfigurationTrait;

    /**
     * @interitdoc
     */
    public function __construct($configId = 'command')
    {
        parent::__construct($configId);
    }

    /**
     * Create service.
     *
     * @param ContainerInterface $container
     * @return CommandMiddleware
     */
    public function __invoke(ContainerInterface $container)
    {
        $options = $this->options($container->get('config'), $this->configId);

        if (isset($options['metadata_gatherer'])) {
            $gatherer = $container->get($options['metadata_gatherer']);
        } else {
            $gatherer = new NoopMetadataGatherer();
        }

        return new CommandMiddleware(
            $container->get($options['command_bus']),
            $container->get($options['message_factory']),
            $gatherer
        );
    }

    /**
     * @interitdoc
     */
    public function defaultOptions()
    {
        return ['command_bus' => CommandBus::class];
    }

    /**
     * @interitdoc
     */
    public function mandatoryOptions()
    {
        return ['message_factory'];
    }
}
