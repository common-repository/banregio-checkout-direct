<?php
/**
 * Created by PhpStorm.
 * User: gerson
 * Date: 13/03/19
 * Time: 03:33 PM
 */

class WC_banregio_motor_pagos_gateway extends WC_Payment_Gateway {
	function __construct() {
		// The global ID for this Payment method
		$this->id = "bnrg_woocomerce_payment_method_motor_pagos";

		// The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
		$this->method_title = "Banregio Direct Checkout/Motor de pagos";

		// The description for this Payment Gateway, shown on the actual Payment options page on the backend
		$this->method_description = "Banregio Payment Gateway Plug-in para WooCommerce, utilizando motor de pagos";

		// The title to be used for the vertical tabs that can be ordered top to bottom
		$this->title = "Banregio motor de pagos";

		// If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
		$this->icon = null;

		// Bool. Can be set to true if you want payment fields to show on the checkout
		// if doing a direct integration, which we are doing in this case
		$this->has_fields = true;

		// Supports the default credit card form
		$this->supports = array( 'default_credit_card_form' );

		// This basically defines your settings which are then loaded with init_settings()
		$this->init_form_fields();

		// After init_settings() is called, you can get the settings and load them into variables, e.g:
		// $this->title = $this->get_option( 'title' );
		$this->init_settings();

		// Turn these settings into variables we can use
		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}

		// Lets check for SSL
		add_action( 'admin_notices', array( $this, 'do_ssl_check' ) );

		// Save settings
		if ( is_admin() ) {
			// Versions over 2.0
			// Save our administration options. Since we are not going to be doing anything special
			// we have not defined 'process_admin_options' in this class so the method in the parent
			// class will be used instead
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
				$this,
				'process_admin_options'
			) );
		}

	}

	function init_form_fields() {
		$this->form_fields = array(
			'identificador_medio' => array(
				'title'       => 'Número de negocio',
				'type'        => 'text',
				'description' => 'Numero de afiliacion para ralizar cobros'
			),
			'toggle_enviroment'   => array(
				'title' => 'Habilitar/Deshabilitar producción',
				'type'  => 'checkbox',
				'label' => 'Habilita ambiente de producción'
			)
		);
	}

	function process_payment( $order_id ) {

		if ( $this->settings['toggle_enviroment'] == "yes" ) {
			$url_motor  = 'https://colecto.banregio.com';
			$mode_trans = 'PRD';

		} else {
			$url_motor  = 'https://testcolecto.banregio.com';
			$mode_trans = 'RND';
		}

		global $woocommerce;
		$order = new WC_Order( $order_id );
		require "vendor/autoload.php";
		//$url_motor = 'https://testcolecto.banregio.com';
		$client = new GuzzleHttp\Client( [
			"base_uri" => $url_motor
		] );

		$tz        = 'America/Mexico_City';
		$timestamp = time();
		$dt        = new DateTime( "now", new DateTimeZone( $tz ) );
		$dt->setTimestamp( $timestamp );


		$options = [
			'form_params' => [
				"bnrg_numero_tarjeta"   => filter_var( trim( $_POST[ $this->id . '-card-number' ] ), FILTER_SANITIZE_NUMBER_INT ),
				"bnrg_fecha_exp"        => filter_var( substr( $_POST[ $this->id . '-card-expiry' ], 0, 2 ) . substr( $_POST[ $this->id . '-card-expiry' ], 5, 6 ), FILTER_SANITIZE_NUMBER_INT ),
				"bnrg_codigo_seguridad" => filter_var( $_POST[ $this->id . '-card-cvc' ], FILTER_SANITIZE_NUMBER_INT ),
				"bnrg_cmd_trans"        => "VENTA",
				"bnrg_folio"            => strval( $order_id ),
				"bnrg_hora_local"       => $dt->format( 'his' ),
				"bnrg_fecha_local"      => $dt->format( 'dmY' ),
				"bnrg_monto_trans"      => $order->get_total(),
				"bnrg_modo_entrada"     => "MANUAL",
				"bnrg_id_medio"         => $this->settings['identificador_medio'],
				"bnrg_modo_trans"       => $mode_trans
			]
		];

		/*if ( empty( $options["json"]["bnrg_numero_tarjeta"] || $options["json"]["bnrg_fecha_exp"] || $options["json"]["bnrg_codigo_seguridad"] ) ) {
			$order->update_status( 'on-hold', __( "No se recibieron los datos de la tarjeta", 'woocommerce' ) );

			return parent::process_payment( $order_id );
		}*/

		try {
			$response = $client->post( "/adq/", $options );
		} catch ( Exception $e ) {
			$order->update_status( 'on-hold', __( $e->getMessage(), 'woocommerce' ) );
		}

		if ( $response->getStatusCode() == 200 ) {


			switch ( $response->getHeaderLine( "BNRG_CODIGO_PROC" ) ) {
				case "A":
					$woocommerce->cart->empty_cart();
					$order->payment_complete();

					return array(
						'result'   => 'success',
						'redirect' => $this->get_return_url( $order )
					);
					break;
				case "D":
					wc_add_notice( __( 'Error en pago: ', 'woothemes' ) . "Transacción declinada por el emisor.", 'error' );

					return null;
					break;
				case "R":
					wc_add_notice( __( 'Error en pago: ', 'woothemes' ) . "Transacción declinada por falta de permisos.", 'error' );

					return null;
					break;
				case "T":
					wc_add_notice( __( 'Error en pago: ', 'woothemes' ) . "Transacción no respondida.", 'error' );

					return null;
					break;
				case null:
					wc_add_notice( __( 'Error en pago: ', 'woothemes' ) . "Ocurrio un problema intenta mas tarde.", 'error' );

					return null;
					break;
				default:
					wc_add_notice( __( 'Error en pago: ', 'woothemes' ) . "Ocurrio un problema intenta mas tarde.", 'error' );

					return null;
					break;
			}

		}

	}

}