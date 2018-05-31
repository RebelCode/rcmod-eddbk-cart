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
use Dhii\Storage\Resource\UpdateCapableInterface;
use Dhii\Util\Normalization\NormalizeIntCapableTrait;
use Dhii\Util\Normalization\NormalizeIterableCapableTrait;
use Dhii\Util\Normalization\NormalizeStringCapableTrait;
use Dhii\Util\String\StringableInterface as Stringable;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\EventManager\EventInterface;
use RebelCode\Bookings\TransitionerInterface;
use RebelCode\EddBookings\Logic\Module\BookingTransitionInterface as T;
use stdClass;

/**
 * The handler that updates and submits bookings when an EDD payment is complete.
 *
 * @since [*next-version*]
 */
class SubmitBookingOnPaymentHandler implements InvocableInterface
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
     * The bookings UPDATE resource model
     *
     * @since [*next-version*]
     *
     * @var UpdateCapableInterface
     */
    protected $bookingsUpdateRm;

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
     * @param TransitionerInterface                         $transitioner     The booking transitioner.
     * @param SelectCapableInterface                        $bookingsSelectRm The bookings SELECT resource model.
     * @param UpdateCapableInterface                        $bookingsUpdateRm The bookings UPDATE resource model.
     * @param array|stdClass|ArrayAccess|ContainerInterface $cartItemConfig   The cart item data config.
     * @param object                                        $exprBuilder      The expression builder.
     */
    public function __construct(
        TransitionerInterface $transitioner,
        SelectCapableInterface $bookingsSelectRm,
        UpdateCapableInterface $bookingsUpdateRm,
        $exprBuilder,
        $cartItemConfig
    ) {
        $this->transitioner     = $transitioner;
        $this->bookingsSelectRm = $bookingsSelectRm;
        $this->bookingsUpdateRm = $bookingsUpdateRm;
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
        // Get the event instance and ensure it implements the interface
        $event = func_get_arg(0);
        if (!($event instanceof EventInterface)) {
            throw $this->_createInvalidArgumentException(
                $this->__('Argument is not an event instance'), null, null, $event
            );
        }

        // Get the event params
        $paymentId = $event->getParam(0);
        $newStatus = $event->getParam(1);
        $oldStatus = $event->getParam(2);

        // Ensure payment was not already completed before. If it was, stop here.
        if ($oldStatus === 'publish' || $oldStatus === 'complete') {
            return;
        }
        // Check if the payment is being updated to a status of "completion". If not, stop here.
        if ($newStatus !== 'publish' && $newStatus !== 'complete') {
            return;
        }

        // Get the payment meta
        $paymentMeta = $this->_getPaymentMetaData($paymentId);
        // Get the items that were in the cart for this payment
        $items = $this->_containerGet($paymentMeta, 'downloads');

        // Get the cart item data keys from the cart item config
        $dataKey       = $this->_containerGetPath($this->cartItemConfig, ['data', 'key']);
        $eddBkKey      = $this->_containerGetPath($this->cartItemConfig, ['data', 'eddbk_key']);
        $bookingIdKey  = $this->_containerGetPath($this->cartItemConfig, ['data', 'booking_id_key']);
        $bookingIdPath = [$dataKey, $eddBkKey, $bookingIdKey];

        // Alias expression builder
        $b = $this->exprBuilder;

        foreach ($items as $_item) {
            try {
                // Get the booking ID from the additional item data
                $_bookingId = $this->_containerGetPath($_item, $bookingIdPath);
                // Fetch the corresponding booking from storage
                $_condition = $b->eq($b->var('id'), $b->lit($_bookingId));
                $_bookings  = $this->bookingsSelectRm->select($_condition);
                // Stop if no booking was found, or if multiple bookings matched the ID (for some reason?)
                if ($this->_countIterable($_bookings) !== 1) {
                    continue;
                }

                // Get the booking
                $_booking = reset($_bookings);
                $_booking = $this->transitioner->transition($_booking, T::TRANSITION_SUBMIT);

                // Prepare the change set
                $_changeSet = [
                    'payment_id' => $paymentId,
                    'client_id'  => $this->_getPaymentCustomerId($paymentId),
                    'status'     => $_booking->getStatus(),
                ];

                // Update booking
                $this->bookingsUpdateRm->update($_changeSet, $_condition);
            } catch (NotFoundExceptionInterface $exception) {
                continue;
            }
        }

        return;
    }

    /**
     * Retrieves the meta data for a payment, given by its ID.
     *
     * @since [*next-version*]
     *
     * @param int|string $paymentId The payment ID.
     *
     * @return int|string|Stringable
     */
    protected function _getPaymentMetaData($paymentId)
    {
        return \edd_get_payment_meta($paymentId);
    }

    /**
     * Retrieves the customer ID for a payment, given by its ID.
     *
     * @since [*next-version*]
     *
     * @param int|string $paymentId The payment ID.
     *
     * @return int|string|Stringable The customer ID.
     */
    protected function _getPaymentCustomerId($paymentId)
    {
        return \edd_get_payment_customer_id($paymentId);
    }
}
