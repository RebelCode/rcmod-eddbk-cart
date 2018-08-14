<?php

namespace RebelCode\EddBookings\Cart\Module;

use ArrayAccess;
use Carbon\Carbon;
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
use Psr\Container\ContainerInterface;
use Psr\EventManager\EventInterface;
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
    use GetBookingDisplayTimezoneCapableTrait;

    /* @since [*next-version*] */
    use ContainerGetPathCapableTrait;

    /* @since [*next-version*] */
    use ContainerGetCapableTrait;

    /* @since [*next-version*] */
    use CountIterableCapableTrait;

    /* @since [*next-version*] */
    use NormalizeTimezoneNameCapableTrait;

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
        $this->_setFallbackTz($fallbackTz);
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
     * @param array|stdClass|ArrayAccess|ContainerInterface $bookingData The booking data.
     *
     * @return string|Stringable The render result.
     */
    protected function _renderBookingInfo($bookingData)
    {
        $format   = $this->_containerGet($this->cartItemConfig, 'booking_datetime_format');
        $clientTz = $this->_getDisplayTimezone($bookingData);

        // Get timestamps from booking
        $startTs = $this->_containerGet($bookingData, 'start');
        $endTs   = $this->_containerGet($bookingData, 'end');

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
            'timezone_label' => $this->__('Timezone:'),
            'start_datetime' => $startTs,
            'end_datetime'   => $endTs,
            'start_text'     => $startStr,
            'end_text'       => $endStr,
            'timezone_text'  => $this->_normalizeTimezoneName($clientTz->getName())
        ]);
    }
}
