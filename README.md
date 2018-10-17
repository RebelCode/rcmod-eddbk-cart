# RebelCode - RebelCode - EDD Bookings - EDD Cart Module

[![Build Status](https://travis-ci.org/rebelcode/rcmod-eddbk-cart.svg?branch=master)](https://travis-ci.org/rebelcode/rcmod-eddbk-cart)
[![Code Climate](https://codeclimate.com/github/RebelCode/rcmod-eddbk-cart/badges/gpa.svg)](https://codeclimate.com/github/RebelCode/rcmod-eddbk-cart)
[![Test Coverage](https://codeclimate.com/github/RebelCode/rcmod-eddbk-cart/badges/coverage.svg)](https://codeclimate.com/github/RebelCode/rcmod-eddbk-cart/coverage)
[![Latest Stable Version](https://poser.pugx.org/rebelcode/rcmod-eddbk-cart/version)](https://packagist.org/packages/rebelcode/rcmod-eddbk-cart)

A RebelCode module that adds EDD cart bookings functionality to EDD Bookings.

# Requirements

* PHP 5.4 or later

_Note:_ UTC offset timezones, such as `"UTC+2"`, `UTC-5`, and the like, will only work on **PHP 5.5.10** or later.
This is due to a change in the [`DateTimeZone` constructor][1] at that PHP version.

[1]: http://php.net/manual/en/datetimezone.construct.php#refsect1-datetimezone.construct-changelog
