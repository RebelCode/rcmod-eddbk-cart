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
use Dhii\Util\Normalization\NormalizeIterableCapableTrait;
use Dhii\Util\Normalization\NormalizeStringCapableTrait;
use Dhii\Util\String\StringableInterface as Stringable;
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
     * @param EDD_Cart          $eddCart        The EDD cart instance.
     * @param Stringable|string $cartItemConfig The cart item data config.
     */
    public function __construct(EDD_Cart $eddCart, $cartItemConfig)
    {
        $this->eddCart        = $eddCart;
        $this->cartItemConfig = $cartItemConfig;
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
     * @throws InvalidArgumentException If the booking is not a valid container.
     * @throws NotFoundExceptionInterface If the service ID was not be found in the booking data.
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

        // Create the cart item data
        $data = [
            $eddBkKey => [
                $bookingIdKey => $bookingId,
            ],
        ];

        // Add the service (EDD Download) to the cart with the additional EDD Bookings data
        $this->eddCart->add($serviceId, $data);
    }
}
