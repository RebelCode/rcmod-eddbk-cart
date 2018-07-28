<?php

namespace RebelCode\EddBookings\Cart;

use Dhii\Data\ValueAwareInterface;
use Dhii\Exception\CreateInvalidArgumentExceptionCapableTrait;
use Dhii\I18n\StringTranslatingTrait;
use RebelCode\Bookings\StateAwareBookingInterface;

/**
 * An implementation of an object that can provide a booking instance.
 *
 * @since [*next-version*]
 */
class BookingValueAware implements ValueAwareInterface
{
    /* @since [*next-version*] */
    use CreateInvalidArgumentExceptionCapableTrait;

    /* @since [*next-version*] */
    use StringTranslatingTrait;

    /**
     * The state aware.
     *
     * @since [*next-version*]
     *
     * @var StateAwareBookingInterface
     */
    protected $booking;

    /**
     * Constructor.
     *
     * @since [*next-version*]
     *
     * @param StateAwareBookingInterface $booking The booking instance.
     */
    public function __construct(StateAwareBookingInterface $booking)
    {
        $this->booking = $booking;
    }

    /**
     * {@inheritdoc}
     *
     * @since [*next-version*]
     *
     * @return StateAwareBookingInterface
     */
    public function getValue()
    {
        return $this->booking;
    }
}
