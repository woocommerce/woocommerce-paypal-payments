/**
 * Carriers Extractor
 *
 * Extracts carrier data from PayPal's documentation and formats it into a PHP array
 * for use with WooCommerce PayPal Payments. The page is server-rendered, so a plain fetch is enough.
 *
 * Usage:
 * 1. Run: ddev carriers-extractor
 * 2. Copy the array generated in carriers_data.php into modules/ppcp-order-tracking/carriers.php.
 * 3. Format it with `ddev npm run fix-lint -- modules/ppcp-order-tracking/carriers.php`.
 * 4. Check the changes
 */

const fs = require( 'fs' );

/**
 * Country codes mapping from WooCommerce
 */
const CODES = {
	AF: 'Afghanistan',
	AX: 'Åland Islands',
	AL: 'Albania',
	DZ: 'Algeria',
	AS: 'American Samoa',
	AD: 'Andorra',
	AO: 'Angola',
	AI: 'Anguilla',
	AQ: 'Antarctica',
	AG: 'Antigua and Barbuda',
	AR: 'Argentina',
	AM: 'Armenia',
	AW: 'Aruba',
	AU: 'Australia',
	AT: 'Austria',
	AZ: 'Azerbaijan',
	BS: 'Bahamas',
	BH: 'Bahrain',
	BD: 'Bangladesh',
	BB: 'Barbados',
	BY: 'Belarus',
	BE: 'Belgium',
	PW: 'Belau',
	BZ: 'Belize',
	BJ: 'Benin',
	BM: 'Bermuda',
	BT: 'Bhutan',
	BO: 'Bolivia',
	BQ: 'Bonaire, Saint Eustatius and Saba',
	BA: 'Bosnia and Herzegovina',
	BW: 'Botswana',
	BV: 'Bouvet Island',
	BR: 'Brazil',
	IO: 'British Indian Ocean Territory',
	BN: 'Brunei',
	BG: 'Bulgaria',
	BF: 'Burkina Faso',
	BI: 'Burundi',
	KH: 'Cambodia',
	CM: 'Cameroon',
	CA: 'Canada',
	CV: 'Cape Verde',
	KY: 'Cayman Islands',
	CF: 'Central African Republic',
	TD: 'Chad',
	CL: 'Chile',
	CN: 'China',
	CX: 'Christmas Island',
	CC: 'Cocos (Keeling) Islands',
	CO: 'Colombia',
	KM: 'Comoros',
	CG: 'Congo (Brazzaville)',
	CD: 'Congo (Kinshasa)',
	CK: 'Cook Islands',
	CR: 'Costa Rica',
	HR: 'Croatia',
	CU: 'Cuba',
	CW: 'Curaçao',
	CY: 'Cyprus',
	CZ: 'Czech Republic',
	DK: 'Denmark',
	DJ: 'Djibouti',
	DM: 'Dominica',
	DO: 'Dominican Republic',
	EC: 'Ecuador',
	EG: 'Egypt',
	SV: 'El Salvador',
	GQ: 'Equatorial Guinea',
	ER: 'Eritrea',
	EE: 'Estonia',
	ET: 'Ethiopia',
	FK: 'Falkland Islands',
	FO: 'Faroe Islands',
	FJ: 'Fiji',
	FI: 'Finland',
	FR: 'France',
	GF: 'French Guiana',
	PF: 'French Polynesia',
	TF: 'French Southern Territories',
	GA: 'Gabon',
	GM: 'Gambia',
	GE: 'Georgia',
	DE: 'Germany',
	GH: 'Ghana',
	GI: 'Gibraltar',
	GR: 'Greece',
	GL: 'Greenland',
	GD: 'Grenada',
	GP: 'Guadeloupe',
	GU: 'Guam',
	GT: 'Guatemala',
	GG: 'Guernsey',
	GN: 'Guinea',
	GW: 'Guinea-Bissau',
	GY: 'Guyana',
	HT: 'Haiti',
	HM: 'Heard Island and McDonald Islands',
	HN: 'Honduras',
	HK: 'Hong Kong',
	HU: 'Hungary',
	IS: 'Iceland',
	IN: 'India',
	ID: 'Indonesia',
	IR: 'Iran',
	IQ: 'Iraq',
	IE: 'Ireland',
	IM: 'Isle of Man',
	IL: 'Israel',
	IT: 'Italy',
	CI: 'Ivory Coast',
	JM: 'Jamaica',
	JP: 'Japan',
	JE: 'Jersey',
	JO: 'Jordan',
	KZ: 'Kazakhstan',
	KE: 'Kenya',
	KI: 'Kiribati',
	KW: 'Kuwait',
	KG: 'Kyrgyzstan',
	LA: 'Lao People\'s Democratic Republic (the)',
	LV: 'Latvia',
	LB: 'Lebanon',
	LS: 'Lesotho',
	LR: 'Liberia',
	LY: 'Libya',
	LI: 'Liechtenstein',
	LT: 'Lithuania',
	LU: 'Luxembourg',
	MO: 'Macao',
	MK: 'North Macedonia',
	MG: 'Madagascar',
	MW: 'Malawi',
	MY: 'Malaysia',
	MV: 'Maldives',
	ML: 'Mali',
	MT: 'Malta',
	MH: 'Marshall Islands',
	MQ: 'Martinique',
	MR: 'Mauritania',
	MU: 'Mauritius',
	YT: 'Mayotte',
	MX: 'Mexico',
	FM: 'Micronesia',
	MD: 'Moldova',
	MC: 'Monaco',
	MN: 'Mongolia',
	ME: 'Montenegro',
	MS: 'Montserrat',
	MA: 'Morocco',
	MZ: 'Mozambique',
	MM: 'Myanmar',
	NA: 'Namibia',
	NR: 'Nauru',
	NP: 'Nepal',
	NL: 'Netherlands',
	NC: 'New Caledonia',
	NZ: 'New Zealand',
	NI: 'Nicaragua',
	NE: 'Niger',
	NG: 'Nigeria',
	NU: 'Niue',
	NF: 'Norfolk Island',
	MP: 'Northern Mariana Islands',
	KP: 'North Korea',
	NO: 'Norway',
	OM: 'Oman',
	PK: 'Pakistan',
	PS: 'Palestinian Territory',
	PA: 'Panama',
	PG: 'Papua New Guinea',
	PY: 'Paraguay',
	PE: 'Peru',
	PH: 'Philippines',
	PN: 'Pitcairn',
	PL: 'Poland',
	PT: 'Portugal',
	PR: 'Puerto Rico',
	QA: 'Qatar',
	RE: 'Reunion',
	RO: 'Romania',
	RU: 'Russia',
	RW: 'Rwanda',
	BL: 'Saint Barthélemy',
	SH: 'Saint Helena',
	KN: 'Saint Kitts and Nevis',
	LC: 'Saint Lucia',
	MF: 'Saint Martin (French part)',
	SX: 'Saint Martin (Dutch part)',
	PM: 'Saint Pierre and Miquelon',
	VC: 'Saint Vincent and the Grenadines',
	SM: 'San Marino',
	ST: 'São Tomé and Príncipe',
	SA: 'Saudi Arabia',
	SN: 'Senegal',
	RS: 'Serbia',
	SC: 'Seychelles',
	SL: 'Sierra Leone',
	SG: 'Singapore',
	SK: 'Slovakia',
	SI: 'Slovenia',
	SB: 'Solomon Islands',
	SO: 'Somalia',
	ZA: 'South Africa',
	GS: 'South Georgia/Sandwich Islands',
	KR: 'Korea',
	SS: 'South Sudan',
	ES: 'Spain',
	LK: 'Sri Lanka',
	SD: 'Sudan',
	SR: 'Suriname',
	SJ: 'Svalbard and Jan Mayen',
	SZ: 'Eswatini',
	SE: 'Sweden',
	CH: 'Switzerland',
	SY: 'Syria',
	TW: 'Taiwan',
	TJ: 'Tajikistan',
	TZ: 'Tanzania',
	TH: 'Thailand',
	TL: 'Timor-Leste',
	TG: 'Togo',
	TK: 'Tokelau',
	TO: 'Tonga',
	TT: 'Trinidad and Tobago',
	TN: 'Tunisia',
	TR: 'Turkey',
	TM: 'Turkmenistan',
	TC: 'Turks and Caicos Islands',
	TV: 'Tuvalu',
	UG: 'Uganda',
	UA: 'Ukraine',
	AE: 'United Arab Emirates',
	GB: 'United Kingdom',
	US: 'United States',
	UY: 'Uruguay',
	UZ: 'Uzbekistan',
	VU: 'Vanuatu',
	VA: 'Vatican',
	VE: 'Venezuela',
	VN: 'Vietnam',
	VG: 'Virgin Islands',
	WF: 'Wallis and Futuna',
	EH: 'Western Sahara',
	WS: 'Samoa',
	YE: 'Yemen',
	ZM: 'Zambia',
	ZW: 'Zimbabwe',
};

