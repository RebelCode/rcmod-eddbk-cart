<?php

namespace RebelCode\EddBookings\Cart\Module;

use ArrayAccess;
use Dhii\Data\Container\ContainerGetCapableTrait;
use Dhii\Data\Container\ContainerGetPathCapableTrait;
use Dhii\Data\Container\CreateContainerExceptionCapableTrait;
use Dhii\Data\Container\CreateNotFoundExceptionCapableTrait;
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
use Dhii\Validation\Exception\ValidationFailedExceptionInterface;
use EDD_Cart;
use Exception as RootException;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RebelCode\Bookings\BookingInterface;
use RebelCode\Bookings\Exception\CouldNotTransitionExceptionInterface;
use RebelCode\Bookings\TransitionerInterface;
use RebelCode\EddBookings\Logic\Module\BookingTransitionInterface as Transition;
use stdClass;

/**
 * The handler that validates bookings in the EDD cart.
 *
 * @since [*next-version*]
 */
class ValidateCartBookingHandler implements InvocableInterface
{
    /* @since [*next-version*] */
    use ContainerGetCapableTrait;

    /* @since [*next-version*] */
    use ContainerGetPathCapableTrait;

    /* @since [*next-version*] */
    use CountIterableCapableTrait;

    /* @since [*next-version*] */
    use NormalizeIntCapableTrait;

    /* @since [*next-version*] */
    use NormalizeKeyCapableTrait;

    /* @since [*next-version*] */
    use NormalizeStringCapableTrait;

    /* @since [*next-version*] */
    use NormalizeIterableCapableTrait;

    /* @since [*next-version*] */
    use ResolveIteratorCapableTrait;

    /* @since [*next-version*] */
    use CreateContainerExceptionCapableTrait;

    /* @since [*next-version*] */
    use CreateNotFoundExceptionCapableTrait;

    /* @since [*next-version*] */
    use CreateOutOfRangeExceptionCapableTrait;

    /* @since [*next-version*] */
    use CreateInvalidArgumentExceptionCapableTrait;

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
     * The booking transitioner.
     *
     * @since [*next-version*]
     *
     * @var TransitionerInterface
     */
    protected $transitioner;

    /**
     * The bookings SELECT resource model
     *
     * @since [*next-version*]
     *
     * @var SelectCapableInterface
     */
    protected $bookingsSelectRm;

    /**
     * The expression builder.
     *
     * @since [*next-version*]
     *
     * @var object
     */
    protected $exprBuilder;

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
     * @param EDD_Cart                                      $eddCart          The EDD cart instance.
     * @param TransitionerInterface                         $transitioner     The booking transitioner.
     * @param SelectCapableInterface                        $bookingsSelectRm The bookings SELECT resource model.
     * @param array|stdClass|ArrayAccess|ContainerInterface $cartItemConfig   The cart item data config.
     * @param object                                        $exprBuilder      The expression builder.
     */
    public function __construct(
        EDD_Cart $eddCart,
        TransitionerInterface $transitioner,
        SelectCapableInterface $bookingsSelectRm,
        $exprBuilder,
        $cartItemConfig
    ) {
        $this->eddCart          = $eddCart;
        $this->transitioner     = $transitioner;
        $this->bookingsSelectRm = $bookingsSelectRm;
        $this->exprBuilder      = $exprBuilder;
        $this->cartItemConfig   = $cartItemConfig;
    }

    /**
     * {@inheritdoc}
     *
     * @since [*next-version*]
     */
    public function __invoke()
    {
        $items = $this->eddCart->get_contents();

        // Get the cart item data keys from the cart item config
        $dataKey       = $this->_containerGetPath($this->cartItemConfig, ['data', 'key']);
        $eddBkKey      = $this->_containerGetPath($this->cartItemConfig, ['data', 'eddbk_key']);
        $bookingIdKey  = $this->_containerGetPath($this->cartItemConfig, ['data', 'booking_id_key']);
        $bookingIdPath = [$dataKey, $eddBkKey, $bookingIdKey];

        // Alias expression builder
        $b = $this->exprBuilder;

        foreach ($items as $_item) {
            try {
                // Get the booking ID
                $bookingId = $this->_containerGetPath($_item, $bookingIdPath);
            } catch (NotFoundExceptionInterface $exception) {
                continue; // If ID not found, cart item does not represent a booking
            }

            // Get the booking that matches the ID
            $bookings  = $this->bookingsSelectRm->select(
                $b->eq(
                    $b->ef('booking', 'id'),
                    $b->lit($bookingId)
                )
            );

            // Check that only 1 booking was retrieved
            if ($this->_countIterable($bookings) !== 1) {
                $this->_addEddCheckoutError(
                    'eddbk_invalid_booking_id',
                    $this->__('A cart item has an invalid booking ID.')
                );
            }

            // Get the booking
            $booking = reset($bookings);

            try {
                // Validate it
                $this->_validateBooking($booking);
            } catch (ValidationFailedExceptionInterface $exception) {
                // Register all validation errors as EDD checkout errors
                foreach ($exception->getValidationErrors() as $key => $error) {
                    $this->_addEddCheckoutError(
                        sprintf('eddbk_invalid_booking_%s', $key),
                        $error
                    );
                }
            }
        }
    }

    /**
     * Validates a cart item booking.
     *
     * @since [*next-version*]
     *
     * @param BookingInterface $booking The booking instance.
     *
     * @throws ValidationFailedExceptionInterface If the booking is invalid.
     */
    protected function _validateBooking(BookingInterface $booking)
    {
        try {
            $this->transitioner->transition($booking, Transition::TRANSITION_SUBMIT);
        } catch (CouldNotTransitionExceptionInterface $exception) {
            // Get the validation failure exception
            $validationException = $this->_resolveValidationFailedException($exception);
            // If found, throw it
            if ($validationException instanceof ValidationFailedExceptionInterface) {
                throw $validationException;
            }
        }
    }

    /**
     * Retrieves the validation failure exception from an exception's previous exceptions chain.
     *
     * @since [*next-version*]
     *
     * @param RootException $exception The exception to search.
     *
     * @return ValidationFailedExceptionInterface|null The found validation failure exception or null if not found.
     */
    protected function _resolveValidationFailedException(RootException $exception)
    {
        while ($exception !== null && !($exception instanceof ValidationFailedExceptionInterface)) {
            $exception = $exception->getPrevious();
        }

        /* @var $exception ValidationFailedExceptionInterface|null */
        return $exception;
    }

    /**
     * Adds an EDD cart checkout error.
     *
     * @since [*next-version*]
     *
     * @param string|Stringable $key The error key.
     * @param string|Stringable $message The error message.
     */
    protected function _addEddCheckoutError($key, $message)
    {
        \edd_set_error($key, $message);
    }
}
