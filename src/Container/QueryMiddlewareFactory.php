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
use Prooph\Psr7Middleware\NoopMetadataGatherer;
use Prooph\Psr7Middleware\QueryMiddleware;
use Prooph\ServiceBus\QueryBus;

final class QueryMiddlewareFactory extends AbstractMiddlewareFactory implements ProvidesDefaultOptions, RequiresMandatoryOptions
{
    use ConfigurationTrait;

    /**
     * @param string $configId
     */
    public function __construct($configId = 'query')
    {
        parent::__construct($configId);
    }

    /**
     * Create service.
     *
     * @param ContainerInterface $container
     * @return QueryMiddleware
     */
    public function __invoke(ContainerInterface $container)
    {
        $options = $this->options($container->get('config'), $this->configId);

        if (isset($options['metadata_gatherer'])) {
            $gatherer = $container->get($options['metadata_gatherer']);
        } else {
            $gatherer = new NoopMetadataGatherer();
        }

        return new QueryMiddleware(
            $container->get($options['query_bus']),
            $container->get($options['message_factory']),
            $container->get($options['response_strategy']),
            $gatherer
        );
    }

    /**
     * @interitdoc
     */
    public function defaultOptions()
    {
        return ['query_bus' => QueryBus::class];
    }

    /**
     * @interitdoc
     */
    public function mandatoryOptions()
    {
        return ['message_factory', 'response_strategy'];
    }
}
