<?php

use Psr\Container\ContainerInterface;
use RebelCode\EddBookings\Cart\Module\AddBookingToCartHandler;
use RebelCode\EddBookings\Cart\Module\RemoveBookingFromCartHandler;

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
    },

    /*
     * The handler that deletes bookings when they are removed from the EDD cart.
     *
     * @since [*next-version*]
     */
    'eddbk_remove_booking_from_cart_handler' => function (ContainerInterface $c) {
        return new RemoveBookingFromCartHandler(
            $c->get('edd_cart'),
            $c->get('booking_select_rm'),
            $c->get('booking_delete_rm'),
            $c->get('sql_expression_builder'),
            $c->get('edd_cart/items')
        );
    },
];
