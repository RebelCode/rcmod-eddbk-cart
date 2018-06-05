<?php

use Dhii\Output\PlaceholderTemplate;
use Psr\Container\ContainerInterface;
use RebelCode\EddBookings\Cart\BookingPriceEvaluator;
use RebelCode\EddBookings\Cart\BookingValueAwareFactory;
use RebelCode\EddBookings\Cart\Module\AddBookingToCartHandler;
use RebelCode\EddBookings\Cart\Module\FilterCartItemPriceHandler;
use RebelCode\EddBookings\Cart\Module\RemoveBookingFromCartHandler;
use RebelCode\EddBookings\Cart\Module\RenderCartBookingInfoHandler;
use RebelCode\EddBookings\Cart\Module\SubmitBookingOnPaymentHandler;
use RebelCode\EddBookings\Cart\Module\ValidateCartBookingHandler;

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
            $c->get('eddbk_cart/cart_items')
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
            $c->get('bookings_select_rm'),
            $c->get('bookings_delete_rm'),
            $c->get('sql_expression_builder'),
            $c->get('eddbk_cart/cart_items')
        );
    },

    /*
     * The handler that deletes bookings when they are removed from the EDD cart.
     *
     * @since [*next-version*]
     */
    'eddbk_submit_booking_on_payment_handler' => function (ContainerInterface $c) {
        return new SubmitBookingOnPaymentHandler(
            $c->get('booking_transitioner'),
            $c->get('bookings_select_rm'),
            $c->get('bookings_update_rm'),
            $c->get('sql_expression_builder'),
            $c->get('eddbk_cart/cart_items')
        );
    },

    /*
     * The handler that validates bookings in the EDD cart.
     *
     * @since [*next-version*]
     */
    'eddbk_validate_cart_bookings_handler' => function (ContainerInterface $c) {
        return new ValidateCartBookingHandler(
            $c->get('edd_cart'),
            $c->get('booking_transitioner'),
            $c->get('bookings_select_rm'),
            $c->get('sql_expression_builder'),
            $c->get('eddbk_cart/cart_items')
        );
    },

    /*
     * The handler that filters cart item prices.
     *
     * @since [*next-version*]
     */
    'eddbk_filter_cart_item_price_handler' => function (ContainerInterface $c) {
        return new FilterCartItemPriceHandler(
            $c->get('bookings_select_rm'),
            $c->get('eddbk_booking_price_evaluator'),
            $c->get('eddbk_booking_value_aware_factory'),
            $c->get('sql_expression_builder'),
            $c->get('eddbk_cart/cart_items')
        );
    },

    /*
     * The handler that renders booking information in the EDD cart.
     *
     * @since [*next-version*]
     */
    'eddbk_render_cart_booking_info_handler' => function (ContainerInterface $c) {
        return new RenderCartBookingInfoHandler(
            $c->get('eddbk_cart_booking_info_template'),
            $c->get('bookings_select_rm'),
            $c->get('sql_expression_builder'),
            $c->get('eddbk_cart/cart_items')
        );
    },

    /*
     * The template for booking info in the EDD cart.
     *
     * @since [*next-version*]
     */
    'eddbk_cart_booking_info_template' => function (ContainerInterface $c) {
        $templateFile = $c->get('eddbk_cart/cart_items/templates/booking_info/file');
        $templatePath = EDDBK_CART_MODULE_TEMPLATES_DIR . DIRECTORY_SEPARATOR . $templateFile;
        $template = file_get_contents($templatePath);

        return new PlaceholderTemplate(
            $template,
            $c->get('eddbk_cart/cart_items/templates/booking_info/token_start'),
            $c->get('eddbk_cart/cart_items/templates/booking_info/token_end'),
            $c->get('eddbk_cart/cart_items/templates/booking_info/token_default')
        );
    },

    /*
     * The booking price evaluator.
     *
     * @since [*next-version*]
     */
    'eddbk_booking_price_evaluator' => function (ContainerInterface $c) {
        return new BookingPriceEvaluator(
            $c->get('eddbk_services_select_rm'),
            $c->get('sql_expression_builder')
        );
    },

    /*
     * The factory that creates booking value-aware instances.
     *
     * @since [*next-version*]
     */
    'eddbk_booking_value_aware_factory' => function (ContainerInterface $c) {
        return new BookingValueAwareFactory();
    },
];
