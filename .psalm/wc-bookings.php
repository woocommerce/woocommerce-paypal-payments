<?php

/**
 * Validate and create a new booking manually.
 *
 * @version  1.10.7
 * @see      WC_Booking::new_booking() for available $new_booking_data args
 * @param    int    $product_id you are booking
 * @param    array  $new_booking_data
 * @param    string $status
 * @param    bool   $exact If false, the function will look for the next available block after your start date if the date is unavailable.
 * @return   mixed  WC_Booking object on success or false on fail
 */
function create_wc_booking( $product_id, $new_booking_data = array(), $status = 'confirmed', $exact = false ) {}

/**
 * Returns true if the product is a booking product, false if not
 * @return bool
 */
function is_wc_booking_product( $product ) {}
