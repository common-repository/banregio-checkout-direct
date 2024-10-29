<?php
/**
 * Created by PhpStorm.
 * User: gerson
 * Date: 13/03/19
 * Time: 03:23 PM
 */

/*
Plugin Name: Banregio Checkout Direct
Plugin URI: http://banregiolabs.com
Description: Realiza cobros utilizando el motor de pagos
Version: 1.0.0
Author: BanregioLabs
Author URI: http://banregiolabs.com
License: A "Slug" license name e.g. GPL2
*/
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
add_action( 'plugins_loaded', 'pbanmot_plugin_init', 0 );

function pbanmot_plugin_init() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		return;
	}

	include_once 'includes/WC_banregio_motor_pagos_gateway.php';
	add_filter( 'woocommerce_payment_gateways', 'pbanmot_add_custom_banregio_gateway' );


}

function pbanmot_add_custom_banregio_gateway( $methods ) {
	$methods[] = 'WC_banregio_motor_pagos_gateway';

	return $methods;
}