const SHOW_COUNTERS = true;
const URL = 'https://developer.paypal.com/docs/tracking/reference/carriers/';

/**
 * Extract carrier data from PayPal documentation page
 */
async function extractCarrierData() {
	console.log( 'Fetching PayPal carrier data...' );

	const response = await fetch( URL );
	if ( ! response.ok ) {
		throw new Error(
			`Failed to fetch ${ URL }: HTTP ${ response.status }`
		);
	}
	const html = await response.text();

	const data = [];

	// Each country is an <h2 id="…"> followed by a table of carriers.
	html.split( /<h2\s/ )
		.slice( 1 )
		.forEach( ( section ) => {
			const headingEnd = section.indexOf( '</h2>' );
			if ( ! /^[^>]*\bid=/.test( section ) || headingEnd === -1 ) {
				return;
			}

			const countryName = htmlToText(
				section.slice( section.indexOf( '>' ) + 1, headingEnd )
			);
			const tbody = section.match( /<tbody[^>]*>([\s\S]*?)<\/tbody>/ );
			if ( ! tbody ) {
				return;
			}

			for ( const row of tbody[ 1 ].matchAll(
				/<tr[^>]*>([\s\S]*?)<\/tr>/g
			) ) {
				const cells = [
					...row[ 1 ].matchAll( /<td[^>]*>([\s\S]*?)<\/td>/g ),
				].map( ( cell ) => htmlToText( cell[ 1 ] ) );
				const [ carrierName, carrierKey, countryCode ] = cells;

				if ( carrierName && carrierKey && countryCode ) {
					data.push( {
						carrierName,
						carrierKey,
						countryCode,
						countryName,
					} );
				}
			}
		} );

	if ( ! data.length ) {
		throw new Error(
			'No carriers found, the page markup has probably changed'
		);
	}

	console.log(
		`Extracted ${ data.length } carriers from PayPal documentation`
	);
	return data;
}

