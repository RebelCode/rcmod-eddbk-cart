<?php

namespace RebelCode\EddBookings\Cart\Module;

use Dhii\Data\Container\ContainerGetCapableTrait;
use Dhii\Data\Container\CreateContainerExceptionCapableTrait;
use Dhii\Data\Container\CreateNotFoundExceptionCapableTrait;
use Dhii\Data\Container\NormalizeKeyCapableTrait;
use Dhii\Exception\CreateInvalidArgumentExceptionCapableTrait;
use Dhii\Exception\CreateOutOfRangeExceptionCapableTrait;
use Dhii\Exception\CreateRuntimeExceptionCapableTrait;
use Dhii\I18n\StringTranslatingTrait;
use Dhii\Invocation\InvocableInterface;
use Dhii\Iterator\CountIterableCapableTrait;
use Dhii\Iterator\ResolveIteratorCapableTrait;
use Dhii\Storage\Resource\SelectCapableInterface;
use Dhii\Util\Normalization\NormalizeIntCapableTrait;
use Dhii\Util\Normalization\NormalizeStringCapableTrait;
use Psr\Container\NotFoundExceptionInterface;
use Psr\EventManager\EventInterface;
use RebelCode\Entity\EntityManagerInterface;

/**
 * The handler for filtering cart item names to just be the service names.
 *
 * @since [*next-version*]
 */
class FilterCartItemNameHandler implements InvocableInterface
{
    /* @since [*next-version*] */
    use ContainerGetCapableTrait;

    /* @since [*next-version*] */
    use CountIterableCapableTrait;

    /* @since [*next-version*] */
    use ResolveIteratorCapableTrait;

    /* @since [*next-version*] */
    use NormalizeIntCapableTrait;

    /* @since [*next-version*] */
    use NormalizeKeyCapableTrait;

    /* @since [*next-version*] */
    use NormalizeStringCapableTrait;

    /* @since [*next-version*] */
    use CreateContainerExceptionCapableTrait;

    /* @since [*next-version*] */
    use CreateNotFoundExceptionCapableTrait;

    /* @since [*next-version*] */
    use CreateOutOfRangeExceptionCapableTrait;

    /* @since [*next-version*] */
    use CreateInvalidArgumentExceptionCapableTrait;

    /* @since [*next-version*] */
    use CreateRuntimeExceptionCapableTrait;

    /* @since [*next-version*] */
    use StringTranslatingTrait;

    /**
     * The services manager.
     *
     * @since [*next-version*]
     *
     * @var EntityManagerInterface
     */
    protected $servicesManager;

    /**
     * Constructor.
     *
     * @since [*next-version*]
     *
     * @param EntityManagerInterface $servicesManager The services manager.
     */
    public function __construct(EntityManagerInterface $servicesManager)
    {
        $this->servicesManager = $servicesManager;
    }

    /**
     * {@inheritdoc}
     *
     * @since [*next-version*]
     */
    public function __invoke()
    {
        $event = func_get_arg(0);

        if (!($event instanceof EventInterface)) {
            throw $this->_createInvalidArgumentException(
                $this->__('Argument is not an event instance'), null, null, $event
            );
        }

        // Get service ID from event
        $serviceId = $event->getParam(1);

        if (empty($serviceId)) {
            return;
        }

        try {
            $service = $this->servicesManager->get($serviceId);
        } catch (NotFoundExceptionInterface $exception) {
            return;
        }

        $name = $this->_containerGet($service, 'name');

        // Set to event
        $event->setParams([0 => $name] + $event->getParams());
    }
}
