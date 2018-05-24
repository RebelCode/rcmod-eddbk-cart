<?php

return [
    /*
     * Config for the EDD cart.
     *
     * @since [*next-version*]
     */
    'edd_cart' => [
        /*
         * Config for cart items.
         *
         * @since [*next-version*]
         */
        'items' => [
            /*
             * Config the cart item data.
             *
             * @since [*next-version*]
             */
            'data' => [
                /**
                 * The EDD key where cart item data is stored for cart items.
                 *
                 * @since [*next-version*]
                 */
                'key'            => 'options',

                /*
                 * The main key used in cart item data by EDD Bookings.
                 *
                 * @since [*next-version*]
                 */
                'eddbk_key'      => 'eddbk',

                /*
                 * The key used in cart item data to store the booking ID.
                 *
                 * @since [*next-version*]
                 */
                'booking_id_key' => 'booking_id',
            ],
        ],
    ],
];
