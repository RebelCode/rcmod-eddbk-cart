# Change log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## [[*next-version*]] - YYYY-MM-DD
### Fixed
- Incorrect booking timezone used for displaying booking info in the cart and confirmation page.

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
- Booking items in cart now have price ID association.=

## [0.1-alpha3] - 2018-06-11
### Fixed
- Bookings in cart no longer show the session length, in seconds, as a price option.
- Cart item spacing for theme compatibility.

## [0.1-alpha2] - 2018-06-06
### Added
- Booking info is now shown in the EDD cart for cart items that correspond to bookings.

## [0.1-alpha1] - 2018-06-04
Initial version.
