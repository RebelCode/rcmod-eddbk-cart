<?php

namespace RebelCode\EddBookings\Cart\Module;

use Carbon\Carbon;
use Dhii\Data\Container\ContainerGetCapableTrait;
use Dhii\Data\Container\CreateContainerExceptionCapableTrait;
use Dhii\Data\Container\CreateNotFoundExceptionCapableTrait;
use Dhii\Data\Container\NormalizeContainerCapableTrait;
use Dhii\Data\Container\NormalizeKeyCapableTrait;
use Dhii\Exception\CreateInvalidArgumentExceptionCapableTrait;
use Dhii\Exception\CreateOutOfRangeExceptionCapableTrait;
use Dhii\Exception\CreateRuntimeExceptionCapableTrait;
use Dhii\I18n\StringTranslatingTrait;
use Dhii\Invocation\InvocableInterface;
use Dhii\Output\TemplateAwareTrait;
use Dhii\Output\TemplateInterface;
use Dhii\Storage\Resource\SelectCapableInterface;
use Dhii\Util\Normalization\NormalizeStringCapableTrait;
use Dhii\Util\String\StringableInterface as Stringable;
use Exception;
use Psr\Container\ContainerExceptionInterface;
use Psr\EventManager\EventInterface;
use RebelCode\Bookings\BookingInterface;
use RuntimeException;
use Traversable;

/**
 * The handler that renders the bookings information table in the EDD purchase confirmation page.
 *
 * @since [*next-version*]
 */
class RenderConfirmationBookingsHandler implements InvocableInterface
{
    /* @since [*next-version*] */
    use TemplateAwareTrait;

    /* @since [*next-version*] */
    use GetBookingDisplayTimezoneCapableTrait;

    /* @since [*next-version*] */
    use ContainerGetCapableTrait;

    /* @since [*next-version*] */
    use NormalizeKeyCapableTrait;

    /* @since [*next-version*] */
    use NormalizeStringCapableTrait;

    /* @since [*next-version*] */
    use NormalizeContainerCapableTrait;

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
     * The template for rendering booking rows.
     *
     * @since [*next-version*]
     *
     * @var TemplateInterface
     */
    protected $bookingRowTemplate;

    /**
     * The bookings SELECT resource model.
     *
     * @since [*next-version*]
     *
     * @var SelectCapableInterface
     */
    protected $bookingsSelectRm;

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
     * The booking date and time format.
     *
     * @since [*next-version*]
     *
     * @var string|Stringable
     */
    protected $bookingFormat;

    /**
     * Constructor.
     *
     * @since [*next-version*]
     *
     * @param TemplateInterface      $bookingTableTemplate The bookings table template.
     * @param TemplateInterface      $bookingRowTemplate   The bookings table row template.
     * @param SelectCapableInterface $bookingsSelectRm     The bookings SELECT resource model.
     * @param SelectCapableInterface $servicesSelectRm     The services SELECT resource model.
     * @param object                 $exprBuilder          The expression builder.
     * @param string|Stringable      $bookingFormat        The bookings date and time format.
     * @param string|Stringable|null $fallbackTz           The fallback timezone name.
     */
    public function __construct(
        TemplateInterface $bookingTableTemplate,
        TemplateInterface $bookingRowTemplate,
        SelectCapableInterface $bookingsSelectRm,
        SelectCapableInterface $servicesSelectRm,
        $exprBuilder,
        $bookingFormat,
        $fallbackTz
    ) {
        $this->_setTemplate($bookingTableTemplate);
        $this->_setFallbackTz($fallbackTz);
        $this->bookingRowTemplate = $bookingRowTemplate;
        $this->bookingsSelectRm   = $bookingsSelectRm;
        $this->servicesSelectRm   = $servicesSelectRm;
        $this->exprBuilder        = $exprBuilder;
        $this->bookingFormat      = $bookingFormat;
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

        $payment   = $event->getParam(0);
        $paymentId = $payment->ID;

        // Alias expression builder
        $b = $this->exprBuilder;

        // Fetch the corresponding booking from storage
        $condition = $b->eq($b->var('payment_id'), $b->lit($paymentId));
        $bookings  = $this->bookingsSelectRm->select($condition);

        return $this->_renderBookingsTable($bookings);
    }

