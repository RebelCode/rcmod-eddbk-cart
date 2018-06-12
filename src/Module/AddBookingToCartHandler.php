<?php

namespace RebelCode\EddBookings\Cart\Module;

use ArrayAccess;
use Dhii\Data\Container\ContainerGetCapableTrait;
use Dhii\Data\Container\ContainerGetPathCapableTrait;
use Dhii\Data\Container\CreateContainerExceptionCapableTrait;
use Dhii\Data\Container\CreateNotFoundExceptionCapableTrait;
use Dhii\Data\Container\NormalizeContainerCapableTrait;
use Dhii\Data\Container\NormalizeKeyCapableTrait;
use Dhii\Exception\CreateInvalidArgumentExceptionCapableTrait;
use Dhii\Exception\CreateOutOfRangeExceptionCapableTrait;
use Dhii\I18n\StringTranslatingTrait;
use Dhii\Invocation\InvocableInterface;
use Dhii\Iterator\CountIterableCapableTrait;
use Dhii\Iterator\ResolveIteratorCapableTrait;
use Dhii\Storage\Resource\SelectCapableInterface;
use Dhii\Util\Normalization\NormalizeIntCapableTrait;
use Dhii\Util\Normalization\NormalizeIterableCapableTrait;
use Dhii\Util\Normalization\NormalizeStringCapableTrait;
use Dhii\Util\String\StringableInterface as Stringable;
use EDD_Cart;
use InvalidArgumentException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\EventManager\EventInterface;
use RebelCode\Bookings\BookingInterface;
use RebelCode\EddBookings\Logic\Module\BookingTransitionInterface as Transition;
use stdClass;

/**
 * The handler for adding bookings to the EDD cart.
 *
 * @since [*next-version*]
 */
class AddBookingToCartHandler implements InvocableInterface
{
    /* @since [*next-version*] */
    use ContainerGetCapableTrait;

    /* @since [*next-version*] */
    use ContainerGetPathCapableTrait;

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
    use NormalizeIterableCapableTrait;

    /* @since [*next-version*] */
    use NormalizeContainerCapableTrait;

    /* @since [*next-version*] */
    use CreateInvalidArgumentExceptionCapableTrait;

    /* @since [*next-version*] */
    use CreateOutOfRangeExceptionCapableTrait;

    /* @since [*next-version*] */
    use CreateContainerExceptionCapableTrait;

    /* @since [*next-version*] */
    use CreateNotFoundExceptionCapableTrait;

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
     * The EDD cart instance.
     *
     * @since [*next-version*]
     *
     * @var EDD_Cart
     */
    protected $eddCart;

    /**
     * The cart item data config.
     *
     * @since [*next-version*]
     *
     * @var array|stdClass|ArrayAccess|ContainerInterface
     */
    protected $cartItemConfig;

    /**
     * Constructor.
     *
     * @since [*next-version*]
     *
     * @param SelectCapableInterface $servicesSelectRm The services SELECT resource model.
     * @param object                 $exprBuilder      The expression builder.
     * @param EDD_Cart               $eddCart          The EDD cart instance.
     * @param Stringable|string      $cartItemConfig   The cart item data config.
     */
    public function __construct($servicesSelectRm, $exprBuilder, EDD_Cart $eddCart, $cartItemConfig)
    {
        $this->servicesSelectRm = $servicesSelectRm;
        $this->exprBuilder      = $exprBuilder;
        $this->eddCart          = $eddCart;
        $this->cartItemConfig   = $cartItemConfig;
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

        // Only continue if the booking is transitioning to the cart
        if ($event->getParam('transition') !== Transition::TRANSITION_CART) {
            return;
        }

        // Get and validate the booking from the event
        $booking = $event->getParam('booking');
        if (!($booking instanceof BookingInterface)) {
            throw $this->_createInvalidArgumentException(
                $this->__('Booking in event is not a valid booking instance'), null, null, $booking
            );
        }

        $this->_addToCart($booking);
    }

    /**
     * Adds the booking to the EDD cart.
     *
     * @since [*next-version*]
     *
     * @param BookingInterface|ContainerInterface $booking The booking - must be a valid booking and container instance.
     *
     * @throws InvalidArgumentException    If the booking is not a valid container.
     * @throws NotFoundExceptionInterface  If the service ID was not be found in the booking data.
     * @throws ContainerExceptionInterface If an error occurred while reading the booking data.
     */
    protected function _addToCart(BookingInterface $booking)
    {
        $bookingId = $booking->getId();

        // Booking must be a container to get the service ID
        $container = $this->_normalizeContainer($booking);
        $serviceId = $this->_containerGet($container, 'service_id');

        // Get the cart item data keys from the cart item config
        $eddBkKey     = $this->_containerGetPath($this->cartItemConfig, ['data', 'eddbk_key']);
        $bookingIdKey = $this->_containerGetPath($this->cartItemConfig, ['data', 'booking_id_key']);
        $priceIdKey   = $this->_containerGetPath($this->cartItemConfig, ['data', 'price_id_key']);

        // Find the price ID
        $service  = $this->_getServiceById($serviceId);
        $lengths  = $this->_containerGet($service, 'session_lengths');
        $duration = $booking->getDuration();
        $priceId  = null;
        foreach ($lengths as $_idx => $_lengthInfo) {
            $_length = (int) $this->_containerGet($_lengthInfo, 'sessionLength');

            if ($_length === $duration) {
                $priceId = $_idx;
                break;
            }
        }

        // Create the cart item data
        $data = [
            $priceIdKey => $priceId,
            $eddBkKey   => [
                $bookingIdKey => $bookingId,
            ],
        ];

        // Add the service (EDD Download) to the cart with the additional EDD Bookings data
        $this->eddCart->add($serviceId, $data);
    }

    /**
     * Retrieves a booking by ID.
     *
     * @since [*next-version*]
     *
     * @param int|string|Stringable $serviceId The ID of the service to retrieve.
     *
     * @return array|stdClass|ArrayAccess|ContainerInterface|null The service instance, or null if no service was
     *                                                            found for the given ID.
     */
    protected function _getServiceById($serviceId)
    {
        $b = $this->exprBuilder;

        $services = $this->servicesSelectRm->select(
            $b->and(
                $b->eq($b->ef('service', 'id'), $b->lit($serviceId))
            )
        );

        if ($this->_countIterable($services) !== 1) {
            return;
        }

        return reset($services);
    }
}
