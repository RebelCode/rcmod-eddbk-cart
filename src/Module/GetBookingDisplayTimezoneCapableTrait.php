<?php

namespace RebelCode\EddBookings\Cart\Module;

use ArrayAccess;
use DateTimeZone;
use Dhii\Util\String\StringableInterface as Stringable;
use Exception;
use Exception as RootException;
use InvalidArgumentException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\ContainerInterface as BaseContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use stdClass;

/**
 * Common functionality for retrieving a booking's display timezone.
 *
 * @since [*next-version*]
 */
trait GetBookingDisplayTimezoneCapableTrait
{
    /**
     * The fallback timezone.
     *
     * @since [*next-version*]
     *
     * @var string|Stringable|null
     */
    protected $fallbackTz;

    /**
     * Retrieves the fallback timezone.
     *
     * @since [*next-version*]
     *
     * @return string|Stringable|null
     */
    protected function _getFallbackTz()
    {
        return $this->fallbackTz;
    }

    /**
     * Sets the fallback timezone.
     *
     * @since [*next-version*]
     *
     * @param string|Stringable|null $fallbackTz
     */
    protected function _setFallbackTz($fallbackTz)
    {
        if (!is_null($fallbackTz) && !is_string($fallbackTz) && !($fallbackTz instanceof Stringable)) {
            throw $this->_createInvalidArgumentException(
                $this->__('Argument is not a string or stringable object'), null, null, $fallbackTz
            );
        }

        $this->fallbackTz = $fallbackTz;
    }

