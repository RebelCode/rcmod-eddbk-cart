<?php

namespace RebelCode\EddBookings\Cart;

use Dhii\Data\Container\ContainerGetCapableTrait;
use Dhii\Data\Container\CreateContainerExceptionCapableTrait;
use Dhii\Data\Container\CreateNotFoundExceptionCapableTrait;
use Dhii\Data\Container\NormalizeContainerCapableTrait;
use Dhii\Data\Container\NormalizeKeyCapableTrait;
use Dhii\Data\StateAwareInterface;
use Dhii\Data\ValueAwareInterface;
use Dhii\Evaluable\EvaluableInterface;
use Dhii\Exception\CreateInvalidArgumentExceptionCapableTrait;
use Dhii\Exception\CreateOutOfRangeExceptionCapableTrait;
use Dhii\Exception\CreateRuntimeExceptionCapableTrait;
use Dhii\I18n\StringTranslatingTrait;
use Dhii\Iterator\CountIterableCapableTrait;
use Dhii\Iterator\ResolveIteratorCapableTrait;
use Dhii\Storage\Resource\SelectCapableInterface;
use Dhii\Util\Normalization\NormalizeIntCapableTrait;
use Dhii\Util\Normalization\NormalizeIterableCapableTrait;
use Dhii\Util\Normalization\NormalizeStringCapableTrait;
use RebelCode\Bookings\BookingInterface;

/**
 * Evaluates booking prices.
 *
 * @since [*next-version*]
 */
class BookingPriceEvaluator implements EvaluableInterface
{
    /* @since [*next-version*] */
    use CountIterableCapableTrait;

    /* @since [*next-version*] */
    use ResolveIteratorCapableTrait;

    /* @since [*next-version*] */
    use ContainerGetCapableTrait;

    /* @since [*next-version*] */
    use NormalizeKeyCapableTrait;

    /* @since [*next-version*] */
    use NormalizeIntCapableTrait;

    /* @since [*next-version*] */
    use NormalizeStringCapableTrait;

    /* @since [*next-version*] */
    use NormalizeContainerCapableTrait;

    /* @since [*next-version*] */
    use NormalizeIterableCapableTrait;

    /* @since [*next-version*] */
    use CreateContainerExceptionCapableTrait;

    /* @since [*next-version*] */
    use CreateNotFoundExceptionCapableTrait;

    /* @since [*next-version*] */
    use CreateInvalidArgumentExceptionCapableTrait;

    /* @since [*next-version*] */
    use CreateOutOfRangeExceptionCapableTrait;

    /* @since [*next-version*] */
    use CreateRuntimeExceptionCapableTrait;

    /* @since [*next-version*] */
    use StringTranslatingTrait;

    /**
     * The services SELECT resource model.
     *
     * @since [*next-version*]
     *
     * @var SelectCapableInterface
     */
    protected $servicesSelectRm;

    /**
     * The expression builder.
     *
     * @since [*next-version*]
     *
     * @var object
     */
    protected $exprBuilder;

    /**
     * Constructor.
     *
     * @since [*next-version*]
     *
     * @param SelectCapableInterface $servicesSelectRm The services SELECT resource model.
     * @param object                 $exprBuilder      The expression builder.
     */
    public function __construct(SelectCapableInterface $servicesSelectRm, $exprBuilder)
    {
        $this->servicesSelectRm = $servicesSelectRm;
        $this->exprBuilder      = $exprBuilder;
    }

    /**
     * {@inheritdoc}
     *
     * @since [*next-version*]
     */
    public function evaluate(ValueAwareInterface $ctx = null)
    {
        $booking = $ctx->getValue();

        if (!($booking instanceof StateAwareInterface) || !($booking instanceof BookingInterface)) {
            throw $this->_createRuntimeException(
                $this->__('Cannot determine price - argument is not a state-aware booking instance'), null, null
            );
        }

        $duration  = $this->_normalizeInt($booking->getDuration());
        $serviceId = $booking->getState()->get('service_id');

        $b = $this->exprBuilder;

        $condition = $b->eq($b->ef('service', 'id'), $b->lit($serviceId));
        // EDD Bookings' services select RM only supports AND top-level expressions
        $services = $this->servicesSelectRm->select($b->and($condition));

        if ($this->_countIterable($services) === 0) {
            throw $this->_createRuntimeException(
                $this->__('Cannot determine price - service ID in booking does not match a service'), null, null
            );
        }

        $service = reset($services);
        $lengths = $this->_containerGet($service, 'session_lengths');
        $lengths = $this->_normalizeIterable($lengths);

        foreach ($lengths as $_lengthInfo) {
            $_length = $this->_normalizeInt($this->_containerGet($_lengthInfo, 'sessionLength'));

            if ($duration === $_length) {
                return $this->_containerGet($_lengthInfo, 'price');
            }
        }

        throw $this->_createRuntimeException(
            $this->__('Cannot determine booking price - booking does not match any session length'), null, null
        );
    }
}
