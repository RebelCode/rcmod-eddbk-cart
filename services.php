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
];
