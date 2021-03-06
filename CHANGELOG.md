# Change log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [[*next-version*]] - YYYY-MM-DD

## [0.1-alpha12] - 2019-01-16
### Changed
- Timezone object creation is now outsourced by a trait from the `rebelcode/time-abstract` package.

## [0.1-alpha11] - 2018-12-17
### Fixed
- Processing session types without resources resulted in an exception being thrown.

## [0.1-alpha10] - 2018-12-05
### Added
- Showing booking resources and session type info in the cart and confirmation pages.

### Changed
- Booking price is now determined using the new session type data.
- Using a more readable date format in the cart and confirmation pages.

## [0.1-alpha9] - 2018-11-01
### Fixed
- The purchase confirmation page showed `{service_name}` instead of the actual service names.

## [0.1-alpha8] - 2018-10-30
### Changed
- Now using a services manager instead of CQRS resource models.
- The module now depends on the EDD Bookings services module.

## [0.1-alpha7] - 2018-08-15
### Fixed
- Incorrect booking timezone used for displaying booking info in the cart and confirmation page.
- Fatal error for undefined method call when calculating a cart booking's price.

### Changed
- Improved booking timezone fallback mechanism with better error handling and reporting.

## [0.1-alpha6] - 2018-08-01
### Added
- Cart items are now assigned a `price_id` that associates them with the selected session length.

### Changed
- Booking dates and times in the cart are shown in the client timezone, if it is available. 

### Fixed
- Added missing module dependencies `wp_bookings_cqrs` and `booking_logic`.

## [0.1-alpha5] - 2018-06-13
### Added
- Booking information is now shown on the confirmation (receipt) page.

## [0.1-alpha4] - 2018-06-12
### Changed
- Cart now shows booking info datetimes in the client timezone.

### Added
- Booking items in cart now have price ID association.

## [0.1-alpha3] - 2018-06-11
### Fixed
- Bookings in cart no longer show the session length, in seconds, as a price option.
- Cart item spacing for theme compatibility.

## [0.1-alpha2] - 2018-06-06
### Added
- Booking info is now shown in the EDD cart for cart items that correspond to bookings.

## [0.1-alpha1] - 2018-06-04
Initial version.
