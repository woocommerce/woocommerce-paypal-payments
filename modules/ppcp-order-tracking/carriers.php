<?php
/**
 * The order tracking carriers.
 *
 * @package WooCommerce\PayPalCommerce\OrderTracking
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\OrderTracking;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

	return array(
		'global' => array(
			'name'  => 'Global',
			'items' => array(
				'B_TWO_C_EUROPE'       => _x( 'B2C Europe', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'CJ_LOGISTICS'         => _x( 'CJ Logistics', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'CORREOS_EXPRESS'      => _x( 'Correos Express', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DHL_ACTIVE_TRACING'   => _x( 'DHL Active Tracing', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DHL_BENELUX'          => _x( 'DHL Benelux', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DHL_GLOBAL_MAIL'      => _x( 'DHL ecCommerce US', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DHL_GLOBAL_MAIL_ASIA' => _x( 'DHL eCommerce Asia', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DHL'                  => _x( 'DHL Express', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DHL_GLOBAL_ECOMMERCE' => _x( 'DHL Global eCommerce', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DHL_PACKET'           => _x( 'DHL Packet', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DPD'                  => _x( 'DPD Global', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DPD_LOCAL'            => _x( 'DPD Local', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DPD_LOCAL_REF'        => _x( 'DPD Local Reference', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DPE_EXPRESS'          => _x( 'DPE Express', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DPEX'                 => _x( 'DPEX Hong Kong', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DTDC_EXPRESS'         => _x( 'DTDC Express Global', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'ESHOPWORLD'           => _x( 'EShopWorld', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'FEDEX'                => _x( 'FedEx', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'FLYT_EXPRESS'         => _x( 'FLYT Express', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'GLS'                  => _x( 'GLS', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'IMX'                  => _x( 'IMX France', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'INT_SUER'             => _x( 'International SEUR', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'LANDMARK_GLOBAL'      => _x( 'Landmark Global', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'MATKAHUOLTO'          => _x( 'Matkahuoloto', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'OMNIPARCEL'           => _x( 'Omni Parcel', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'ONE_WORLD'            => _x( 'One World', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'POSTI'                => _x( 'Posti', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'RABEN_GROUP'          => _x( 'Raben Group', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'SF_EXPRESS'           => _x( 'SF EXPRESS', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'SKYNET_Worldwide'     => _x( 'SkyNet Worldwide Express', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'SPREADEL'             => _x( 'Spreadel', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'TNT'                  => _x( 'TNT Global', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'UPS'                  => _x( 'UPS', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'UPS_MI'               => _x( 'UPS Mail Innovations', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'WEBINTERPRET'         => _x( 'WebInterpret', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),

		),
		'AG'     => array(
			'name'  => _x( 'Antigua and Barbuda', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'CORREOS_AG' => _x( 'Correos Antigua and Barbuda', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'AR'     => array(
			'name'  => _x( 'Argentina', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'EMIRATES_POST' => _x( 'Emirates Post', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'OCA_AR	'       => _x( 'OCA Argentina', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'AU'     => array(
			'name'  => _x( 'Australia', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'ADSONE'            => _x( 'Adsone', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'AUSTRALIA_POST'    => _x( 'Australia Post', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'TOLL_AU'           => _x( 'Australia Toll', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'BONDS_COURIERS'    => _x( 'Bonds Couriers', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'COURIERS_PLEASE'   => _x( 'Couriers Please', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DHL_AU'            => _x( 'DHL Australia', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DTDC_AU'           => _x( 'DTDC Australia', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'FASTWAY_AU'        => _x( 'Fastway Australia', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'HUNTER_EXPRESS	'   => _x( 'Hunter Express', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'SENDLE'            => _x( 'Sendle', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'STARTRACK'         => _x( 'Star Track', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'STARTRACK_EXPRESS' => _x( 'Star Track Express', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'TNT_AU	'           => _x( 'TNT Australia', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'TOLL'              => _x( 'Toll', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'UBI_LOGISTICS'     => _x( 'UBI Logistics', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'AT'     => array(
			'name'  => _x( 'Austria', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'AUSTRIAN_POST_EXPRESS' => _x( 'Austrian Post Express', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'AUSTRIAN_POST'         => _x( 'Austrian Post Registered', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DHL_AT'                => _x( 'DHL Austria', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'BE'     => array(
			'name'  => _x( 'Belgium', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'BPOST'      => _x( 'bpost', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'BPOST_INT'  => _x( 'bpost International', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'MONDIAL_BE' => _x( 'Mondial Belgium', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'TAXIPOST'   => _x( 'TaxiPost', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'BR'     => array(
			'name'  => _x( 'Brazil', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'CORREOS_BR'   => _x( 'Correos Brazil', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DIRECTLOG_BR' => _x( 'Directlog', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'BG'     => array(
			'name'  => _x( 'Bulgaria', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'BULGARIAN_POST' => _x( 'Bulgarian Post', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'CA'     => array(
			'name'  => _x( 'Canada', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'CANADA_POST' => _x( 'Canada Post', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'CANPAR'      => _x( 'Canpar', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'GREYHOUND'   => _x( 'Greyhound', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'LOOMIS'      => _x( 'Loomis', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'PUROLATOR'   => _x( 'Purolator', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'CL'     => array(
			'name'  => _x( 'Chile', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'CORREOS_CL' => _x( 'Correos Chile', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'CN'     => array(
			'name'  => _x( 'China', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'FOUR_PX_EXPRESS' => _x( 'Correos', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'AUPOST_CN'       => _x( 'AUPOST CHINA', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'BQC_EXPRESS'     => _x( 'BQC Express', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'BUYLOGIC'        => _x( 'Buylogic', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'CHINA_POST'      => _x( 'China Post', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'CNEXPS'          => _x( 'CN Exps', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'EC_CN'           => _x( 'EC China', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'EFS'             => _x( 'EFS', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'EMPS_CN'         => _x( 'EMPS China', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'EMS_CN'          => _x( 'EMS China', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'HUAHAN_EXPRESS'  => _x( 'Huahan Express', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'SFC_EXPRESS'     => _x( 'SFC Express', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'TNT_CN'          => _x( 'TNT China', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'WINIT'           => _x( 'WinIt', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'YANWEN_CN'       => _x( 'Yanwen', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'CR'     => array(
			'name'  => _x( 'Costa Rica', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'CORREOS_CR' => _x( 'Correos De Costa Rica', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'HR'     => array(
			'name'  => _x( 'Croatia', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'HRVATSKA_HR' => _x( 'Hrvatska', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'CY'     => array(
			'name'  => _x( 'Cyprus', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'CYPRUS_POST_CYP' => _x( 'Cyprus Post', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'CZ'     => array(
			'name'  => _x( 'Czech Republic', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'CESKA_CZ' => _x( 'Ceska', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'GLS_CZ'   => _x( 'GLS Czech Republic', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'FR'     => array(
			'name'  => _x( 'France', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'BERT'            => _x( 'BERT TRANSPORT', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'CHRONOPOST_FR'   => _x( 'Chronopost France', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'COLIPOSTE'       => _x( 'Coliposte', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'COLIS'           => _x( 'Colis France', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DHL_FR'          => _x( 'DHL France', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DPD_FR'          => _x( 'DPD France', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'GEODIS'          => _x( 'GEODIS - Distribution & Express', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'GLS_FR'          => _x( 'GLS France', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'LAPOSTE'         => _x( 'LA Poste', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'MONDIAL'         => _x( 'Mondial Relay', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'RELAIS_COLIS_FR' => _x( 'Relais Colis', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'TELIWAY'         => _x( 'Teliway', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'TNT_FR'          => _x( 'TNT France', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'DE'     => array(
			'name'  => _x( 'Germany', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'ASENDIA_DE'        => _x( 'Asendia Germany', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DELTEC_DE'         => _x( 'Deltec Germany', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DEUTSCHE_DE'       => _x( 'Deutsche', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DHL_DEUTSCHE_POST' => _x( 'DHL Deutsche Post', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DPD_DE'            => _x( 'DPD Germany', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'GLS_DE'            => _x( 'GLS Germany', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'HERMES_DE'         => _x( 'Hermes Germany', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'TNT_DE'            => _x( 'TNT Germany', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'GR'     => array(
			'name'  => _x( 'Greece', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'ELTA_GR'   => _x( 'ELTA Greece', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'GENIKI_GR' => _x( 'Geniki Greece', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'ACS_GR'    => _x( 'GRC Greece', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'HK'     => array(
			'name'  => _x( 'Hong Kong', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'ASENDIA_HK'            => _x( 'Asendia Hong Kong', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DHL_HK'                => _x( 'DHL Hong Kong', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DPD_HK'                => _x( 'DPD Hong Kong', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'HK_POST'               => _x( 'Hong Kong Post', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'KERRY_EXPRESS_HK'      => _x( 'Kerry Express Hong Kong', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'LOGISTICSWORLDWIDE_HK' => _x( 'Logistics Worldwide Hong Kong', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'QUANTIUM'              => _x( 'Quantium', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'SEKOLOGISTICS'         => _x( 'Seko Logistics', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'TAQBIN_HK'             => _x( 'TA-Q-BIN Parcel Hong Kong', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'HU'     => array(
			'name'  => _x( 'Hungary', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'MAGYAR_HU' => _x( 'Magyar', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'IS'     => array(
			'name'  => _x( 'Iceland', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'POSTUR_IS' => _x( 'Postur', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'IN'     => array(
			'name'  => _x( 'India', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'BLUEDART'              => _x( 'Bluedart', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DELHIVERY_IN'          => _x( 'Delhivery', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DOTZOT'                => _x( 'DotZot', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DTDC_IN'               => _x( 'DTDC India', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'EKART'                 => _x( 'Ekart', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'INDIA_POST'            => _x( 'India Post', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'PROFESSIONAL_COURIERS' => _x( 'Professional Couriers', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'REDEXPRESS'            => _x( 'Red Express', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'SWIFTAIR'              => _x( 'Swift Air', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'XPRESSBEES'            => _x( 'Xpress Bees', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'ID'     => array(
			'name'  => _x( 'Indonesia', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'FIRST_LOGISITCS' => _x( 'First Logistics', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'JNE_IDN'         => _x( 'JNE Indonesia', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'LION_PARCEL'     => _x( 'Lion Parcel', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'NINJAVAN_ID'     => _x( 'Ninjavan Indonesia', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'PANDU'           => _x( 'Pandu Logistics', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'POS_ID'          => _x( 'Pos Indonesia Domestic', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'POS_INT'         => _x( 'Pos Indonesia International', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'RPX_ID'          => _x( 'RPX Indonesia', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'RPX'             => _x( 'RPX International', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'TIKI_ID'         => _x( 'Tiki', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'WAHANA_ID'       => _x( 'Wahana', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'IE'     => array(
			'name'  => _x( 'Ireland', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'AN_POST'    => _x( 'AN POST Ireland', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DPD_IR'     => _x( 'DPD Ireland', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'MASTERLINK' => _x( 'Masterlink', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'TPG'        => _x( 'TPG', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'WISELOADS'  => _x( 'Wiseloads', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'IL'     => array(
			'name'  => _x( 'Israel', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'ISRAEL_POST' => _x( 'Israel Post', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'IT'     => array(
			'name'  => _x( 'Italy', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'BRT_IT'           => _x( 'BRT Bartolini', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DHL_IT'           => _x( 'DHL Italy', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DMM_NETWORK'      => _x( 'DMM Network', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'FERCAM_IT'        => _x( 'FERCAM Logistics & Transport', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'GLS_IT'           => _x( 'GLS Italy', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'HERMES_IT'        => _x( 'Hermes Italy', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'POSTE_ITALIANE'   => _x( 'Poste Italiane', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'REGISTER_MAIL_IT' => _x( 'Register Mail IT', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'SDA_IT'           => _x( 'SDA Italy', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'SGT_IT'           => _x( 'SGT Corriere Espresso', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'TNT_CLICK_IT'     => _x( 'TNT Click Italy', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'TNT_IT'           => _x( 'TNT Italy', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'JP'     => array(
			'name'  => _x( 'Japan', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'DHL_JP'     => _x( 'DHL Japan', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'JP_POST'    => _x( 'JP Post', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'JAPAN_POST' => _x( 'Japan Post', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'POCZTEX'    => _x( 'Pocztex', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'SAGAWA'     => _x( 'Sagawa', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'SAGAWA_JP'  => _x( 'Sagawa JP', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'TNT_JP'     => _x( 'TNT Japan', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'YAMATO'     => _x( 'Yamato Japan', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'KR'     => array(
			'name'  => _x( 'Korea', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'ECARGO'                => _x( 'Ecargo', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'EPARCEL_KR'            => _x( 'eParcel Korea', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'KOREA_POST'            => _x( 'Korea Post', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'KOR_KOREA_POST'        => _x( 'KOR Korea Post', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'CJ_KR'                 => _x( 'Korea Thai CJ', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'LOGISTICSWORLDWIDE_KR' => _x( 'Logistics Worldwide Korea', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'PANTOS'                => _x( 'Pantos', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'RINCOS'                => _x( 'Rincos', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'ROCKET_PARCEL'         => _x( 'Rocket Parcel International', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'SRE_KOREA'             => _x( 'SRE Korea', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'LT'     => array(
			'name'  => _x( 'Lithuania', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'LIETUVOS_LT' => _x( 'Lietuvos Pastas', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'MY'     => array(
			'name'  => _x( 'Malaysia', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'AIRPAK_MY'             => _x( 'Airpak', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'CITYLINK_MY'           => _x( 'CityLink Malaysia', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'CJ_MY'                 => _x( 'CJ Malaysia', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'CJ_INT_MY'             => _x( 'CJ Malaysia International', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'CUCKOOEXPRESS'         => _x( 'Cuckoo Express', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'JETSHIP_MY'            => _x( 'Jet Ship Malaysia', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'KANGAROO_MY'           => _x( 'Kangaroo Express', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'LOGISTICSWORLDWIDE_MY' => _x( 'Logistics Worldwide Malaysia', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'MALAYSIA_POST'         => _x( 'Malaysia Post EMS / Pos Laju', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'NATIONWIDE'            => _x( 'Nationwide', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'NINJAVAN_MY'           => _x( 'Ninjavan Malaysia', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'SKYNET_MY'             => _x( 'Skynet Malaysia', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'TAQBIN_MY'             => _x( 'TA-Q-BIN Parcel Malaysia', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'MX'     => array(
			'name'  => _x( 'Mexico', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'CORREOS_MX' => _x( 'Correos De Mexico', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'ESTAFETA'   => _x( 'Estafeta', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'AEROFLASH'  => _x( 'Mexico Aeroflash', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'REDPACK'    => _x( 'Mexico Redpack', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'SENDA_MX'   => _x( 'Mexico Senda Express', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'NL'     => array(
			'name'  => _x( 'Netherlands', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'DHL_NL'         => _x( 'DHL Netherlands', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DHL_PARCEL_NL'  => _x( 'DHL Parcel Netherlands', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'GLS_NL'         => _x( 'GLS Netherlands', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'KIALA'          => _x( 'Kiala', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'POSTNL'         => _x( 'PostNL', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'POSTNL_INT'     => _x( 'PostNl International', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'POSTNL_INT_3_S' => _x( 'PostNL International 3S', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'TNT_NL'         => _x( 'TNT Netherlands', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'TRANSMISSION'   => _x( 'Transmission Netherlands', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'NZ'     => array(
			'name'  => _x( 'New Zealand', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'COURIER_POST' => _x( 'Courier Post', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'FASTWAY_NZ'   => _x( 'Fastway New Zealand', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'NZ_POST'      => _x( 'New Zealand Post', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'TOLL_IPEC'    => _x( 'Toll IPEC', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'NG'     => array(
			'name'  => _x( 'Nigeria', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'COURIERPLUS' => _x( 'Courier Plus', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'NIPOST_NG'   => _x( 'NiPost', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'NO'     => array(
			'name'  => _x( 'Norway', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'POSTEN_NORGE' => _x( 'Posten Norge', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'PH'     => array(
			'name'  => _x( 'Philippines', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'TWO_GO'          => _x( '2GO', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'AIR_21'          => _x( 'Air 21', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'AIRSPEED'        => _x( 'Airspeed', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'JAMEXPRESS_PH'   => _x( 'Jam Express', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'LBC_PH'          => _x( 'LBC Express', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'NINJAVAN_PH'     => _x( 'Ninjavan Philippines', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'RAF_PH'          => _x( 'RAF Philippines', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'XEND_EXPRESS_PH' => _x( 'Xend Express', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'PL'     => array(
			'name'  => _x( 'Poland', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'DHL_PL'            => _x( 'DHL Poland', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DPD_PL'            => _x( 'DPD Poland', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'INPOST_PACZKOMATY' => _x( 'InPost Paczkomaty', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'POCZTA_POLSKA'     => _x( 'Poczta Polska', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'SIODEMKA'          => _x( 'Siodemka', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'TNT_PL'            => _x( 'TNT Poland', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'PT'     => array(
			'name'  => _x( 'Portugal', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'ADICIONAL_PT'  => _x( 'Adicional Logistics', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'CHRONOPOST_PT' => _x( 'Chronopost Portugal', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'CTT_PT'        => _x( 'Portugal PTT', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'SEUR_PT'       => _x( 'Portugal Seur', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'RO'     => array(
			'name'  => _x( 'Romania', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'DPD_RO'   => _x( 'DPD Romania', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'POSTA_RO' => _x( 'Postaromana', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'RU'     => array(
			'name'  => _x( 'Russia', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'DPD_RU'       => _x( 'DPD Russia', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'RUSSIAN_POST' => _x( 'Russian Post', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'SA'     => array(
			'name'  => _x( 'Saudi Arabia', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'DAWN_WING'       => _x( 'Dawn Wing', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'RAM'             => _x( 'Ram', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'THE_COURIER_GUY' => _x( 'The Courier Guy', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'CS'     => array(
			'name'  => _x( 'Serbia', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'POST_SERBIA_CS' => _x( 'Serbia Post', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'SG'     => array(
			'name'  => _x( 'Singapore', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'DHL_SG'        => _x( 'DHL Singapore', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'JETSHIP_SG'    => _x( 'JetShip Singapore', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'NINJAVAN_SG'   => _x( 'Ninjavan Singapore', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'PARCELPOST_SG' => _x( 'Parcel Post', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'SINGPOST'      => _x( 'Singapore Post', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'TAQBIN_SG'     => _x( 'TA-Q-BIN Parcel Singapore', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'ZA'     => array(
			'name'  => _x( 'South Africa', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'FASTWAY_ZA' => _x( 'Fastway South Africa', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'ES'     => array(
			'name'  => _x( 'Spain', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'ASM_ES'        => _x( 'ASM', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'CBL_LOGISTICA' => _x( 'CBL Logistics', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'CORREOS_ES'    => _x( 'Correos De Spain', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DHL_ES	'       => _x( 'DHL Spain', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DHL_PARCEL_ES' => _x( 'DHL Parcel Spain', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'GLS_ES'        => _x( 'GLS Spain', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'INT_SEUR'      => _x( 'International Suer', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'ITIS'          => _x( 'ITIS', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'NACEX_ES'      => _x( 'Nacex Spain', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'REDUR_ES'      => _x( 'Redur Spain', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'SEUR_ES'       => _x( 'Spanish Seur', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'TNT_ES'        => _x( 'TNT Spain', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'SE'     => array(
			'name'  => _x( 'Sweden', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'DBSCHENKER_SE'             => _x( 'DB Schenker Sweden', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DIRECTLINK_SE'             => _x( 'DirectLink Sweden', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'POSTNORD_LOGISTICS_GLOBAL' => _x( 'PostNord Logistics', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'POSTNORD_LOGISTICS_DK'     => _x( 'PostNord Logistics Denmark', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'POSTNORD_LOGISTICS_SE'     => _x( 'PostNord Logistics Sweden', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'CH'     => array(
			'name'  => _x( 'Switzerland', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'SWISS_POST' => _x( 'Swiss Post', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'TW'     => array(
			'name'  => _x( 'Taiwan', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'CHUNGHWA_POST'  => _x( 'Chunghwa Post', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'TAIWAN_POST_TW' => _x( 'Taiwan Post', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'TH'     => array(
			'name'  => _x( 'Thailand', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'ACOMMMERCE'       => _x( 'Acommerce', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'ALPHAFAST'        => _x( 'Alphafast', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'CJ_TH'            => _x( 'CJ Thailand', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'FASTRACK'         => _x( 'FastTrack Thailand', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'KERRY_EXPRESS_TH' => _x( 'Kerry Express Thailand', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'NIM_EXPRESS'      => _x( 'NIM Express', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'NINJAVAN_THAI'    => _x( 'Ninjavan Thailand', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'SENDIT'           => _x( 'SendIt', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'THAILAND_POST'    => _x( 'Thailand Post', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'TR'     => array(
			'name'  => _x( 'Turkey', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'PTT_POST' => _x( 'PTT Posta', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'UA'     => array(
			'name'  => _x( 'Ukraine', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'NOVA_POSHTA'     => _x( 'Nova Poshta', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'NOVA_POSHTA_INT' => _x( 'Nova Poshta International', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'AE'     => array(
			'name'  => _x( 'United Arab Emirates', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'AXL'         => _x( 'AXL Express & Logistics', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'CONTINENTAL' => _x( 'Continental', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'SKYNET_UAE'  => _x( 'Skynet Worldwide Express UAE', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'GB'     => array(
			'name'  => _x( 'United Kingdom', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'AIRBORNE_EXPRESS_UK' => _x( 'Airborne Express UK', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'AIRSURE'             => _x( 'Airsure', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'APC_OVERNIGHT'       => _x( 'APC Overnight', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'ASENDIA_UK'          => _x( 'Asendia UK', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'COLLECTPLUS'         => _x( 'CollectPlus', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DELTEC_UK'           => _x( 'Deltec UK', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DHL_UK'              => _x( 'DHL UK', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DPD_DELISTRACK'      => _x( 'DPD Delistrack', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'DPD_UK'              => _x( 'DPD UK', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'FASTWAY_UK'          => _x( 'Fastway UK', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'HERMESWORLD_UK'      => _x( 'HermesWorld', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'INTERLINK'           => _x( 'Interlink Express', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'MYHERMES'            => _x( 'MyHermes UK', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'NIGHTLINE_UK'        => _x( 'Nightline UK', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'PARCELFORCE'         => _x( 'Parcel Force', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'ROYAL_MAIL'          => _x( 'Royal Mail', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'RPD_2_MAN'           => _x( 'RPD2man Deliveries', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'SKYNET_UK'           => _x( 'Skynet Worldwide Express UK', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'TNT_UK'              => _x( 'TNT UK', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'UK_MAIL'             => _x( 'UK Mail', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'YODEL'               => _x( 'Yodel', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'US'     => array(
			'name'  => _x( 'United States', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'ABC_PACKAGE'          => _x( 'ABC Package Express', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'AIRBORNE_EXPRESS'     => _x( 'Airborne Express', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'ASENDIA_US'           => _x( 'Asendia USA', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'CPACKET'              => _x( 'Cpacket', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'ENSENDA'              => _x( 'Ensenda USA', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'ESTES'                => _x( 'Estes', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'FASTWAY_US'           => _x( 'Fastway USA', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'GLOBEGISTICS'         => _x( 'Globegistics USA', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'INTERNATIONAL_BRIDGE' => _x( 'International Bridge', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'ONTRAC'               => _x( 'OnTrac', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'RL_US'                => _x( 'RL Carriers', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'RRDONNELLEY'          => _x( 'RR Donnelley', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'USPS'                 => _x( 'USPS', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
		'VN'     => array(
			'name'  => _x( 'Vietnam', 'Name of carrier country', 'woocommerce-paypal-payments' ),
			'items' => array(
				'KERRY_EXPRESS_VN' => _x( 'Kerry Express Vietnam', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'VIETNAM_POST'     => _x( 'Vietnam Post', 'Name of carrier', 'woocommerce-paypal-payments' ),
				'VNPOST_EMS'       => _x( 'Vietnam Post EMS', 'Name of carrier', 'woocommerce-paypal-payments' ),
			),
		),
	);