    /**
     * Retrieves the timezone to use for displaying booking dates and times.
     *
     * @since [*next-version*]
     *
     * @param array|stdClass|ArrayAccess|ContainerInterface $bookingData The booking data.
     *
     * @return DateTimeZone The timezone instance.
     */
    protected function _getDisplayTimezone($bookingData)
    {
        $try = [
            [$this, '_getBookingClientTimezone'],
            [$this, '_getFallbackTimezone'],
            [$this, '_getWordPressTimezone'],
        ];

        foreach ($try as $_callable) {
            try {
                return call_user_func_array($_callable, [$bookingData]);
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
     * @param array|stdClass|ArrayAccess|ContainerInterface $bookingData The booking data.
     *
     * @return DateTimeZone The timezone instance.
     */
    protected function _getBookingClientTimezone($bookingData)
    {
        $clientTzName = $this->_containerGet($bookingData, 'client_tz');

        return $this->_createDateTimeZone($clientTzName);
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
        return $this->_createDateTimeZone($this->_normalizeString($this->fallbackTz));
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
        $wpTimezone = $this->_getWordPressOption('timezone_string');

        if (empty($wpTimezone)) {
            // Get GMT offset
            $gmtOffset = (float) $this->_getWordPressOption('gmt_offset');
            // Convert into a time decimal (ex. 2.5 => 2.3) with the decimal part being in minutes
            $hours   = intval($gmtOffset);
            $minutes = 0.6 * ($gmtOffset - $hours);
            $decimal = $hours + $minutes;

            // Convert into a UTC timezone
            $wpTimezone = sprintf('UTC%+05.0f', $decimal * 100);
        }

        return $this->_createDateTimeZone($wpTimezone);
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
        return $this->_createDateTimeZone(date_default_timezone_get());
    }

    /**
     * Creates a {@link DateTimeZone} object for a timezone, by name.
     *
     * @see DateTimeZone
     *
     * @since [*next-version*]
     *
     * @param string|Stringable $tzName The name of the timezone.
     *
     * @return DateTimeZone The created {@link DateTimeZone} instance.
     *
     * @throws InvalidArgumentException If the timezone name is not a string or stringable object.
     * @throws OutOfRangeException If the timezone name is invalid and does not represent a valid timezone.
     */
    protected function _createDateTimeZone($tzName)
    {
        $argTz  = $tzName;
        $tzName = $this->_normalizeString($tzName);

        // If the timezone is a UTC offset timezone, transform into a valid DateTimeZone offset.
        // See http://php.net/manual/en/datetimezone.construct.php
        if (preg_match('/^UTC(\+|\-)(\d{1,2})(:?(\d{2}))?$/', $tzName, $matches) && count($matches) >= 2) {
            $sign    = $matches[1];
            $hours   = (int) $matches[2];
            $minutes = count($matches) >= 4 ? (int) $matches[4] : 0;
            $tzName  = sprintf('%s%02d%02d', $sign, $hours, $minutes);
        }

        try {
            return new DateTimeZone($tzName);
        } catch (Exception $exception) {
            throw $this->_createOutOfRangeException(
                $this->__('Invalid timezone name: "%1$s"', [$argTz]), null, $exception, $argTz
            );
        }
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

    /**
     * Normalizes a value to its string representation.
     *
     * The values that can be normalized are any scalar values, as well as
     * {@see StringableInterface).
     *
     * @since [*next-version*]
     *
     * @param Stringable|string|int|float|bool $subject The value to normalize to string.
     *
     * @throws InvalidArgumentException If the value cannot be normalized.
     *
     * @return string The string that resulted from normalization.
     */
    abstract protected function _normalizeString($subject);

    /**
     * Retrieves a value from a container or data set.
     *
     * @since [*next-version*]
     *
     * @param array|ArrayAccess|stdClass|BaseContainerInterface $container The container to read from.
     * @param string|int|float|bool|Stringable                  $key       The key of the value to retrieve.
     *
     * @throws InvalidArgumentException    If container is invalid.
     * @throws ContainerExceptionInterface If an error occurred while reading from the container.
     * @throws NotFoundExceptionInterface  If the key was not found in the container.
     *
     * @return mixed The value mapped to the given key.
     */
    abstract protected function _containerGet($container, $key);

    /**
     * Normalizes a container.
     *
     * @since [*next-version*]
     *
     * @param array|ArrayAccess|stdClass|BaseContainerInterface $container The container to normalize.
     *
     * @throws InvalidArgumentException If the container is invalid.
     *
     * @return array|ArrayAccess|stdClass|BaseContainerInterface Something that can be used with
     *                                                           {@see ContainerGetCapableTrait#_containerGet()} or
     *                                                           {@see ContainerHasCapableTrait#_containerHas()}.
     */
    abstract protected function _normalizeContainer($container);

    /**
     * Creates a new Dhii invalid argument exception.
     *
     * @since [*next-version*]
     *
     * @param string|Stringable|int|float|bool|null $message  The message, if any.
     * @param int|float|string|Stringable|null      $code     The numeric error code, if any.
     * @param RootException|null                    $previous The inner exception, if any.
     * @param mixed|null                            $argument The invalid argument, if any.
     *
     * @return InvalidArgumentException The new exception.
     */
    abstract protected function _createInvalidArgumentException(
        $message = null,
        $code = null,
        RootException $previous = null,
        $argument = null
    );

    /**
     * Creates a new Dhii Out Of Range exception.
     *
     * @since [*next-version*]
     *
     * @param string|Stringable|int|float|bool|null $message  The message, if any.
     * @param int|float|string|Stringable|null      $code     The numeric error code, if any.
     * @param RootException|null                    $previous The inner exception, if any.
     * @param mixed|null                            $argument The value that is out of range, if any.
     *
     * @return OutOfRangeException The new exception.
     */
    abstract protected function _createOutOfRangeException(
        $message = null,
        $code = null,
        RootException $previous = null,
        $argument = null
    );

    /**
     * Translates a string, and replaces placeholders.
     *
     * @since [*next-version*]
     * @see   sprintf()
     * @see   _translate()
     *
     * @param string $string  The format string to translate.
     * @param array  $args    Placeholder values to replace in the string.
     * @param mixed  $context The context for translation.
     *
     * @return string The translated string.
     */
    abstract protected function __($string, $args = [], $context = null);
}