/**
 * Strip tags and decode the entities the docs page uses.
 * @param html
 */
function htmlToText( html ) {
	return html
		.replace( /<[^>]*>/g, '' )
		.replace( /&#x([0-9a-f]+);/gi, ( _, hex ) =>
			String.fromCodePoint( parseInt( hex, 16 ) )
		)
		.replace( /&#(\d+);/g, ( _, dec ) =>
			String.fromCodePoint( parseInt( dec, 10 ) )
		)
		.replace( /&quot;/g, '"' )
		.replace( /&lt;/g, '<' )
		.replace( /&gt;/g, '>' )
		.replace( /&nbsp;/g, ' ' )
		.replace( /&amp;/g, '&' )
		.trim();
}

/**
 * Convert extracted data to PHP array structure
 * @param carrierData
 */
function buildCarriersArray( carrierData ) {
	const carriersArray = {};
	const codesFlipped = Object.fromEntries(
		Object.entries( CODES ).map( ( [ k, v ] ) => [ v, k ] )
	);

	carrierData.forEach(
		( { carrierName, carrierKey, countryCode, countryName } ) => {
			// Skip 'OTHER' carrier
			if ( carrierKey === 'OTHER' ) {
				return;
			}

			let arrayKey = 'global';
			if ( countryCode !== 'GLOBAL' ) {
				arrayKey =
					codesFlipped[ countryName ] || countryCode.toUpperCase();
			}

            // Escape single quotes in carrier and country name
            const escapedCountryName = countryName.replace(/'/g, "\\'");
            const escapedCarrierName = carrierName.replace(/'/g, "\\'");

            if ( ! carriersArray[ arrayKey ] ) {
				carriersArray[ arrayKey ] = {
					name:
						arrayKey === 'global'
							? "'Global'"
							: `tr( '${ escapedCountryName }', 'Name of carrier country', 'woocommerce-paypal-payments' )`,
					items: {},
				};
			}

            carriersArray[arrayKey].items[carrierKey] = `tr( '${escapedCarrierName}', 'Name of carrier', 'woocommerce-paypal-payments' )`;
		}
	);

	return carriersArray;
}

/**
 * Show counters for debugging
 * @param carrierData
 */
function showCounters( carrierData ) {
	if ( ! SHOW_COUNTERS ) {
		return;
	}

	console.log( '\nℹ️ INFO\n' );

	const countryCounts = {};
	carrierData.forEach( ( { countryCode } ) => {
		countryCounts[ countryCode ] =
			( countryCounts[ countryCode ] || 0 ) + 1;
	} );

	// Sort by country code
	const sortedCountries = Object.keys( countryCounts ).sort( ( a, b ) =>
		a.localeCompare( b, undefined, { sensitivity: 'base' } )
	);

	sortedCountries.forEach( ( countryCode ) => {
		console.log(
			`${ countryCode }: ${ countryCounts[ countryCode ] } entries`
		);
	} );

	console.log( '' );
}

/**
 * Generate PHP file with carriers array
 * @param carriersArray
 */
function generateCarriersPhpFile( carriersArray ) {
	let output = `<?php
/**
 * Carriers data for PayPal tracking.
 *
 * Generated by carriers-extractor.js
 */

return (array) apply_filters(
\t'woocommerce_paypal_payments_tracking_carriers',
\tarray(
`;

	Object.entries( carriersArray ).forEach(
		( [ countryKey, countryData ] ) => {
			const formattedKey =
				countryKey === 'global' ? 'global' : countryKey.toUpperCase();
			output += `\t\t'${ formattedKey }' => array(
\t\t\t'name'  => ${ countryData.name },
\t\t\t'items' => array(
`;

			Object.entries( countryData.items ).forEach(
				( [ carrierKey, carrierName ] ) => {
					output += `\t\t\t\t'${ carrierKey }' => ${ carrierName },
`;
				}
			);

			output += `\t\t\t),
\t\t),
`;
		}
	);

	output += `\t)
);
`;

	fs.writeFileSync( 'carriers_data.php', output );
	console.log(
		'Carriers data has been successfully extracted and saved to carriers_data.php\n'
	);
}

/**
 * Main execution function
 */
async function main() {
	try {
		const carrierData = await extractCarrierData();
		const carriersArray = buildCarriersArray( carrierData );
		generateCarriersPhpFile( carriersArray );
		showCounters( carrierData );

		console.log( '✅ Extraction completed successfully!' );
	} catch ( error ) {
		console.error( '❌ Error during extraction:', error.message );
		process.exit( 1 );
	}
}

if ( require.main === module ) {
	main();
}
