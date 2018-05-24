<?php

use Psr\Container\ContainerInterface;
use RebelCode\EddBookings\Cart\Module\AddBookingToCartHandler;

return [
    /*
     * The EDD cart instance.
     *
     * @since [*next-version*]
     */
    'edd_cart' => function (ContainerInterface $c) {
        return EDD()->cart;
    },

    /*
     * The handler that adds bookings to the EDD cart.
     *
     * @since [*next-version*]
     */
    'eddbk_add_booking_to_cart_handler' => function (ContainerInterface $c) {
        return new AddBookingToCartHandler(
            $c->get('edd_cart'),
            $c->get('edd_cart/items')
        );
    }
];
