<?php

namespace RebelCode\EddBookings\Cart\Module;

use ArrayAccess;
use Carbon\Carbon;
use DateTimeZone;
use Dhii\Data\Container\ContainerGetCapableTrait;
use Dhii\Data\Container\ContainerGetPathCapableTrait;
use Dhii\Data\Container\CreateContainerExceptionCapableTrait;
use Dhii\Data\Container\CreateNotFoundExceptionCapableTrait;
use Dhii\Data\Container\Exception\NotFoundExceptionInterface;
use Dhii\Data\Container\NormalizeContainerCapableTrait;
use Dhii\Data\Container\NormalizeKeyCapableTrait;
use Dhii\Exception\CreateInvalidArgumentExceptionCapableTrait;
use Dhii\Exception\CreateOutOfRangeExceptionCapableTrait;
use Dhii\I18n\StringTranslatingTrait;
use Dhii\Invocation\InvocableInterface;
use Dhii\Iterator\CountIterableCapableTrait;
use Dhii\Iterator\ResolveIteratorCapableTrait;
use Dhii\Output\TemplateAwareTrait;
use Dhii\Output\TemplateInterface;
use Dhii\Storage\Resource\SelectCapableInterface;
use Dhii\Util\Normalization\NormalizeIntCapableTrait;
use Dhii\Util\Normalization\NormalizeIterableCapableTrait;
use Dhii\Util\Normalization\NormalizeStringCapableTrait;
use Dhii\Util\String\StringableInterface as Stringable;
use Exception;
use Psr\Container\ContainerInterface;
use Psr\EventManager\EventInterface;
use RebelCode\Bookings\BookingInterface;
use stdClass;

/**
 * The handler for rendering booking info in the EDD cart, for cart items that correspond to bookings.
 *
 * @since [*next-version*]
 */
class RenderCartBookingInfoHandler implements InvocableInterface
{
    /* @since [*next-version*] */
    use TemplateAwareTrait;

    /* @since [*next-version*] */
    use ContainerGetPathCapableTrait;

    /* @since [*next-version*] */
    use ContainerGetCapableTrait;

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
    use NormalizeContainerCapableTrait;

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
     * The bookings SELECT resource model.
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
     * The fallback timezone.
     *
     * @since [*next-version*]
     *
     * @var string|Stringable|null
     */
    protected $fallbackTz;

    /**
     * Constructor.
     *
     * @since [*next-version*]
     *
     * @param TemplateInterface                             $template         The template to use to render the info.
     * @param SelectCapableInterface                        $bookingsSelectRm The bookings SELECT resource model.
     * @param object                                        $exprBuilder      The expression builder.
     * @param array|stdClass|ArrayAccess|ContainerInterface $cartItemConfig   The cart item data config.
     * @param string|Stringable|null                        $fallbackTz       The fallback timezone to use for bookings
     *                                                                        that do not have a client timezone.
     */
    public function __construct(
        TemplateInterface $template,
        SelectCapableInterface $bookingsSelectRm,
        $exprBuilder,
        $cartItemConfig,
        $fallbackTz
    ) {
        $this->_setTemplate($template);
        $this->bookingsSelectRm = $bookingsSelectRm;
        $this->exprBuilder      = $exprBuilder;
        $this->cartItemConfig   = $cartItemConfig;
        $this->fallbackTz       = $fallbackTz;
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

        $item = $event->getParam(0);
        $item = $this->_normalizeContainer($item);

        $dataKey       = $this->_containerGetPath($this->cartItemConfig, ['data', 'key']);
        $eddBkKey      = $this->_containerGetPath($this->cartItemConfig, ['data', 'eddbk_key']);
        $bookingIdKey  = $this->_containerGetPath($this->cartItemConfig, ['data', 'booking_id_key']);
        $bookingIdPath = [$dataKey, $eddBkKey, $bookingIdKey];

        try {
            $bookingId = $this->_containerGetPath($item, $bookingIdPath);
        } catch (NotFoundExceptionInterface $exception) {
            // Cart item does not have a booking ID, and thus does not correspond to a booking.
            return;
        }

        // Alias expression builder
        $b = $this->exprBuilder;

        // Fetch the corresponding booking from storage
        $condition = $b->eq($b->var('id'), $b->lit($bookingId));
        $bookings  = $this->bookingsSelectRm->select($condition);
        // Stop if no booking was found, or if multiple bookings matched the ID (for some reason?)
        if ($this->_countIterable($bookings) !== 1) {
            return;
        }

        // Get the booking
        $booking = reset($bookings);

        echo $this->_renderBookingInfo($booking);
    }