    /**
     * Renders the bookings table.
     *
     * @since [*next-version*]
     *
     * @param BookingInterface[]|Traversable $bookings The bookings to render in the table.
     *
     * @return string|Stringable The render output.
     */
    protected function _renderBookingsTable($bookings)
    {
        $rows = '';
        foreach ($bookings as $_booking) {
            if (!($_booking instanceof BookingInterface)) {
                throw $this->_createOutOfRangeException(
                    $this->__('Booking is not a valid booking instance'), null, null, $_booking
                );
            }

            $rows = $this->_renderBookingRow($_booking);
        }

        return $this->_getTemplate()->render([
            'table_heading'        => $this->__('Bookings'),
            'service_column'       => $this->__('Service'),
            'booking_start_column' => $this->__('Start'),
            'booking_end_column'   => $this->__('End'),
            'booking_tz_column'    => $this->__('Timezone'),
            'booking_rows'         => $rows,
        ]);
    }

    /**
     * Renders a booking row.
     *
     * @since [*next-version*]
     *
     * @param BookingInterface $booking The booking instance.
     *
     * @return string|Stringable The rendered booking row.
     */
    protected function _renderBookingRow(BookingInterface $booking)
    {
        $timezone = $this->_getDisplayTimezone($booking);

        // Get timestamps from booking
        $startTs = $booking->getStart();
        $endTs   = $booking->getEnd();

        // Create date time helper instances
        $startDt = Carbon::createFromTimestampUTC($startTs);
        $endDt   = Carbon::createFromTimestampUTC($endTs);

        // Shift to client timezone, if available
        if ($timezone !== null) {
            $startDt->setTimezone($timezone);
            $endDt->setTimezone($timezone);
        }

        // Format times to strings
        $startStr = $startDt->format($this->bookingFormat);
        $endStr   = $endDt->format($this->bookingFormat);

        return $this->bookingRowTemplate->render([
            'service_name' => $this->_getServiceName($booking),
            'start_text'   => $startStr,
            'end_text'     => $endStr,
            'timezone'     => $timezone->getName(),
        ]);
    }

    /**
     * Retrieves the name of the service for a booking.
     *
     * @since [*next-version*]
     *
     * @param BookingInterface $booking The booking instance.
     *
     * @return string|Stringable The name of the service.
     *
     * @throws RuntimeException If failed to retrieve the service name or the service ID from the booking.
     */
    protected function _getServiceName(BookingInterface $booking)
    {
        try {
            $container = $this->_normalizeContainer($booking);
            $serviceId = $this->_containerGet($container, 'service_id');
        } catch (Exception $exception) {
            /*
             * Catches:
             * - Invalid argument exception if the booking is not a container
             * - Container exception if an error occurred while reading
             * - Container not found exception if the service ID was not found
             */
            throw $this->_createRuntimeException(
                $this->__('Failed to get the ID of the service for a booking'), null, $exception
            );
        }

        // Alias expression builder
        $b = $this->exprBuilder;
        // EDD Bookings' services select RM only supports AND top-level expressions
        $services = $this->servicesSelectRm->select($b->and(
            $b->eq(
                $b->ef('service', 'id'),
                $b->lit($serviceId)
            )
        ));

        $service = reset($services) ? : null;

        try {
            return $this->_containerGet($service, 'name');
        } catch (ContainerExceptionInterface $exception) {
            throw $this->_createRuntimeException(
                $this->__('Failed to get the name of the service for a booking'), null, $exception
            );
        }
    }
}
