<?php

namespace RebelCode\EddBookings\Cart;

use Dhii\Data\ValueAwareInterface;
use Dhii\Exception\CreateInvalidArgumentExceptionCapableTrait;
use Dhii\I18n\StringTranslatingTrait;
use RebelCode\Bookings\BookingAwareTrait;
use RebelCode\Bookings\BookingInterface;

/**
 * An implementation of an object that can provide a booking instance.
 *
 * @since [*next-version*]
 */
class BookingValueAware implements ValueAwareInterface
{
    /* @since [*next-version*] */
    use BookingAwareTrait;

    /* @since [*next-version*] */
    use CreateInvalidArgumentExceptionCapableTrait;

    /* @since [*next-version*] */
    use StringTranslatingTrait;

    /**
     * Constructor.
     *
     * @since [*next-version*]
     *
     * @param BookingInterface|null $booking The booking instance, if any.
     */
    public function __construct(BookingInterface $booking = null)
    {
        $this->_setBooking($booking);
    }

    /**
     * {@inheritdoc}
     *
     * @since [*next-version*]
     *
     * @return BookingInterface
     */
    public function getValue()
    {
        return $this->_getBooking();
    }
}