    /**
     * Renders the information for a booking.
     *
     * @since [*next-version*]
     *
     * @param BookingInterface $booking The booking.
     *
     * @return string|Stringable The render result.
     */
    protected function _renderBookingInfo(BookingInterface $booking)
    {
        $format   = $this->_containerGet($this->cartItemConfig, 'booking_datetime_format');
        $clientTz = $this->_getDisplayTimezone($booking);

        // Get timestamps from booking
        $startTs = $booking->getStart();
        $endTs   = $booking->getEnd();

        // Create date time helper instances
        $startDt = Carbon::createFromTimestampUTC($startTs);
        $endDt   = Carbon::createFromTimestampUTC($endTs);

        // Shift to client timezone, if available
        if ($clientTz !== null) {
            $startDt->setTimezone($clientTz);
            $endDt->setTimezone($clientTz);
        }

        // Format times to strings
        $startStr = $startDt->format($format);
        $endStr   = $endDt->format($format);

        return $this->_getTemplate()->render([
            'from_label'     => $this->__('From:'),
            'until_label'    => $this->__('Until:'),
            'start_datetime' => $startTs,
            'end_datetime'   => $endTs,
            'start_text'     => $startStr,
            'end_text'       => $endStr,
        ]);
    }

    /**
     * Retrieves the timezone to use for displaying booking dates and times.
     *
     * @since [*next-version*]
     *
     * @param BookingInterface $booking The booking instance.
     *
     * @return DateTimeZone The timezone instance.
     */
    protected function _getDisplayTimezone(BookingInterface $booking)
    {
        $try = [
            [$this, '_getBookingClientTimezone'],
            [$this, '_getFallbackTimezone'],
            [$this, '_getWordPressTimezone'],
        ];

        foreach ($try as $_callable) {
            try {
                return call_user_func_array($_callable, [$booking]);
            } catch (Exception $exception) {
                continue;
            }
        }

        return $this->_getServerTimezone();
    }

    /**
     * Retrieves the booking's client timezone.
     *
     * @since [*next-version*]
     *
     * @param BookingInterface $booking The booking instance.
     *
     * @return DateTimeZone The timezone instance.
     */
    protected function _getBookingClientTimezone(BookingInterface $booking)
    {
        $container    = $this->_normalizeContainer($booking);
        $clientTzName = $this->_containerGet($container, 'client_tz');

        return new DateTimeZone($clientTzName);
    }

    /**
     * Retrieves the fallback timezone.
     *
     * @since [*next-version*]
     *
     * @return DateTimeZone The timezone instance.
     */
    protected function _getFallbackTimezone()
    {
        return new DateTimeZone($this->_normalizeString($this->fallbackTz));
    }

    /**
     * Retrieves the WordPress timezone.
     *
     * @since [*next-version*]
     *
     * @return DateTimeZone The timezone instance.
     */
    protected function _getWordPressTimezone()
    {
        return new DateTimeZone($this->_getWordPressOption('timezone_string'));
    }

    /**
     * Retrieves the server timezone.
     *
     * @since [*next-version*]
     *
     * @return DateTimeZone The timezone instance.
     */
    protected function _getServerTimezone()
    {
        return new DateTimeZone(date_default_timezone_get());
    }

    /**
     * Retrieves the value for a wordpress option.
     *
     * @since [*next-version*]
     *
     * @param string|Stringable $key     The key of the option.
     * @param bool              $default The default value to return if the option with the given $key is not found.
     *
     * @return mixed|null The value of the option, or the value of the $default parameter if the option was not found.
     */
    protected function _getWordPressOption($key, $default = false)
    {
        return \get_option($this->_normalizeString($key), $default);
    }
}
