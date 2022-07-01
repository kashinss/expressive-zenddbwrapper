<?php
/**
 * User: Nick Rezun
 * Date: 17.10.2017
 * Time: 17:16
 */

namespace Ctrlweb\Expressive\ZendDbWrapper;

use Interop\Container\ContainerInterface;
use Zend\Db\Adapter\AdapterInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class ZendDbMapperFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $adapter = $container->get(AdapterInterface::class);

        return new $requestedName($adapter);
    }
}